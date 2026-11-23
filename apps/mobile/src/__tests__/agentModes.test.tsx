import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import AgentSetupScreen from '@/app/agent/setup';
import MyGoalScreen from '@/app/agent/goal';
import { ModeCard } from '@/features/agent/ModeCard';
import { i18n } from '@/lib/i18n';
import type { ModeState } from '@/lib/agent';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const mode = (over: Partial<ModeState> = {}): ModeState => ({
  goal: null,
  flags: [],
  needs_setup: true,
  suggested_default: 'launch',
  own: { unlocked: false, unlocks_on: '2030-04-01', years: 4 },
  suggestion: null,
  history: [],
  welcome: { name: 'James Miller', licence: '0798765', state: 'TX', brokerage: 'Linda Park', verified: true },
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('Agent modes on the phone (A11.1a: screens 4, 5, 6, 146)', () => {
  it('welcomes, sets the goal and flags, then goes to Today', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: mode() }));
    mockApi.PUT.mockResolvedValue(ok({ data: mode({ goal: 'launch', needs_setup: false }) }));
    await render(wrap(<AgentSetupScreen />));

    expect(await screen.findByText('Welcome, James Miller')).toBeTruthy();
    expect(screen.getByText('TX license #0798765')).toBeTruthy();
    await fireEvent.press(screen.getByText('Get started'));
    expect(await screen.findByText(/Unlocks/)).toBeTruthy();
    fireEvent.press(screen.getByText('Continue'));
    expect(await screen.findByText('Part-time')).toBeTruthy();
    expect(mockApi.PUT).toHaveBeenCalledWith('/agent/mode', { body: { goal: 'launch', reason: 'setup' } });

    await fireEvent(screen.getByLabelText('Bilingual'), 'valueChange', true);
    fireEvent.press(screen.getByText('Build my home screen'));
    await screen.findByText('Build my home screen');
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/agent/mode/flags', { body: { flags: ['bilingual'] } });
  });

  it('offers a suggested switch on Today, and "Not yet"', async () => {
    const onChange = jest.fn();
    mockApi.POST.mockResolvedValue(ok({ data: mode({ goal: 'launch', needs_setup: false }) }));
    await render(wrap(<ModeCard mode={mode({ goal: 'launch', needs_setup: false, suggestion: { goal: 'grow', closings: 1 } })} onChange={onChange} />));

    expect(screen.getByText('Launch mode')).toBeTruthy();
    expect(screen.getByText('Ready for Grow?')).toBeTruthy();
    fireEvent.press(screen.getByText('Not yet'));
    await screen.findByText('Ready for Grow?');
    expect(mockApi.POST).toHaveBeenCalledWith('/agent/mode/snooze');
    fireEvent.press(screen.getByText('Launch mode'));
    expect(router.push).toHaveBeenCalledWith('/agent/goal');
  });

  it('changes the goal by hand in My goal, with history', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: mode({ goal: 'launch', needs_setup: false, history: [{ from: null, to: 'launch', reason: 'setup', at: '2026-11-20T15:00:00Z' }] }) }));
    mockApi.PUT.mockResolvedValue(ok({ data: mode({ goal: 'grow', needs_setup: false }) }));
    await render(wrap(<MyGoalScreen />));

    expect(await screen.findByText('Started as Launch')).toBeTruthy();
    fireEvent.press(screen.getByText('Close more deals'));
    expect(await screen.findByText('Saved')).toBeTruthy();
    expect(mockApi.PUT).toHaveBeenCalledWith('/agent/mode', { body: { goal: 'grow', reason: 'manual' } });
  });
});
