import { fireEvent, render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import EarningsScreen from '@/app/earnings';
import { groupLines } from '@/lib/earnings';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { router: { back: jest.fn(), push: jest.fn() }, useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const line = (id: number, product: string, amount: number, status = 'pending', date = '2026-11-03') => ({ id, product, role: 'instructor' as const, amount, status: status as 'pending', date });
const earnings = (overrides = {}) => ({
  month: '2026-11',
  net_this_month: 10772,
  fee_percent: 10,
  pending: 10772,
  next_payout: '2026-11-23',
  account: { kind: 'express', state: 'ready', requirements: 0 },
  lines: [line(1, 'Texas Pre-licence Complete', 5386), line(2, 'Texas Pre-licence Complete', 5386), line(3, 'Texas Exam Prep', 900, 'cancelled')],
  payouts: [],
  ...overrides,
});
const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => jest.clearAllMocks());

describe('Earnings (instructor screen 20, mentor screen 28)', () => {
  it('shows net this month, the next payout and shares grouped by product; a tap shows each sale', async () => {
    mockApi.GET.mockResolvedValue({ data: earnings() });
    await wrap(<EarningsScreen />);

    expect(await screen.findAllByText('$107.72')).toHaveLength(2);
    expect(screen.getByText(/Next payout Mon · \$107\.72 · Stripe/)).toBeTruthy();
    expect(screen.getByText('Texas Pre-licence Complete (2)')).toBeTruthy();
    // Refunded shares don't count.
    expect(screen.queryByText(/Texas Exam Prep/)).toBeNull();
    expect(screen.getByText(/managed on Stripe from the web/)).toBeTruthy();

    await fireEvent.press(screen.getByRole('button', { name: /Texas Pre-licence Complete \(2\)/ }));
    expect(screen.getAllByText('Instructor share')).toHaveLength(2);
  });

  it('says payouts need setting up on the web when Stripe is not ready', async () => {
    mockApi.GET.mockResolvedValue({ data: earnings({ account: null, lines: [], net_this_month: 0 }) });
    await wrap(<EarningsScreen />);

    expect(await screen.findByText(/set up payouts on Stripe from the web/)).toBeTruthy();
    expect(screen.getByText('No earnings this month yet')).toBeTruthy();
  });

  it('groups only this month, biggest first', () => {
    const groups = groupLines([line(1, 'A', 100), line(2, 'B', 500), line(3, 'B', 100, 'paid'), line(4, 'C', 900, 'pending', '2026-10-30')], '2026-11');

    expect(groups.map((g) => [g.product, g.count, g.amount])).toEqual([['B', 2, 600], ['A', 1, 100]]);
  });
});
