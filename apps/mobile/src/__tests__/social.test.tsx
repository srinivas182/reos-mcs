import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import PostsScreen from '@/app/social';
import SocialAccountsScreen from '@/app/social/accounts';
import NewPostScreen from '@/app/social/new';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn(), DELETE: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => ({}),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/connections', () => ({ connectTool: jest.fn(async () => ({ outcome: 'connected', reason: null, connectionId: 4 })) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const accounts = [
  {
    integration: 'meta', name: 'Instagram and Facebook', purpose: 'Post, schedule and read insights.', launch: 'enabled', built: true, available: true,
    connection: { id: 4, status: 'active', label: 'James Miller' },
    targets: [{ channel: 'facebook', id: 'p1', name: 'James Miller, REALTOR®' }, { channel: 'instagram', id: 'ig1', name: '@jamesmiller.homes' }],
    channels: { instagram: { media: 'required', max_text: 2200 }, facebook: { media: 'optional', max_text: 63206 } },
  },
  { integration: 'linkedin', name: 'LinkedIn', purpose: 'Post.', launch: 'beta', built: false, available: false, connection: null, targets: [], channels: {} },
];
const published = {
  id: 'p-1', body: 'Just listed: 1420 Pecan St', media: [], link: null, attribution: 'James Miller · Lone Star Realty', listing: null, status: 'published', scheduled_at: null,
  published_at: '2026-11-20T16:00:00Z', created_at: '2026-11-20T15:55:00Z',
  targets: [{ id: 1, integration: 'meta', channel: 'facebook', target_id: 'p1', name: 'James Miller, REALTOR®', status: 'published', error: null, url: null, published_at: '2026-11-20T16:00:00Z', insights: { reach: 1240 } }],
  totals: { reach: 1240 },
};
const checks = { attribution: 'James Miller · Lone Star Realty', checks: [{ code: 'broker', ok: true, detail: null }, { code: 'targets', ok: true, detail: null }], ok: true, approval: false, issues: [] };

beforeEach(() => jest.clearAllMocks());

describe('Social posting on the phone (B11.1: screens 108, 169, 170)', () => {
  it('shows my posts with results', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { stats: { posts_this_month: 1, reach: 1240, engagement: 20, clicks: 3 }, scheduled: [], posted: [published], drafts: [] } }));
    await render(wrap(<PostsScreen />));

    expect(await screen.findByText('Posted (1)')).toBeTruthy();
    expect(screen.getByText('Published')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('New post'));
    expect(router.push).toHaveBeenCalledWith('/social/new');
  });

  it('connects a social account and lists what it can post to', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [{ ...accounts[0], connection: null, targets: [] }, accounts[1]] }));
    await render(wrap(<SocialAccountsScreen />));

    expect(await screen.findByText('Coming soon')).toBeTruthy();
    await fireEvent.press(screen.getByText('Connect'));
    const { connectTool } = jest.requireMock<{ connectTool: jest.Mock }>('@/lib/connections');
    expect(connectTool).toHaveBeenCalledWith('meta');
  });

  it('checks the post and publishes it to the chosen account with the broker name', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: accounts }));
    mockApi.POST.mockImplementation(async (path: string) => ok({ data: path === '/social/posts/check' ? checks : published }));
    await render(wrap(<NewPostScreen />));

    await fireEvent.changeText(await screen.findByLabelText('Post text'), 'Just listed: 1420 Pecan St');
    await fireEvent(screen.getByLabelText('Facebook · James Miller, REALTOR®'), 'valueChange', true);
    expect(await screen.findByText('James Miller · Lone Star Realty')).toBeTruthy();
    await waitFor(() => expect(screen.getByText('Your broker’s name is on the post')).toBeTruthy());
    await fireEvent.press(screen.getByText('Publish now'));

    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/social/posts', {
        body: { body: 'Just listed: 1420 Pecan St', media: [], link: null, targets: [{ connection: 4, channel: 'facebook', id: 'p1' }], when: 'now', at: null, listing: null },
      }),
    );
    expect(router.replace).toHaveBeenCalledWith('/social');
  });
});
