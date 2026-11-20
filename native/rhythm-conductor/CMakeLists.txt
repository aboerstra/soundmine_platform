cmake_minimum_required(VERSION 3.24)
project(SoundmineRhythmConductor VERSION 0.1.0 LANGUAGES C CXX)

include(CTest)
option(SOUNDMINE_BUILD_VST3 "Build the JUCE Pattern Conductor prototype" ON)

add_library(rhythm_cue_scheduler STATIC src/CueScheduler.cpp)
target_compile_features(rhythm_cue_scheduler PUBLIC cxx_std_20)
target_include_directories(rhythm_cue_scheduler PUBLIC src)
set_target_properties(rhythm_cue_scheduler PROPERTIES POSITION_INDEPENDENT_CODE ON)

if(BUILD_TESTING)
    add_executable(rhythm_cue_tests tests/CueSchedulerTest.cpp)
    target_link_libraries(rhythm_cue_tests PRIVATE rhythm_cue_scheduler)
    add_test(NAME rhythm_cue_scheduler COMMAND rhythm_cue_tests)
endif()

if(SOUNDMINE_BUILD_VST3)
    find_package(JUCE 8.0.12 EXACT CONFIG QUIET)
    if(NOT TARGET juce::juce_audio_processors)
        include(FetchContent)
        FetchContent_Declare(JUCE
            URL https://codeload.github.com/juce-framework/JUCE/tar.gz/refs/tags/8.0.12
            URL_HASH SHA256=dec1a8baee5aaec4502b717a421b850e257ab7e9c2efd03766185ec1957cf102
        )
        FetchContent_MakeAvailable(JUCE)
    endif()

    get_filename_component(brand_assets "${CMAKE_CURRENT_SOURCE_DIR}/../../public/brand" ABSOLUTE)
    juce_add_binary_data(PatternConductorAssets
        HEADER_NAME PatternConductorAssets.h
        NAMESPACE PatternConductorAssets
        SOURCES
            "${brand_assets}/products/thesoundmine-pattern-conductor-lockup-on-dark.png"
            "${brand_assets}/fonts/SpaceGrotesk-Regular.ttf"
            "${brand_assets}/fonts/JetBrainsMono-Medium.ttf"
    )
    set_target_properties(PatternConductorAssets PROPERTIES POSITION_INDEPENDENT_CODE ON)

    juce_add_plugin(RhythmConductor
        COMPANY_NAME TheSoundMine
        COMPANY_WEBSITE https://thesoundmine.com
        BUNDLE_ID com.soundmine.rhythmconductor
        PLUGIN_MANUFACTURER_CODE Sdmn
        PLUGIN_CODE Rhcd
        PRODUCT_NAME "TheSoundMine Pattern Conductor"
        ICON_BIG "${brand_assets}/products/thesoundmine-pattern-conductor-app-icon-1024.png"
        FORMATS VST3
        IS_SYNTH TRUE
        NEEDS_MIDI_OUTPUT TRUE
        VST3_CATEGORIES Instrument
        COPY_PLUGIN_AFTER_BUILD FALSE
    )
    target_sources(RhythmConductor PRIVATE src/PluginProcessor.cpp)
    target_compile_definitions(RhythmConductor PRIVATE
        JUCE_USE_CURL=0
        JUCE_WEB_BROWSER=0
        JUCE_VST3_CAN_REPLACE_VST2=0
    )
    target_link_libraries(RhythmConductor PRIVATE
        rhythm_cue_scheduler
        PatternConductorAssets
        juce::juce_audio_processors
        juce::juce_recommended_config_flags
    )
    set(vst3_resources "$<TARGET_FILE_DIR:RhythmConductor_VST3>/../Resources")
    add_custom_command(TARGET RhythmConductor_VST3 POST_BUILD
        COMMAND ${CMAKE_COMMAND} -E make_directory "${vst3_resources}"
        COMMAND ${CMAKE_COMMAND} -E copy_if_different
            "${brand_assets}/fonts/SpaceGrotesk-OFL.txt"
            "${brand_assets}/fonts/JetBrainsMono-OFL.txt"
            "${vst3_resources}"
        VERBATIM
    )
endif()
