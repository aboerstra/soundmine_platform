cmake_minimum_required(VERSION 3.24)
project(SoundmineStudio VERSION 0.1.0 LANGUAGES CXX)

if(NOT TARGET nlohmann_json::nlohmann_json)
    find_package(nlohmann_json 3.12 QUIET CONFIG)
    if(NOT nlohmann_json_FOUND)
        include(FetchContent)
        FetchContent_Declare(nlohmann_json
            URL https://codeload.github.com/nlohmann/json/tar.gz/refs/tags/v3.12.0
            URL_HASH SHA256=4b92eb0c06d10683f7447ce9406cb97cd4b453be18d7279320f7b2f025c10187
        )
        FetchContent_MakeAvailable(nlohmann_json)
    endif()
endif()

add_library(soundmine_studio src/workflow.cpp src/json.cpp)
add_library(Soundmine::Studio ALIAS soundmine_studio)
target_compile_features(soundmine_studio PUBLIC cxx_std_20)
target_include_directories(soundmine_studio PUBLIC include)
target_link_libraries(soundmine_studio PRIVATE nlohmann_json::nlohmann_json)

if(PROJECT_IS_TOP_LEVEL)
    include(CTest)
endif()
if(BUILD_TESTING)
    add_executable(studio_tests tests/workflow_test.cpp)
    target_link_libraries(studio_tests PRIVATE Soundmine::Studio)
    add_test(NAME studio_contracts
        COMMAND studio_tests "${CMAKE_CURRENT_SOURCE_DIR}/../../fixtures/studio/v1"
            "${CMAKE_CURRENT_BINARY_DIR}/conformance")
endif()
