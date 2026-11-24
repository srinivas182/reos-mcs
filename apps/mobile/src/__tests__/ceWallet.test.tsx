import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import CeWalletScreen from '@/app/ce';
import CompletionScreen from '@/app/ce/completion/[id]';
import RenewScreen from '@/app/ce/renew/[id]';
import type { Wallet } from '@/lib/ce';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => ({ id: '1' }),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const wallet = (over: Partial<Wallet> = {}): Wallet => ({
  licence: { id: 1, state_code: 'TX', state_name: 'Texas', number: '0798765', type: 'sales_agent', status: 'active', expires_on: '2027-01-15', sponsor_name: 'Linda Park' },
  ce: {
    state: 'TX', due_on: '2027-01-15', period_start: '2025-01-15', days_left: 52, first_renewal: false, hours_required: 18, hours_done: 7, hours_left: 11, complete: false,
    parts: [{ code: 'legal_update_1', title: 'Legal Update I', kind: 'mandatory', hours: 4, hours_done: 4, done: true }], deferral: null, sources: [],
  },
  completions: [{ id: 2, title: 'Negotiation essentials', hours: 3, completed_on: '2026-10-02', provider: 'Champions School', course_code: null, status: 'self_reported', certificate: true, reporting: null }],
  trec: { mce: 'outstanding', education: 'none', checked_at: '2026-11-24T11:30:00Z' },
  renewal: {
    due_on: '2027-01-15', ready: false, renewed_at: null, confirmed_at: null, renewed_until: null,
    steps: [
      { code: 'ce_reported', title: 'CE reported', detail: 'Finish CE 10 days before.', url: 'https://trec.texas.gov/', done: false },
      { code: 'renew', title: 'Renew with TREC', detail: 'Renew in REALM.', url: 'https://aca-prod.accela.com/TREC/Default.aspx', done: false },
    ],
  },
  buy: { price: 7, product: 'Agent Core', amount: 2900, interval: 'month' },
  unlocked: false,
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('The CE wallet on the phone (A11.2: screens 103, 105, 106)', () => {
  it('shows hours, who reported each completion, and where to get CE without a price', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [wallet()] }));
    await render(wrap(<CeWalletScreen />));

    expect(await screen.findByText('7 of 18 hours')).toBeTruthy();
    expect(screen.getByText('Self-reported')).toBeTruthy();
    expect(screen.getByText('TREC shows CE still outstanding.')).toBeTruthy();
    expect(screen.queryByText(/\$29/)).toBeNull();
    await fireEvent.press(screen.getByText('Get CE courses'));
    expect(router.push).toHaveBeenCalledWith('/membership');
    await fireEvent.press(screen.getByText('Add a certificate'));
    expect(router.push).toHaveBeenCalledWith('/licences/certificate');
  });

  it('shows a REOS course\'s steps to TREC and shares the certificate once reported (screens 103, 104)', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction', activityType: undefined });
    mockApi.GET.mockResolvedValue(
      ok({
        data: {
          id: 1, holder: 'James Miller', licence: '0798765', state_code: 'TX', title: 'Legal Update I', hours: 4, completed_on: '2026-03-01', provider: 'Texas Realty School', course_number: '40123',
          status: 'reos_reported', issued: true,
          reporting: {
            status: 'reported', reference: 'CE-2026-118844', reason: null,
            steps: [
              { code: 'completed', state: 'done' }, { code: 'final_passed', state: 'done' }, { code: 'reported', state: 'done' }, { code: 'member_record', state: 'not_connected' }, { code: 'certificate', state: 'done' },
            ],
          },
        },
      }),
    );
    await render(wrap(<CompletionScreen />));

    expect(await screen.findByText('Reporting to TREC: Reported to TREC')).toBeTruthy();
    expect(screen.getByText('Done · TREC reference CE-2026-118844')).toBeTruthy();
    expect(screen.getByText('Not connected')).toBeTruthy();
    await fireEvent.press(screen.getByText('Share'));
    expect(share).toHaveBeenCalledWith({ message: expect.stringContaining('James Miller completed Legal Update I (4 CE hours)') });
  });

  it('walks the renewal checklist and records "I\'ve renewed"', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [wallet()] }));
    mockApi.PUT.mockResolvedValue(ok({ data: wallet({ renewal: { ...wallet().renewal, renewed_at: '2026-11-24T15:00:00Z' } }) }));
    await render(wrap(<RenewScreen />));

    expect(await screen.findByText('Renew with TREC (To do)')).toBeTruthy();
    await fireEvent.press(screen.getByText('I’ve renewed with TREC'));
    expect(mockApi.PUT).toHaveBeenCalledWith('/me/licences/{licence}/renewal', { params: { path: { licence: 1 } }, body: { renewed: true } });
    expect(await screen.findByText('Marked renewed')).toBeTruthy();
    expect(screen.getAllByText('Undo').length).toBeGreaterThan(0);
  });
});
