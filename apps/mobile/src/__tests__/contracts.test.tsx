import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import ContractReviewScreen from '@/app/contracts/[id]';
import ContractAskScreen from '@/app/contracts/ask/[id]';
import ContractsScreen from '@/app/contracts';
import type { ContractReview } from '@/lib/contracts';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: () => ({ id: 'r-1' }),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('expo-document-picker', () => ({ getDocumentAsync: jest.fn() }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const review = (over: Partial<ContractReview> = {}): ContractReview => ({
  id: 'r-1', file_name: 'Pecan contract.pdf', form: '20-18', form_name: 'One to Four Family Residential Contract (Resale), TREC 20-18', status: 'done', step: 'done', pages: 5,
  created_at: '2026-11-24T15:00:00Z', reviewed_at: '2026-11-24T15:00:30Z', deal: { id: 7, uuid: 'd-1', property: '1420 Pecan St, Austin' }, compared: true,
  terms: [{ code: 'sales_price', label: 'Sales price', value: '435,000.00', page: 1, previous: '440,000.00', changed: true }],
  addenda: ['third_party_financing'],
  findings: [{ code: 'weekend', severity: 'check', title: 'Closing falls on a Saturday', detail: 'Confirm with your broker and the title company how this date is counted.', page: 3, date: '2027-01-09', citation: null, url: null }],
  deadlines: [{ code: 'option_period', kind: 'option_period', label: 'Option period ends (5:00 p.m.)', due_on: '2026-12-01', page: 2, basis: '7 days after the Effective Date' }],
  deadlines_added: false, broker_question: null, questions: [], credits: 20, cost: 20, error: null,
  ...over,
});

beforeEach(() => jest.clearAllMocks());

describe('Contract review on the phone (A12.2: screens 63–66)', () => {
  it('shows the cost first and starts the sample review', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { reviews: [], cost: 20, credits_left: 480, sample: true, files: [] } }));
    mockApi.POST.mockResolvedValue(ok({ data: review({ status: 'reviewing', step: 'read' }) }));
    await render(wrap(<ContractsScreen />));

    expect(await screen.findByText('20 credits per review · 480 left')).toBeTruthy();
    expect(screen.getByText('No reviews yet')).toBeTruthy();
    await fireEvent.press(screen.getByText('Try the sample TREC 20-18'));
    expect(mockApi.POST).toHaveBeenCalledWith('/contracts', expect.objectContaining({ body: {} }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/contracts/[id]', params: { id: 'r-1' } });
  });

  it('shows the steps while reviewing', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: review({ status: 'reviewing', step: 'checks' }) }));
    await render(wrap(<ContractReviewScreen />));

    expect(await screen.findByText('Checking common problems')).toBeTruthy();
    expect(screen.queryByText('Findings')).toBeNull();
  });

  it('shows dated findings, deadlines and changes, and adds the deadlines', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: review() }));
    mockApi.POST.mockResolvedValue(ok({ data: review({ deadlines_added: true }) }));
    await render(wrap(<ContractReviewScreen />));

    expect(await screen.findByText('Closing falls on a Saturday')).toBeTruthy();
    expect(screen.getByText(/Jan 9, 2027 · p\. 3/)).toBeTruthy();
    expect(screen.getByText('Changed · was 440,000.00')).toBeTruthy();
    await fireEvent.press(screen.getByText('Add deadlines to my calendar'));
    expect(mockApi.POST).toHaveBeenCalledWith('/contracts/{review}/deadlines', { params: { path: { review: 'r-1' } } });
    expect(await screen.findByText('Deadlines added')).toBeTruthy();
  });

  it('answers a question with pages and shares the note for the client', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction', activityType: undefined });
    mockApi.GET.mockResolvedValue(ok({ data: review() }));
    mockApi.POST.mockResolvedValue(ok({ data: review({ questions: [{ question: 'When does the option period end?', answer: 'Page 2 says: within 7 days after the Effective Date.', pages: [2], note: "Hi Ana Lopez, here's what the contract says.", asked_at: '2026-11-24T15:05:00Z' }] }) }));
    await render(wrap(<ContractAskScreen />));

    await fireEvent.changeText(await screen.findByLabelText('Your question'), 'When does the option period end?');
    await fireEvent.press(screen.getByLabelText('Ask'));
    expect(await screen.findByText('Page 2 says: within 7 days after the Effective Date.')).toBeTruthy();
    await fireEvent.press(screen.getByText('Share the note'));
    expect(share).toHaveBeenCalledWith({ message: "Hi Ana Lopez, here's what the contract says." });
  });
});
