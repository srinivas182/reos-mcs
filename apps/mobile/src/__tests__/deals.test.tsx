import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import DealScreen from '@/app/deals/[uuid]';
import DealAlertsScreen from '@/app/deals/alerts';
import PipelineScreen from '@/app/deals/index';
import type { Deal, DealCard } from '@/lib/deals';
import { i18n } from '@/lib/i18n';
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
const elm: DealCard = {
  id: 1,
  uuid: '9f0c1e7a-2d4b-4c7e-9a51-3b6f0e8d2c11',
  property: '1418 Elm St',
  city: 'Austin',
  side: 'buy',
  stage: 'under_contract',
  price: 425000,
  commission: '12750.00',
  source: 'reos',
  read_only: false,
  external_url: null,
  next: { id: 11, kind: 'option_period', name: 'Option period ends', due_on: '2026-11-26', days: 3, done: false },
  documents: { complete: 0, total: 0, missing: 0 },
};
const oak: Deal = {
  ...elm,
  id: 2,
  uuid: '4b1d2e3f-5a6b-4c7d-8e9f-0a1b2c3d4e5f',
  property: '9 Oak Ct',
  side: 'sell',
  source: 'lone-wolf',
  read_only: true,
  external_url: 'https://transact.lwolf.com/transactions/9',
  documents: { complete: 1, total: 2, missing: 1 },
  parties: [{ name: 'Dan Whitfield', role: 'Seller', email: null, phone: null }],
  files: [
    { name: 'Contract', status: 'complete', url: null },
    { name: 'Seller disclosure', status: 'missing', url: null },
  ],
  dates: [{ id: 21, kind: 'financing', name: 'Financing deadline', due_on: '2026-12-01', days: 8, done: false }],
  kept_files: [],
  signatures: [],
  can_sign: false,
};

beforeEach(() => jest.clearAllMocks());

describe('deals on the phone (Agent App screens 9 and 10, B9.1)', () => {
  it('shows what is under contract, active clients and pending commission', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { under_contract: 2, active: 1, pending_commission: '31050.00', closed_this_year: 4, deals: [elm] } }));
    await render(wrap(<PipelineScreen />));

    expect(await screen.findByText('$31,050')).toBeTruthy();
    expect(screen.getByText('Under contract')).toBeTruthy();
    await fireEvent.press(screen.getByText('1418 Elm St'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/deals/[uuid]', params: { uuid: elm.uuid } });
    await fireEvent.press(screen.getByText('Deal alerts'));
    expect(router.push).toHaveBeenCalledWith('/deals/alerts');
  });

  it('lists deadlines in the next two weeks and missing documents', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { deadlines: [{ deal: elm, date: elm.next }], missing: [{ deal: oak, documents: ['Seller disclosure'] }] } }));
    await render(wrap(<DealAlertsScreen />));

    expect(await screen.findByText('Option period ends · in 3 days')).toBeTruthy();
    expect(screen.getByText('Seller disclosure')).toBeTruthy();
  });

  it('keeps a deal from a tool read-only and opens it there', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ uuid: oak.uuid });
    mockApi.GET.mockResolvedValue(ok({ data: oak }));
    await render(wrap(<DealScreen />));

    expect(await screen.findByText('Read only: changes are made in Lone Wolf.')).toBeTruthy();
    expect(screen.getByText('Missing')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Open in Lone Wolf' }));
    expect(jest.requireMock<{ openBrowserAsync: jest.Mock }>('expo-web-browser').openBrowserAsync).toHaveBeenCalledWith(oak.external_url);
  });

  it("shows each envelope's signers and opens the signed copy (B9.3)", async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ uuid: oak.uuid });
    mockApi.GET.mockResolvedValue(
      ok({
        data: {
          ...oak,
          signatures: [
            {
              uuid: '2a4b6c8d-0e1f-4a2b-8c3d-4e5f6a7b8c9d',
              subject: "Seller's Disclosure",
              status: 'completed',
              documents: ["Seller's Disclosure.pdf"],
              signers: [{ name: 'Dan Whitfield', email: 'dan@example.com', routing_order: 1, status: 'signed', at: '2026-11-22T16:00:00Z' }],
              sent_at: '2026-11-22T14:00:00Z',
              completed_at: '2026-11-22T16:00:00Z',
              filed_in: 'lone-wolf',
            },
          ],
          kept_files: [{ uuid: '0b8e6d1c-4f2a-4e5b-9c3d-7a1f2e3d4c5b', kind: 'signed', name: "Seller's Disclosure (signed).pdf", size: 182400, added_at: null, scan: 'clean', url: 'https://reos.com/deals/files/0b8e?signature=x' }],
        },
      }),
    );
    await render(wrap(<DealScreen />));

    expect(await screen.findByText('Signed by everyone')).toBeTruthy();
    expect(screen.getByText('Dan Whitfield · Signed')).toBeTruthy();
    expect(screen.getByText('Also filed in Lone Wolf')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: "Seller's Disclosure (signed).pdf" }));
    expect(jest.requireMock<{ openBrowserAsync: jest.Mock }>('expo-web-browser').openBrowserAsync).toHaveBeenCalledWith('https://reos.com/deals/files/0b8e?signature=x');
  });
});

describe('Connections › Transactions (screen 17, B9.1b)', () => {
  it('lists deals from the tools with stage, documents complete, next deadline and commission', async () => {
    const TransactionsScreen = jest.requireActual<{ default: () => React.JSX.Element }>('@/app/connections/transactions').default;
    mockApi.GET.mockResolvedValue(ok({ data: [oak] }));
    await render(wrap(<TransactionsScreen />));

    expect(await screen.findByText('9 Oak Ct')).toBeTruthy();
    expect(screen.getByText(/Under contract · 1 of 2 documents/)).toBeTruthy();
    expect(screen.getByText('Read only: changes are made in the transaction tool.')).toBeTruthy();
  });
});
