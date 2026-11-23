import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { anaBooking, nelle } from '@/components/bookings/fixtures';
import { i18n } from '@/lib/i18n';
import BookingsPage from '@/pages/bookings/index';
import InstructorPage from '@/pages/bookings/instructor';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/bookings', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

describe('Book an instructor (candidate screen 18, A10.2a)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async () => reply({ data: anaBooking }));
        vi.stubGlobal('fetch', fetchMock);
        router.visit.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('lists instructors and your sessions, and cancels with the refund rule said plainly', async () => {
        const user = userEvent.setup();
        render(
            <BookingsPage
                instructors={[nelle]}
                bookings={[
                    anaBooking,
                    { ...anaBooking, uuid: 'x-late', refund_on_cancel: false },
                ]}
                can_teach={false}
            />,
        );

        expect(screen.getByText('Nelle Hart')).toBeInTheDocument();
        expect(screen.getByText('From $35')).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: 'Cancel (no refund within 24 hours)',
            }),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            `/bookings/${anaBooking.uuid}/cancel`,
        );
        expect(router.reload).toHaveBeenCalled();
    });

    it('books a time and goes to checkout for a paid session', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementationOnce(async () =>
            reply(
                {
                    data: { ...anaBooking, status: 'held' },
                    checkout_url: '/checkout/9?booking=abc',
                },
                201,
            ),
        );
        render(
            <InstructorPage
                instructor={nelle}
                slots={{
                    '30': ['2026-11-24T19:00:00Z'],
                    '60': ['2026-11-24T19:00:00Z', '2026-11-24T19:30:00Z'],
                }}
            />,
        );

        const book = screen.getByRole('button', {
            name: 'Book and pay $60',
        });
        expect(book).toBeDisabled();
        await user.click(screen.getAllByRole('button', { pressed: false })[0]!);
        await user.type(
            screen.getByLabelText('What would you like help with?'),
            'Proration math',
        );
        await user.click(book);

        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/bookings');
        expect(JSON.parse(init.body as string)).toEqual({
            instructor: 12,
            starts_at: '2026-11-24T19:00:00Z',
            minutes: 60,
            topic: 'Proration math',
        });
        expect(router.visit).toHaveBeenCalledWith('/checkout/9?booking=abc');
    });
});
