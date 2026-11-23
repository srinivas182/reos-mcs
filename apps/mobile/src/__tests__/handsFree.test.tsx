import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import HandsFreeScreen from '@/app/practice/hands-free';
import { feedbackText, questionText, understand } from '@/lib/handsFree';
import { i18n } from '@/lib/i18n';
import type { Attempt } from '@/lib/practice';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() }, useLocalSearchParams: jest.fn(() => ({})) }));
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const speech = jest.requireMock<{ __emit: (name: string, event: unknown) => void; ExpoSpeechRecognitionModule: { start: jest.Mock } }>('expo-speech-recognition');
const voice = jest.requireMock<{ __spoken: string[]; speak: jest.Mock }>('expo-speech');
const audio = jest.requireMock<{ __emit: (name: string, event: unknown) => void; __playlist: { setActiveForLockScreen: jest.Mock; play: jest.Mock; skipTo: jest.Mock } }>('expo-audio');

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const options = ['At a scheduled 78 percent loan-to-value', 'At 90 percent', 'Never', 'When the buyer asks'];
const drill = {
  id: 14,
  kind: 'drill',
  mode: 'practice',
  total: 2,
  questions: [
    { id: 101, n: 1, topic: 'finance', prompt: 'When must borrower-paid PMI end automatically?', options, choice: null, flagged: false, feedback: null },
    { id: 102, n: 2, topic: 'agency', prompt: 'What does IABS stand for?', options: ['Information About Brokerage Services', 'Intermediary Agreement', 'Inspection', 'Insurance'], choice: null, flagged: false, feedback: null },
  ],
} as unknown as Attempt;

beforeEach(() => {
  jest.clearAllMocks();
  voice.__spoken.length = 0;
});

const t = i18n.getFixedT('en');
const flush = () => act(async () => new Promise((resolve) => setTimeout(resolve, 10)));

describe('understanding a spoken answer (screen 51, A9.3)', () => {
  it('takes a letter, an ordinal, true or false, or the answer\'s own words', () => {
    expect(understand('B', options, 'en')).toEqual({ kind: 'answer', choice: 1 });
    expect(understand('the answer is option see', options, 'en')).toEqual({ kind: 'answer', choice: 2 });
    expect(understand('Bee.', options, 'en')).toEqual({ kind: 'answer', choice: 1 });
    expect(understand('the first one', options, 'en')).toEqual({ kind: 'answer', choice: 0 });
    expect(understand('seventy eight percent loan to value', ['At a scheduled 78 percent loan-to-value', 'At 90 percent', 'Never', 'Ask'], 'en')).toEqual({ kind: 'answer', choice: 0 });
    expect(understand('scheduled loan-to-value', options, 'en')).toEqual({ kind: 'answer', choice: 0 });
    expect(understand('true', ['True', 'False'], 'en')).toEqual({ kind: 'answer', choice: 0 });
    expect(understand('falso', ['Verdadero', 'Falso'], 'es')).toEqual({ kind: 'answer', choice: 1 });
    expect(understand('la respuesta es be', options, 'es')).toEqual({ kind: 'answer', choice: 1 });
  });

  it('hears Repeat, Skip and Stop in both languages, and asks again when unclear', () => {
    expect(understand('repeat', options, 'en')).toEqual({ kind: 'repeat' });
    expect(understand('next', options, 'en')).toEqual({ kind: 'skip' });
    expect(understand('stop', options, 'en')).toEqual({ kind: 'stop' });
    expect(understand('repetir', options, 'es')).toEqual({ kind: 'repeat' });
    expect(understand('siguiente', options, 'es')).toEqual({ kind: 'skip' });
    expect(understand('parar', options, 'es')).toEqual({ kind: 'stop' });
    expect(understand('', options, 'en')).toEqual({ kind: 'unclear' });
    expect(understand('percent', ['At 78 percent', 'At 90 percent', 'Never', 'Ask'], 'en')).toEqual({ kind: 'unclear' });
    expect(understand('ninety percent', ['At 78 percent', 'At 90 percent', 'Never', 'Ask'], 'en')).toEqual({ kind: 'answer', choice: 1 });
    expect(understand('setenta y ocho por ciento', ['Al 78 por ciento', 'Al 90 por ciento', 'Nunca', 'Pregunte'], 'es')).toEqual({ kind: 'answer', choice: 0 });
  });

  it('reads the question with its options, and the feedback with why', () => {
    expect(questionText({ n: 1, total: 2, prompt: 'What does IABS stand for?', options: ['Information About Brokerage Services', 'Insurance'] }, t)).toBe(
      'Question 1 of 2. What does IABS stand for? A: Information About Brokerage Services. B: Insurance. Your answer?',
    );
    expect(feedbackText({ correct: false, right: 0, explanation: 'It explains representation.' }, ['Information About Brokerage Services', 'Insurance'], t)).toBe(
      'Not quite. The answer is A: Information About Brokerage Services. It explains representation.',
    );
  });
});

