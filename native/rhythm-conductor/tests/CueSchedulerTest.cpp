#include "CueScheduler.hpp"

#include <iostream>
#include <stdexcept>

using namespace soundmine::rhythm;

namespace {

void expect(bool condition, const char* message)
{
    if (!condition)
        throw std::runtime_error(message);
}

TransportBlock block(double ppq, int samples, double barStart = 0.0)
{
    return {
        .playing = true,
        .ppq = ppq,
        .lastBarPpq = barStart,
        .bpm = 60.0,
        .quarterNotesPerBar = 4.0,
        .sampleRate = 1000.0,
        .samples = samples,
        .samplePosition = std::nullopt,
    };
}

void sendsExactlyOnceAtTheCalculatedOffset()
{
    CueScheduler scheduler;
    expect(!scheduler.process(block(2.75, 250), Cue{6, 16, 1.0}), "Block end must be exclusive");
    expect(scheduler.status() == CueStatus::scheduled, "Cue should be scheduled");
    const auto event = scheduler.process(block(3.0, 250));
    expect(event && event->sampleOffset == 0 && event->cue.channel == 6 && event->cue.program == 16,
           "Expected unchanged MIDI cue at the next block start");
    expect(scheduler.status() == CueStatus::submitted, "Cue did not report submission");
    expect(!scheduler.process(block(3.25, 250)), "Cue must not repeat");
}

void lateRequestMovesToTheFirstBarWithEnoughLead()
{
    CueScheduler scheduler;
    expect(!scheduler.process(block(3.5, 500), Cue{1, 127, 1.0}), "Cue took a bar with insufficient lead");
    expect(!scheduler.process(block(4.0, 2750, 4.0)), "Cue was submitted before its lead time");
    const auto event = scheduler.process(block(6.75, 500, 4.0));
    expect(event && event->sampleOffset == 250, "Cue should reach the host one quarter note before bar three");
}

void waitsWhileStoppedAndUsesTheHostMeter()
{
    CueScheduler scheduler;
    auto stopped = block(0.0, 256);
    stopped.playing = false;
    expect(!scheduler.process(stopped, Cue{16, 0, 0.5}), "Stopped transport must not send a cue");
    expect(scheduler.status() == CueStatus::waitingForPlayback, "Expected to wait for playback");

    auto sixEight = block(2.25, 500);
    sixEight.quarterNotesPerBar = 3.0;
    const auto event = scheduler.process(sixEight);
    expect(event && event->sampleOffset == 250, "Six-eight meter should give three quarter notes per bar");
}

void readingMissingPositionDoesNotGuessTheBar()
{
    CueScheduler scheduler;
    expect(!scheduler.process(std::nullopt, Cue{1, 0, 0.0}), "Missing host position must not send a cue");
    expect(scheduler.status() == CueStatus::awaitingHostPosition, "Expected a missing-position status");
    const auto event = scheduler.process(block(3.75, 500));
    expect(event && event->sampleOffset == 250, "A new cue should schedule when the host reports position");
}

void transportInterruptionsCancelPendingCues()
{
    const auto queue = [](CueScheduler& scheduler) {
        expect(!scheduler.process(block(1.0, 200), Cue{1, 5, 1.0}), "Test cue submitted unexpectedly");
    };

    CueScheduler scheduler;
    queue(scheduler);
    expect(!scheduler.process(block(2.95, 100)), "Seek should cancel a cue before it reaches hardware");
    expect(scheduler.status() == CueStatus::interrupted, "Seek did not report interruption");

    scheduler = {};
    queue(scheduler);
    auto stopped = block(1.2, 200);
    stopped.playing = false;
    expect(!scheduler.process(stopped), "Stop should cancel a pending cue");
    expect(scheduler.status() == CueStatus::interrupted, "Stop did not report interruption");
    expect(!scheduler.process(block(2.95, 100)), "A canceled cue survived playback restart");

    scheduler = {};
    queue(scheduler);
    expect(!scheduler.process(std::nullopt), "Losing transport should cancel a scheduled cue");
    expect(scheduler.status() == CueStatus::interrupted, "Loss of host position did not report interruption");

    scheduler = {};
    queue(scheduler);
    auto changedMeter = block(1.2, 100);
    changedMeter.quarterNotesPerBar = 3.0;
    expect(!scheduler.process(changedMeter) && scheduler.status() == CueStatus::interrupted,
           "A changed meter must retire a previously scheduled cue");
}

void recomputesTheSampleOffsetAfterATempoChange()
{
    CueScheduler scheduler;
    auto first = block(2.0, 750);
    first.samplePosition = 2000;
    expect(!scheduler.process(first, Cue{1, 0, 1.0}), "Cue should be pending");

    auto faster = block(2.75, 500);
    faster.samplePosition = 2750;
    faster.bpm = 120;
    const auto event = scheduler.process(faster);
    expect(event && event->sampleOffset == 125, "Cue should respect the new tempo");
}

void rejectsLoopsAndInvalidCues()
{
    CueScheduler scheduler;
    auto looping = block(2.75, 500);
    looping.looping = true;
    expect(!scheduler.process(looping, Cue{1, 0, 1.0}) && scheduler.status() == CueStatus::loopUnsupported,
           "Loop mode must reject cues");
    expect(!scheduler.process(block(2.75, 500)), "Loop rejection must clear the cue");
    expect(!scheduler.process(block(3.25, 100), Cue{1, 0, 4.0}) && scheduler.status() == CueStatus::invalidCue,
           "Cue lead must be shorter than one bar");
    expect(!scheduler.process(block(3.35, 100), Cue{17, 0, 0.0}) && scheduler.status() == CueStatus::invalidCue,
           "Invalid MIDI channel must be rejected");
}

void newestRequestReplacesThePendingCue()
{
    CueScheduler scheduler;
    expect(!scheduler.process(block(2.75, 50), Cue{1, 16, 1.0}), "First cue should be pending");
    const auto event = scheduler.process(block(2.8, 400), Cue{9, 42, 1.0});
    expect(event && event->sampleOffset == 200 && event->cue.channel == 9 && event->cue.program == 42,
           "The latest cue must replace the previous program and channel together");
}

} // namespace

int main()
{
    try {
        sendsExactlyOnceAtTheCalculatedOffset();
        lateRequestMovesToTheFirstBarWithEnoughLead();
        waitsWhileStoppedAndUsesTheHostMeter();
        readingMissingPositionDoesNotGuessTheBar();
        transportInterruptionsCancelPendingCues();
        recomputesTheSampleOffsetAfterATempoChange();
        rejectsLoopsAndInvalidCues();
        newestRequestReplacesThePendingCue();
        std::cout << "Rhythm Conductor cue scheduler cases passed\n";
        return 0;
    } catch (const std::exception& error) {
        std::cerr << error.what() << '\n';
        return 1;
    }
}
