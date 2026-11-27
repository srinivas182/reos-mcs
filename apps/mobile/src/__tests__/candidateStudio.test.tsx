import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import CandidateStudioScreen from '@/app/social/candidate';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };

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

const made = {
  id: 'c-1',
  milestone: 'exam_pass',
  label: 'Texas real estate exam',
  captions: [
    { tone: 'professional', text: 'I passed the Texas real estate exam! #FutureAgent' },
    { tone: 'warm', text: 'I did it! #FutureAgent' },
    { tone: 'short', text: 'Passed! #FutureAgent' },
  ],
  designs: [{ layout: 'band', path: 'a.png', url: 'https://reos.test/a.png' }],
  checks: [{ caption: 0, ok: true, issues: [] }],
};

beforeEach(() => jest.clearAllMocks());

describe('The candidate studio on the phone (B13.3)', () => {
  it('makes a pass post, blocks service advertising, and shares a clean caption', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction', activityType: undefined });
    mockApi.GET.mockImplementation(async (path: string) => (path === '/candidate-studio' ? ok({ data: [{ milestone: 'exam_pass', label: 'Texas real estate exam', made: null }] }) : ok({ data: [] })));
    mockApi.POST.mockImplementation(async (path: string, init: { body?: { text?: string } }) => {
      if (path === '/candidate-studio/moments/{milestone}') return ok({ data: made });
      const bad = (init.body?.text ?? '').includes('Call me');

      return ok({ data: bad ? { ok: false, issues: [{ code: 'unlicensed', severity: 'block', phrase: 'call me', fix: '', reason: null, citation: 'Tex. Occ. Code §1101.351', url: '', source: 'rule' }] } : { ok: true, issues: [] } });
    });
    await render(wrap(<CandidateStudioScreen />));

    await fireEvent.press(await screen.findByText('Texas real estate exam'));
    const box = await screen.findByLabelText('Caption');
    await fireEvent.changeText(box, 'Passed! Call me to sell your home.');
    await fireEvent.press(screen.getByLabelText('Check'));
    expect(await screen.findByText('"call me" offers real estate services before your license is active (Tex. Occ. Code §1101.351).')).toBeTruthy();

    await fireEvent.changeText(box, 'I passed the Texas real estate exam! #FutureAgent');
    await fireEvent.press(screen.getByLabelText('Share'));
    await waitFor(() => expect(share).toHaveBeenCalledWith({ message: 'I passed the Texas real estate exam! #FutureAgent', url: 'https://reos.test/a.png' }));
  });
});
