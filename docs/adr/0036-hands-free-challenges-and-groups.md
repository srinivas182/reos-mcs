# ADR 0036: Hands-free mode, challenges and study groups

- Status: Accepted (hands-free mode, A9.3a)
- Date: 2026-11-23 (Sprint 9; spec in docs/sprints/S09-spec.md)

## Context

Candidates want to study when they can't look at the screen: on a walk, at the gym, doing chores. Prospects were clear that a study feature must never be sold for driving.

Sprint 9 decision 4 settled the approach:
- voice answers that keep going with the screen locked;
- the headphone buttons as a fallback;
- no driving features.

## Decision (hands-free mode, A9.3a; phone screen 51)

1. **The quiz is a smart drill (A9.2b) in Practice mode, read aloud.**
   - The phone's own voice reads the question and its options (`expo-speech`), in English or Spanish.
   - Each answer is saved with the same endpoint as tapping. The server returns why, and the phone says it.
   - The drill's answers count towards readiness like any other.
   - No new server endpoint is needed.
2. **The answer is understood on the phone** (`lib/handsFree.ts`, pure and tested). It can be:
   - a letter ("B", "bee", "option C", and the Spanish letter names);
   - an ordinal ("the second one");
   - "true" or "false";
   - or the option's own words, compared both ways so a short option isn't won by one shared word, with spoken numbers read as digits.
   "Repeat", "Skip" and "Stop" are commands in both languages. Anything unclear is asked again twice, then the question is repeated.
3. **Speech is recognized on the device only** (`requiresOnDeviceRecognition`), so no audio leaves the phone. A phone that can't do that still reads the questions aloud, and the candidate taps the big answer buttons.
4. **Locked screen and headphones.** A quiet "now playing" session (`expo-audio` with background playback and recording) keeps the quiz alive with the screen locked and gives the lock screen and headphone buttons a job:
   - **Play or pause** repeats the question.
   - **Next** skips it.

   It loops a 30-second silent track, two copies, so Next has somewhere to go. The session ends with the quiz.
5. **Not for driving.** The start screen says so plainly. There's no CarPlay or Android Auto integration, and no wording anywhere offers driving (tested on the shortcut text).
6. **Ways in:**
   - the practice hub;
   - the home-screen quick action "Quiz me";
   - "Hey Siri, quiz me with REOS": an App Shortcut in the app itself (App Intents, iOS 18+), added by `plugins/with-quiz-shortcut.js`, with an English phrase and a Spanish one;
   - the Android App Action: a `shortcuts.xml` "open app feature" capability ("voice quiz").

   All of them open `reos://practice/hands-free`.

## Consequences

- **Device builds:** the Siri and Assistant entries need a development or store build. The config plugin is checked by `expo prebuild` (Swift file in the target, manifest meta-data), but Swift is only compiled on macOS in EAS.
- **iOS versions:** iOS before 18 gets the quick action and the deep link (usable from the Shortcuts app) but no Siri phrase.
- **Background audio:** iOS's background audio mode and Android's media and microphone foreground services are declared. The store review notes must explain that they serve the voice quiz.

## Alternatives considered

- **A separate native App Intents extension:** not needed. App Shortcuts can live in the main app target, which a config plugin can add to.
- **Server-side speech recognition:** it would send audio off the phone, against the privacy promise, and it adds latency.
- **A screen-on mode only:** decision 4 chose lock-screen use.