describe('hands-free mode (screen 51)', () => {
  it('reads a drill aloud, takes a spoken answer, says why, and works from the lock screen', async () => {
    mockApi.POST.mockImplementation((path: string) => Promise.resolve(ok({ data: path === '/practice/tests' ? drill : { id: 14, correct: 1, total: 2 } })));
    mockApi.PUT.mockResolvedValue(ok({ data: { ...drill.questions[0], choice: 0, feedback: { correct: true, right: 0, explanation: 'At 78 percent, by law.', source: 'HPA' } } }));
    await render(wrap(<HandsFreeScreen />));

    expect(screen.getByText('Not for driving')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Start the voice quiz' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'drill', topic: null, mode: 'practice' } });
    expect(audio.__playlist.setActiveForLockScreen).toHaveBeenCalledWith(true, { title: 'Hands-free mode', artist: 'REOS' }, expect.objectContaining({ showNextTrack: true }));
    expect(voice.__spoken[0]).toContain('Question 1 of 2. When must borrower-paid PMI end automatically?');

    await flush();
    expect(speech.ExpoSpeechRecognitionModule.start).toHaveBeenCalledWith(expect.objectContaining({ lang: 'en-US', requiresOnDeviceRecognition: true }));
    expect(screen.getByText('Listening…')).toBeTruthy();

    await act(async () => speech.__emit('result', { isFinal: true, results: [{ transcript: 'A' }] }));
    expect(mockApi.PUT).toHaveBeenCalledWith('/practice/attempts/{attempt}/answers/{question}', { params: { path: { attempt: 14, question: 101 } }, body: { choice: 0, flagged: false } });
    expect(voice.__spoken).toContain('Right. At 78 percent, by law.');

    // Feedback done: the next question. Headphones' next skips it, which ends the drill.
    await flush();
    expect(voice.__spoken.at(-1)).toContain('Question 2 of 2.');
    await act(async () => audio.__emit('trackChanged', { previousIndex: 0, currentIndex: 1 }));
    expect(audio.__playlist.skipTo).toHaveBeenCalledWith(0);
    expect(await screen.findByText('1 of 2 right')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/attempts/{attempt}/submit', { params: { path: { attempt: 14 } } });

    await fireEvent.press(screen.getByRole('button', { name: 'See results' }));
    expect(router.replace).toHaveBeenCalledWith({ pathname: '/practice/results/[id]', params: { id: '14' } });
    await flush();
  });

  it('repeats on a headphone pause, and answers by tap without on-device speech', async () => {
    speech.ExpoSpeechRecognitionModule.start.mockClear();
    const module = jest.requireMock<{ ExpoSpeechRecognitionModule: { supportsOnDeviceRecognition: jest.Mock } }>('expo-speech-recognition').ExpoSpeechRecognitionModule;
    module.supportsOnDeviceRecognition.mockReturnValueOnce(false);
    mockApi.POST.mockResolvedValue(ok({ data: drill }));
    mockApi.PUT.mockResolvedValue(ok({ data: { ...drill.questions[0], choice: 2, feedback: { correct: false, right: 0, explanation: 'By law.', source: 'HPA' } } }));
    await render(wrap(<HandsFreeScreen />));
    await fireEvent.press(screen.getByRole('button', { name: 'Start the voice quiz' }));
    await flush();

    expect(screen.getByText(/can't recognize speech on the device/)).toBeTruthy();
    expect(speech.ExpoSpeechRecognitionModule.start).not.toHaveBeenCalled();

    await act(async () => {
      audio.__emit('playlistStatusUpdate', { playing: true });
      audio.__emit('playlistStatusUpdate', { playing: false });
    });
    expect(audio.__playlist.play).toHaveBeenCalled();
    expect(voice.__spoken.filter((s) => s.startsWith('Question 1 of 2.'))).toHaveLength(2);

    await flush();
    await fireEvent.press(screen.getByRole('button', { name: 'C. Never' }));
    expect(voice.__spoken).toContain('Not quite. The answer is A: At a scheduled 78 percent loan-to-value. By law.');
  });
});

describe('"Hey Siri, quiz me with REOS" and the Android App Action', () => {
  const plugin = jest.requireActual<{ swift: () => string; shortcutsXml: (pkg: string) => string }>('../../plugins/with-quiz-shortcut');

  it('opens the hands-free quiz, and is not a driving feature', () => {
    expect(plugin.swift()).toContain('"Quiz me with \\(.applicationName)"');
    expect(plugin.swift()).toContain('reos://practice/hands-free');
    expect(plugin.shortcutsXml('com.reos.app')).toContain('actions.intent.OPEN_APP_FEATURE');
    expect(plugin.shortcutsXml('com.reos.app')).toContain('reos://practice/hands-free{?feature}');
    expect(`${plugin.swift()}${plugin.shortcutsXml('com.reos.app')}`).not.toMatch(/carplay|android\.auto|driving mode/i);
  });
});
