#include "CueScheduler.hpp"

#include <juce_audio_processors/juce_audio_processors.h>

#include <atomic>
#include <cmath>

namespace soundmine::rhythm {
namespace {

constexpr auto channelId = "channel";
constexpr auto programId = "program";
constexpr auto leadId = "lead";
constexpr unsigned cueFlag = 1u << 21;
constexpr double ticksPerQuarterNote = 96.0;

juce::AudioProcessorValueTreeState::ParameterLayout makeParameters()
{
    juce::AudioProcessorValueTreeState::ParameterLayout layout;
    layout.add(std::make_unique<juce::AudioParameterInt>(
        juce::ParameterID{channelId, 1}, "MIDI channel", 1, 16, 1));
    layout.add(std::make_unique<juce::AudioParameterInt>(
        juce::ParameterID{programId, 1}, "MIDI program", 1, 128, 1));
    layout.add(std::make_unique<juce::AudioParameterFloat>(
        juce::ParameterID{leadId, 1}, "Cue lead",
        juce::NormalisableRange<float>{0.0f, 8.0f, 1.0f / 96.0f}, 1.0f));
    return layout;
}

class Processor final : public juce::AudioProcessor {
public:
    Processor()
        : AudioProcessor(BusesProperties().withOutput("Output", juce::AudioChannelSet::stereo(), true))
    {
    }

    const juce::String getName() const override { return "Rhythm Conductor"; }
    bool acceptsMidi() const override { return false; }
    bool producesMidi() const override { return true; }
    bool isMidiEffect() const override { return false; }
    double getTailLengthSeconds() const override { return 0.0; }
    bool hasEditor() const override { return true; }
    juce::AudioProcessorEditor* createEditor() override;
    int getNumPrograms() override { return 1; }
    int getCurrentProgram() override { return 0; }
    void setCurrentProgram(int) override {}
    const juce::String getProgramName(int) override { return {}; }
    void changeProgramName(int, const juce::String&) override {}

    void prepareToPlay(double sampleRate, int) override
    {
        sampleRate_ = sampleRate;
        scheduler_ = {};
        request_.store(0, std::memory_order_relaxed);
        status_.store(CueStatus::idle, std::memory_order_relaxed);
    }

    void releaseResources() override {}

    bool isBusesLayoutSupported(const BusesLayout& layout) const override
    {
        return layout.getMainOutputChannelSet() == juce::AudioChannelSet::stereo();
    }

    void processBlock(juce::AudioBuffer<float>& audio, juce::MidiBuffer& midi) override
    {
        audio.clear();
        midi.clear();
        const auto request = takeCue();
        if (isNonRealtime()) {
            scheduler_ = {};
            status_.store(CueStatus::offlineRendering, std::memory_order_relaxed);
            return;
        }

        const auto event = scheduler_.process(readTransport(audio.getNumSamples()), request);
        auto status = scheduler_.status();
        if (event && !midi.addEvent(juce::MidiMessage::programChange(event->cue.channel, event->cue.program),
                                   event->sampleOffset))
            status = CueStatus::midiBufferRejected;

        status_.store(status, std::memory_order_relaxed);
    }

    void getStateInformation(juce::MemoryBlock& data) override
    {
        if (const auto xml = parameters.copyState().createXml())
            copyXmlToBinary(*xml, data);
    }

    void setStateInformation(const void* data, int size) override
    {
        if (const auto xml = getXmlFromBinary(data, size);
            xml && xml->hasTagName(parameters.state.getType()))
            parameters.replaceState(juce::ValueTree::fromXml(*xml));
    }

    void queueCue()
    {
        const auto channel = static_cast<unsigned>(parameters.getRawParameterValue(channelId)->load()) - 1u;
        const auto program = static_cast<unsigned>(parameters.getRawParameterValue(programId)->load()) - 1u;
        const auto ticks = static_cast<unsigned>(std::lround(parameters.getRawParameterValue(leadId)->load() *
                                                              ticksPerQuarterNote));
        request_.store(cueFlag | (ticks << 11) | (channel << 7) | program, std::memory_order_relaxed);
    }

    CueStatus status() const { return status_.load(std::memory_order_relaxed); }
    bool hasUnreadCue() const { return request_.load(std::memory_order_relaxed) != 0; }

    juce::AudioProcessorValueTreeState parameters{*this, nullptr, "RhythmConductorPrototype", makeParameters()};

private:
    std::optional<Cue> takeCue() noexcept
    {
        const auto bits = request_.exchange(0, std::memory_order_relaxed);
        if (bits == 0)
            return std::nullopt;
        return Cue{static_cast<int>((bits >> 7) & 0xfu) + 1, static_cast<int>(bits & 0x7fu),
                   static_cast<double>((bits >> 11) & 0x3ffu) / ticksPerQuarterNote};
    }

