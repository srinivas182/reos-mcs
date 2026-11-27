# ADR 0061: Video practice reviewed from the words, with the video kept on the device

- Status: Accepted (A14.1b)
- Date: 2026-11-28 (Sprint 14; spec in docs/sprints/S14-spec.md)

## Context

Agents record listing pitches and video intros, but a recording only gets better with feedback on how it sounds. Coaching tools that review video usually upload it to their servers. A pitch video holds the agent's face and voice and often their clients' addresses, so storing it raises privacy questions and storage costs. Most of what makes a pitch better can be measured from the words and the length alone: pace, filler words, opening, proof and close.

## Decision

1. **The video stays on the device.**
   - On the phone, the camera (expo-camera) records up to two minutes, and the agent watches it back there.
   - In the browser, the recording uses the camera, the microphone and MediaRecorder, and stays in the browser.
   - REOS never receives the video.
2. **The words are heard on the device, and the agent checks them.**
   - On the phone, on-device speech recognition reads the recording.
   - In the browser, the browser's speech recognition listens while recording.
   - The agent corrects the words, or types them where the phone or browser can't hear them, before asking for the review.
   - Only the words, the length and the kind of video go to REOS: `POST /api/v1/video-practice`, limited to 10 a minute.
3. **The review.**
   - **Pace:** words a minute; 130 to 160 is good.
   - **Filler words:** each one with its count.
   - **Score:** out of 100, from five parts of 20 points: pace, fillers, opening, proof and close.
   - **Three fixes:** each with a line to try and a lesson.
   - **Who reviews:** Claude when it's configured. REOS's own measures are the fallback, in English or Spanish.
   - **Length:** recordings under 10 seconds or 20 words are too short to review.
4. **Before you post.** The words go through the compliance engine on the `video` channel (B13.1). The review lists what would need fixing before the video is posted, for example the broker's name not being said or shown, or a "guarantee". This check isn't logged, because nothing is published.
5. **Privacy.** Reviews belong to the agent alone: there's no organisation on them, and anyone else gets a 404.

## Consequences

- No video storage, upload time or retention policy is needed, and agents keep control of their own recordings.
- REOS can't review the visuals, such as eye contact, framing or light. That would need the video and would be a separate opt-in.
- Some Android phones can't read words from a video file. On those, the agent types what they said.
