import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import InstructorScreen from '@/app/bookings/[id]';
import HoursScreen from '@/app/bookings/hours';
import BookingsScreen from '@/app/bookings/index';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: null }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const nelle = {
  id: 1, user_id: 12, name: 'Nelle Hart', school: 'Texas Realty School', bio: 'Contracts and math.', languages: ['en', 'es'], topics: ['Contracts'],
  price_30: 3500, price_60: 6000, rating: 4.9, ratings: 38, next_slot: '2026-11-24T19:00:00Z',
};
const booking = {
  uuid: '5c4b3a29-1807-4f6e-9d5c-4b3a29180f6e', as: 'student', instructor: 'Nelle Hart', student: 'Ana Ruiz', starts_at: '2026-11-24T19:00:00Z', ends_at: '2026-11-24T19:30:00Z',
  minutes: 30, topic: 'Proration math', status: 'confirmed', price: 3500, session: '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d', can_cancel: true, refund_on_cancel: true, can_rate: false, rating: null, tasks_sent: false, checkout_url: null,
};

beforeEach(() => jest.clearAllMocks());

describe('Book an instructor on the phone (candidate screen 18, A10.2a)', () => {
  it('lists your sessions and instructors, and cancels with a refund until 24 hours before', async () => {
    mockApi.GET.mockImplementation(async (path: string) => ok({ data: path === '/bookings' ? [booking] : [nelle] }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...booking, status: 'refunded' } }));
    await render(wrap(<BookingsScreen />));

    expect(await screen.findByText('With Nelle Hart')).toBeTruthy();
    expect(screen.getByText(/4\.9 \(38\) · From \$35/)).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Join' }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/sessions/[id]', params: { id: booking.session } });
    await fireEvent.press(screen.getByRole('button', { name: 'Cancel' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/bookings/{booking}/cancel', { params: { path: { booking: booking.uuid } } });
  });

  it('books a free time with a topic, and opens checkout for a paid one', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '12' });
    mockApi.GET.mockResolvedValue(ok({ data: nelle, slots_30: ['2026-11-24T19:00:00Z'], slots_60: ['2026-11-24T19:00:00Z'] }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...booking, status: 'held' }, checkout_url: 'https://reos.com/checkout/9?booking=x' }));
    await render(wrap(<InstructorScreen />));

    expect(await screen.findByText('Contracts and math.')).toBeTruthy();
    const slot = new Date('2026-11-24T19:00:00Z').toLocaleString('en', { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    await fireEvent.press(screen.getByRole('button', { name: slot }));
    await fireEvent.changeText(screen.getByLabelText('What would you like help with?'), 'Proration math');
    await fireEvent.press(screen.getByRole('button', { name: 'Book and pay $60' }));

    expect(mockApi.POST).toHaveBeenCalledWith('/bookings', { body: { instructor: 12, starts_at: '2026-11-24T19:00:00Z', minutes: 60, topic: 'Proration math' } });
    expect(jest.requireMock<{ openBrowserAsync: jest.Mock }>('expo-web-browser').openBrowserAsync).toHaveBeenCalledWith('https://reos.com/checkout/9?booking=x');
    expect(router.replace).toHaveBeenCalledWith('/bookings');
  });
});

describe('Hours and prices on the phone (instructor i14, A10.2d)', () => {
  it("shows the school's price limit beside each price", async () => {
    mockApi.GET.mockResolvedValue(ok({ data: null, limits: { max_30_cents: null, max_60_cents: 7500 } }));
    await render(wrap(<HoursScreen />));

    expect(await screen.findByText('Your school allows at most $75.')).toBeTruthy();
    expect(screen.queryAllByText(/Your school allows at most/)).toHaveLength(1);
  });
});
