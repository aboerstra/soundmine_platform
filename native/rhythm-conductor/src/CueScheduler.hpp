#pragma once

#include <cstdint>
#include <optional>

namespace soundmine::rhythm {

struct Cue {
    int channel; // MIDI channels 1–16.
    int program; // Wire value 0–127.
    double leadQuarterNotes;
};

struct TransportBlock {
    bool playing{};
    bool looping{};
    double ppq{};
    double lastBarPpq{};
    double bpm{};
    double quarterNotesPerBar{};
    double sampleRate{};
    int samples{};
    std::optional<std::int64_t> samplePosition;
};

enum class CueStatus {
    idle,
    waitingForPlayback,
    awaitingHostPosition,
    scheduled,
    submitted,
    invalidCue,
    interrupted,
    loopUnsupported,
    offlineRendering,
    midiBufferRejected,
};

struct ScheduledMIDI {
    Cue cue;
    int sampleOffset;
};

class CueScheduler {
public:
    std::optional<ScheduledMIDI> process(std::optional<TransportBlock> block,
                                         std::optional<Cue> request = std::nullopt) noexcept;
    CueStatus status() const noexcept { return status_; }

private:
    std::optional<TransportBlock> previous_;
    std::optional<Cue> pending_;
    std::optional<double> launchBar_;
    CueStatus status_{CueStatus::idle};
};

} // namespace soundmine::rhythm
