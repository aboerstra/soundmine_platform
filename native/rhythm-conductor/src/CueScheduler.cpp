#include "CueScheduler.hpp"

#include <algorithm>
#include <cmath>
#include <limits>

namespace soundmine::rhythm {
namespace {

double beatsPerSample(const TransportBlock& block) noexcept
{
    return (block.bpm / 60.0) / block.sampleRate;
}

bool usable(const TransportBlock& block) noexcept
{
    return std::isfinite(block.ppq) && std::isfinite(block.lastBarPpq) &&
           std::isfinite(block.bpm) && block.bpm > 0.0 &&
           std::isfinite(block.quarterNotesPerBar) && block.quarterNotesPerBar > 0.0 &&
           std::isfinite(block.sampleRate) && block.sampleRate > 0.0 && block.samples > 0 &&
           std::isfinite(beatsPerSample(block)) && beatsPerSample(block) > 0.0;
}

bool discontinuity(const TransportBlock& previous, const TransportBlock& current) noexcept
{
    if (previous.quarterNotesPerBar != current.quarterNotesPerBar)
        return true;
    if (previous.samplePosition && current.samplePosition) {
        const auto start = *previous.samplePosition;
        return start > std::numeric_limits<std::int64_t>::max() - previous.samples ||
               start + previous.samples != *current.samplePosition;
    }

    const double expectedPpq = previous.ppq + previous.samples * beatsPerSample(previous);
    const double tolerance = std::max(0.001, 4.0 * beatsPerSample(current));
    return std::abs(current.ppq - expectedPpq) > tolerance;
}

bool validCue(const Cue& cue) noexcept
{
    return cue.channel >= 1 && cue.channel <= 16 && cue.program >= 0 && cue.program <= 127 &&
           std::isfinite(cue.leadQuarterNotes) && cue.leadQuarterNotes >= 0.0;
}

} // namespace

std::optional<ScheduledMIDI> CueScheduler::process(std::optional<TransportBlock> block,
                                                   std::optional<Cue> request) noexcept
{
    const bool moving = block && block->playing && !block->looping && usable(*block);
    if (previous_ && (!moving || discontinuity(*previous_, *block))) {
        if (pending_)
            status_ = CueStatus::interrupted;
        pending_.reset();
        launchBar_.reset();
    }
    previous_ = moving ? block : std::nullopt;

    if (request) {
        launchBar_.reset();
        if (!validCue(*request)) {
            pending_.reset();
            status_ = CueStatus::invalidCue;
            return std::nullopt;
        }
        pending_ = request;
    }

    if (!pending_)
        return std::nullopt;
    if (block && block->looping) {
        pending_.reset();
        status_ = CueStatus::loopUnsupported;
        return std::nullopt;
    }
    if (block && !block->playing) {
        status_ = CueStatus::waitingForPlayback;
        return std::nullopt;
    }
    if (!moving) {
        status_ = CueStatus::awaitingHostPosition;
        return std::nullopt;
    }

    const auto& transport = *block;
    const double barLength = transport.quarterNotesPerBar;
    const double lead = pending_->leadQuarterNotes;
    if (lead >= barLength) {
        pending_.reset();
        status_ = CueStatus::invalidCue;
        return std::nullopt;
    }

    const double samplePpq = beatsPerSample(transport);
    constexpr double sampleTolerance = 1e-6;
    if (!launchBar_) {
        const auto barsSinceAnchor = std::floor((transport.ppq - transport.lastBarPpq) / barLength);
        launchBar_ = transport.lastBarPpq + (barsSinceAnchor + 1.0) * barLength;
        if (*launchBar_ - lead < transport.ppq - sampleTolerance * samplePpq)
            *launchBar_ += barLength;
    }

    const double offset = std::ceil((*launchBar_ - lead - transport.ppq) / samplePpq - sampleTolerance);
    if (!std::isfinite(offset) || offset < 0.0 ||
        std::abs(std::remainder(*launchBar_ - transport.lastBarPpq, barLength)) > 0.001) {
        pending_.reset();
        launchBar_.reset();
        status_ = CueStatus::interrupted;
        return std::nullopt;
    }
    if (offset >= transport.samples) {
        status_ = CueStatus::scheduled;
        return std::nullopt;
    }

    const auto event = ScheduledMIDI{*pending_, static_cast<int>(offset)};
    pending_.reset();
    launchBar_.reset();
    status_ = CueStatus::submitted;
    return event;
}

} // namespace soundmine::rhythm