    std::optional<TransportBlock> readTransport(int samples) const
    {
        const auto* playHead = getPlayHead();
        const auto position = playHead ? playHead->getPosition() : juce::Optional<juce::AudioPlayHead::PositionInfo>{};
        if (!position)
            return std::nullopt;

        TransportBlock block{};
        block.playing = position->getIsPlaying();
        block.looping = position->getIsLooping();
        if (!block.playing || block.looping)
            return block;

        const auto ppq = position->getPpqPosition();
        const auto bar = position->getPpqPositionOfLastBarStart();
        const auto tempo = position->getBpm();
        const auto meter = position->getTimeSignature();
        if (!ppq || !bar || !tempo || !meter || meter->numerator <= 0 || meter->denominator <= 0)
            return std::nullopt;

        block.ppq = *ppq;
        block.lastBarPpq = *bar;
        block.bpm = *tempo;
        block.quarterNotesPerBar = meter->numerator * 4.0 / meter->denominator;
        block.sampleRate = sampleRate_;
        block.samples = samples;
        if (const auto samplePosition = position->getTimeInSamples())
            block.samplePosition = *samplePosition;
        return block;
    }

    // One atomic publishes the complete cue from the editor to the audio thread.
    static_assert(std::atomic<unsigned>::is_always_lock_free);
    static_assert(std::atomic<CueStatus>::is_always_lock_free);
    std::atomic<unsigned> request_{0};
    std::atomic<CueStatus> status_{CueStatus::idle};
    CueScheduler scheduler_;
    double sampleRate_{};
};

juce::String statusText(CueStatus status)
{
    switch (status) {
        case CueStatus::idle: return "Ready";
        case CueStatus::waitingForPlayback: return "Waiting for playback";
        case CueStatus::awaitingHostPosition: return "Waiting for host position, tempo and meter";
        case CueStatus::scheduled: return "Queued for a bar boundary";
        case CueStatus::submitted: return "Program Change sent to the host";
        case CueStatus::invalidCue: return "Set cue lead shorter than one bar";
        case CueStatus::interrupted: return "Cue canceled after a transport change";
        case CueStatus::loopUnsupported: return "Turn looping off to cue a program";
        case CueStatus::offlineRendering: return "Cues are disabled during offline rendering";
        case CueStatus::midiBufferRejected: return "The MIDI buffer rejected the Program Change";
    }
    return {};
}

class Editor final : public juce::AudioProcessorEditor, private juce::Timer {
public:
    explicit Editor(Processor& owner)
        : AudioProcessorEditor(owner), owner_(owner),
          channelAttachment_(owner.parameters, channelId, channel_),
          programAttachment_(owner.parameters, programId, program_),
          leadAttachment_(owner.parameters, leadId, lead_)
    {
        addSetting(channel_, channelLabel_, "MIDI channel");
        addSetting(program_, programLabel_, "MIDI program (1–128)");
        addSetting(lead_, leadLabel_, "Cue lead (quarter notes)");
        lead_.setNumDecimalPlacesToDisplay(3);
        addAndMakeVisible(queue_);
        queue_.onClick = [this] {
            owner_.queueCue();
            timerCallback();
        };
        addAndMakeVisible(status_);
        setSize(420, 198);
        timerCallback();
        startTimerHz(12);
    }

    void paint(juce::Graphics& graphics) override
    {
        graphics.fillAll(getLookAndFeel().findColour(juce::ResizableWindow::backgroundColourId));
    }

    void resized() override
    {
        auto area = getLocalBounds().reduced(14);
        place(area.removeFromTop(34), channelLabel_, channel_);
        place(area.removeFromTop(34), programLabel_, program_);
        place(area.removeFromTop(34), leadLabel_, lead_);
        area.removeFromTop(8);
        queue_.setBounds(area.removeFromTop(28).removeFromLeft(130));
        status_.setBounds(area.removeFromTop(26));
    }

private:
    void addSetting(juce::Slider& slider, juce::Label& label, const juce::String& text)
    {
        label.setText(text, juce::dontSendNotification);
        label.attachToComponent(&slider, true);
        slider.setSliderStyle(juce::Slider::LinearHorizontal);
        slider.setTextBoxStyle(juce::Slider::TextBoxRight, false, 64, 22);
        addAndMakeVisible(slider);
        addAndMakeVisible(label);
    }

    static void place(juce::Rectangle<int> row, juce::Label& label, juce::Slider& slider)
    {
        label.setBounds(row.removeFromLeft(159));
        slider.setBounds(row);
    }

    void timerCallback() override
    {
        status_.setText(owner_.hasUnreadCue() ? "Waiting for host processing" : statusText(owner_.status()),
                        juce::dontSendNotification);
    }

    Processor& owner_;
    juce::Slider channel_, program_, lead_;
    juce::Label channelLabel_, programLabel_, leadLabel_, status_;
    juce::TextButton queue_{"Queue program"};
    juce::AudioProcessorValueTreeState::SliderAttachment channelAttachment_, programAttachment_, leadAttachment_;
};

juce::AudioProcessorEditor* Processor::createEditor()
{
    return new Editor(*this);
}

} // namespace
} // namespace soundmine::rhythm

juce::AudioProcessor* JUCE_CALLTYPE createPluginFilter()
{
    return new soundmine::rhythm::Processor();
}
