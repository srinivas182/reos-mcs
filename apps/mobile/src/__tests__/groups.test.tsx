import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import GroupInviteScreen from '@/app/groups/[uuid]';
import GroupsScreen from '@/app/practice/groups';
import { i18n } from '@/lib/i18n';
import type { StudyGroup } from '@/lib/practice';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const group: StudyGroup = {
  id: 3,
  uuid: '0b6d5b8e-7f43-4c1e-a2f1-5d3e9c8b7a61',
  name: 'Night owls',
  link: 'https://reos.com/groups/0b6d5b8e-7f43-4c1e-a2f1-5d3e9c8b7a61',
  school: null,
  members: 2,
  max: 20,
  mine: true,
  leaderboard: [
    { first_name: 'Marco', questions: 64, accuracy: 81, mine: false },
    { first_name: 'Sofia', questions: 42, accuracy: 74, mine: true },
  ],
  week_start: '2026-11-23',
};

beforeEach(() => jest.clearAllMocks());

describe('study groups (screen 52, A9.3c)', () => {
  it('turns study groups on, shows the weekly leaderboard by first name, and challenges the group', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { opt_in: false, groups: [] } }));
    mockApi.PUT.mockResolvedValue(ok({ data: { opt_in: true, groups: [group] } }));
    mockApi.POST.mockResolvedValue(ok({ data: { uuid: 'c1', attempt_id: 90 } }));
    await render(wrap(<GroupsScreen />));

    expect(await screen.findByText('Study groups are off. Turn them on to start or join one.')).toBeTruthy();
    await fireEvent(screen.getByRole('switch'), 'valueChange', true);
    expect(mockApi.PUT).toHaveBeenCalledWith('/practice/groups/opt-in', { body: { opt_in: true } });
    expect(await screen.findByText('1. Marco')).toBeTruthy();
    expect(screen.getByText('64 questions · 81% right')).toBeTruthy();
    expect(screen.getByText('2. Sofia (you)')).toBeTruthy();

    await fireEvent.press(screen.getByRole('button', { name: 'Challenge the group' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/groups/{group}/challenge', { params: { path: { group: group.uuid } } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '90' } });
  });

  it('joins from an invite link, or says to turn study groups on first', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ uuid: group.uuid });
    mockApi.GET.mockResolvedValue(ok({ data: { ...group, mine: false, leaderboard: [] } }));
    mockApi.POST.mockResolvedValueOnce({ data: undefined, error: { message: 'Turn on study groups in your Profile first.' }, response: { ok: false, status: 422 } }).mockResolvedValueOnce(ok({ data: group }));
    await render(wrap(<GroupInviteScreen />));

    expect(await screen.findByText('Night owls has 2 members. Join to see the weekly leaderboard.')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Join the group' }));
    expect(await screen.findByText('Turn on study groups in your Profile first.')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Join the group' }));
    expect(router.replace).toHaveBeenCalledWith('/practice/groups');
  });
});
