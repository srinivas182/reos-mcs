/**
 * Lock-screen and headphone controls for hands-free mode (screen 51, A9.3;
 * decision 4). A quiet "now playing" session keeps the quiz going with the
 * screen locked, and gives the lock screen and headphone buttons a job:
 * play or pause is Repeat, next is Skip.
 *
 * It plays a short silent track on a loop (two copies, so Next has somewhere
 * to go); nothing is recorded and no audio leaves the phone.
 */
import { type AudioPlaylist, createAudioPlaylist, setAudioModeAsync } from 'expo-audio';

const SILENCE = require('../../../assets/audio/silence.m4a') as number;

export type Remote = { title: (text: string) => void; stop: () => void };

export async function startRemote(title: string, onRepeat: () => void, onSkip: () => void): Promise<Remote> {
  await setAudioModeAsync({ playsInSilentMode: true, shouldPlayInBackground: true, allowsRecording: true, interruptionMode: 'doNotMix' });
  const playlist: AudioPlaylist = createAudioPlaylist({ sources: [SILENCE, SILENCE], loop: 'single' });
  let stopping = false;
  let wasPlaying = false;

  const track = playlist.addListener('trackChanged', ({ currentIndex }) => {
    if (currentIndex === 0 || stopping) return;
    playlist.skipTo(0);
    playlist.play();
    onSkip();
  });
  const status = playlist.addListener('playlistStatusUpdate', ({ playing }) => {
    // A pause we didn't ask for came from the lock screen or the headphones.
    if (wasPlaying && !playing && !stopping) {
      playlist.play();
      onRepeat();
    }
    wasPlaying = playing;
  });

  playlist.setActiveForLockScreen(true, { title, artist: 'REOS' }, { showNextTrack: true, showPreviousTrack: false, showSeekBackward: false, showSeekForward: false });
  playlist.play();

  return {
    title: (text) => playlist.updateLockScreenMetadata({ title: text, artist: 'REOS' }),
    stop: () => {
      stopping = true;
      track.remove();
      status.remove();
      playlist.pause();
      playlist.clearLockScreenControls();
      playlist.destroy();
    },
  };
}
