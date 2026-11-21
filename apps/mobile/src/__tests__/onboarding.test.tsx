import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as Notifications from 'expo-notifications';
import { I18nextProvider } from 'react-i18next';

import QuizScreen from '@/app/(onboarding)/quiz';
import ScoreScreen from '@/app/(onboarding)/score';
import SetupScreen from '@/app/(onboarding)/setup';
import StartScreen from '@/app/(onboarding)/start';
import type { Me } from '@/lib/auth';
import { i18n } from '@/lib/i18n';
import { attachDraft, clearDraft, getDraft, hasSignedInBefore, markSignedIn, topicLevels, updateDraft } from '@/lib/onboarding';
import { needsSetup } from '@/lib/useAuthGate';
import { ToastProvider } from '@/ui';

const mockRouter = { push: jest.fn(), replace: jest.fn(), back: jest.fn() };
const mockClient = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn() };
const mockAuth = { status: 'signedOut' as string, refresh: jest.fn(), signInWithApple: jest.fn(), signInWithBrowser: jest.fn() };

jest.mock('expo-router', () => ({
  get router() {
    return mockRouter;
  },
  useLocalSearchParams: () => ({}),
}));
jest.mock('@/lib/api', () => ({ api: () => mockClient, webBaseUrl: () => 'http://localhost:8010' }));
jest.mock('@/lib/auth', () => ({ useAuth: () => mockAuth }));
jest.mock('@/lib/monitoring', () => ({ appConfig: () => Promise.resolve({ sign_in: { apple: false, google: true, phone: true, demo: false } }) }));

const questions = [
  { id: 1, topic: 'agency', question: 'Q1?', options: ['A1', 'B1', 'C1', 'D1'], correct_option: 1, explanation: 'Because one.', source: 'Outline · Agency', sample: true },
  { id: 2, topic: 'math', question: 'Q2?', options: ['A2', 'B2', 'C2', 'D2'], correct_option: 0, explanation: 'Because two.', source: 'Outline · Math', sample: true },
];

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  jest.clearAllMocks();
  clearDraft();
  mockAuth.status = 'signedOut';
});

describe('the draft kept on the phone', () => {
  it('scores topics the same way as the server', () => {
    expect(
      topicLevels([
        { topic: 'agency', correct: true },
        { topic: 'agency', correct: true },
        { topic: 'math', correct: true },
        { topic: 'math', correct: false },
        { topic: 'finance', correct: false },
      ]),
    ).toEqual([
      { topic: 'agency', level: 'strong' },
      { topic: 'math', level: 'learning' },
      { topic: 'finance', level: 'start_here' },
    ]);
  });

  it('saves path, state and answers to the new account, then forgets them', async () => {
    updateDraft({ path: 'become_agent', state: 'TX', answers: [{ question_id: 1, choice: 1 }] });
    mockClient.PUT.mockResolvedValue({});
    mockClient.POST.mockResolvedValue({});

    await expect(attachDraft(mockClient as never)).resolves.toBe(true);
    expect(mockClient.PUT).toHaveBeenCalledWith('/me/onboarding', { body: { path: 'become_agent', state: 'TX' } });
    expect(mockClient.POST).toHaveBeenCalledWith('/me/quiz-attempts', { body: { state: 'TX', answers: [{ question_id: 1, choice: 1 }] } });
    expect(getDraft()).toEqual({});
    await expect(attachDraft(mockClient as never)).resolves.toBe(false);
  });

  it('remembers that someone has signed in on this phone', () => {
    markSignedIn();
    expect(hasSignedInBefore()).toBe(true);
  });

  it('sends a new candidate to the 60-second setup', () => {
    const me = (onboarding: Partial<Me['onboarding']>) => ({ onboarding: { completed: false, path: 'become_agent', ...onboarding } }) as Me;

    expect(needsSetup(me({}))).toBe(true);
    expect(needsSetup(me({ completed: true }))).toBe(false);
    expect(needsSetup(me({ path: 'licensed_agent' }))).toBe(false);
    expect(needsSetup(null)).toBe(false);
  });
});

describe('Choose your path (journey screen 1)', () => {
  it('shows the five paths and starts the candidate path', async () => {
    await wrap(<StartScreen />);

    expect(screen.getByRole('header', { name: 'REOS' })).toBeOnTheScreen();
    for (const title of ['I want to become an agent', "I'm a licensed agent", 'I lead a team or brokerage', "I'm an instructor or coach", "I'm a service provider"]) {
      expect(screen.getByRole('button', { name: new RegExp(title) })).toBeOnTheScreen();
    }

    await fireEvent.press(screen.getByRole('button', { name: /I want to become an agent/ }));
    expect(getDraft().path).toBe('become_agent');
    expect(mockRouter.push).toHaveBeenCalledWith('/state');
  });

  it('sends brokers to reos.com', async () => {
    await wrap(<StartScreen />);
    await fireEvent.press(screen.getByRole('button', { name: /I lead a team or brokerage/ }));
    expect(screen.getByText('Set up your team on reos.com')).toBeOnTheScreen();
  });
});

describe('Free quiz (journey screen 6) and score (screen 7)', () => {
  it('explains each answer, then keeps the answers for the score', async () => {
    mockClient.GET.mockResolvedValue({ data: { data: questions } });
    await wrap(<QuizScreen />);

    expect(await screen.findByText('Q1?')).toBeOnTheScreen();
    expect(screen.getByText('1 of 2')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('radio', { name: /B1/ }));
    expect(screen.getByText('Correct, nicely done.')).toBeOnTheScreen();
    expect(screen.getByText(/Because one\./)).toBeOnTheScreen();
    expect(screen.getByText(/Source: Outline · Agency/)).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Next question' }));
    await fireEvent.press(screen.getByRole('radio', { name: /C2/ }));
    expect(screen.getByText('Not quite.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'See my score' }));

    expect(getDraft().answers).toEqual([
      { question_id: 1, choice: 1 },
      { question_id: 2, choice: 2 },
    ]);
    expect(mockRouter.replace).toHaveBeenCalledWith('/score');
  });

  it('shows the score, the topics and one-tap sign-up', async () => {
    updateDraft({ results: [{ topic: 'agency', correct: true }, { topic: 'math', correct: false }] });
    await wrap(<ScoreScreen />);

    expect(screen.getByText('50%')).toBeOnTheScreen();
    expect(screen.getByText("You're off to a strong start.")).toBeOnTheScreen();
    expect(screen.getByText('Strong')).toBeOnTheScreen();
    expect(screen.getByText('Start here')).toBeOnTheScreen();
    expect(await screen.findByRole('button', { name: 'Continue with Google' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Continue with phone' })).toBeOnTheScreen();
  });
});

describe('60-second setup (journey screen 8)', () => {
  it('saves the choices, schedules the reminder and shows the plan', async () => {
    mockAuth.status = 'signedIn';
    mockClient.PUT.mockResolvedValue({ response: { ok: true } });
    await wrap(<SetupScreen />);

    await fireEvent.press(screen.getByRole('radio', { name: '45 min' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Build my plan' }));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/plan'));
    expect(mockClient.PUT).toHaveBeenCalledWith('/me/onboarding', {
      body: expect.objectContaining({ minutes_per_day: 45, reminder_time: '19:30', complete: true }),
    });
    expect(Notifications.requestPermissionsAsync).toHaveBeenCalled();
    expect(Notifications.scheduleNotificationAsync).toHaveBeenCalledWith(
      expect.objectContaining({ trigger: expect.objectContaining({ hour: 19, minute: 30 }) }),
    );
  });

  it('asks for no permission without a reminder', async () => {
    mockAuth.status = 'signedIn';
    mockClient.PUT.mockResolvedValue({ response: { ok: true } });
    await wrap(<SetupScreen />);

    await fireEvent.press(screen.getByRole('button', { name: /Remind me/ }));
    await fireEvent.press(screen.getByRole('radio', { name: 'No reminder' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Build my plan' }));

    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/plan'));
    expect(Notifications.requestPermissionsAsync).not.toHaveBeenCalled();
  });
});
