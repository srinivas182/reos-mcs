import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { SwitchState } from '@/components/licence/types';
import { i18n } from '@/lib/i18n';
import SwitchPage from '@/pages/licence/switch';
import MentorsPage from '@/pages/mentors/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/licence-journey/licence', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const blank: SwitchState = {
    number: null,
    check: null,
    checked_at: null,
    watching: false,
    record: null,
    can_switch: false,
    broker: null,
    switched: false,
};
const found: SwitchState = {
    ...blank,
    number: '0798765',
    check: 'verified',
    can_switch: true,
    record: {
        name: 'Ana Ruiz',
        type: 'sales_agent',
        status: 'active',
        licensed_on: '2023-04-01',
        sponsor: 'Lone Star Realty',
    },
};

describe('switching to agent (A10.3c: f03, 20, f04)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(
            async () =>
                new Response(
                    JSON.stringify({ data: found, organisation: 'REOS' }),
                    {
                        status: 200,
                        headers: { 'Content-Type': 'application/json' },
                    },
                ),
        );
        vi.stubGlobal('fetch', fetchMock);
        router.visit.mockClear();
        router.reload.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('checks the number with TREC', async () => {
        const user = userEvent.setup();
        render(<SwitchPage switch={blank} />);

        await user.type(
            screen.getByLabelText('TREC licence number'),
            '0798765',
        );
        await user.click(screen.getByRole('button', { name: 'Check' }));
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/licence/check',
        );
    });

    it('shows the licence found and switches on your own; the broker seat needs an accepted offer', async () => {
        const user = userEvent.setup();
        render(<SwitchPage switch={found} />);

        expect(screen.getByText('Active licence found')).toBeInTheDocument();
        expect(
            screen.getByText(/Sponsored by Lone Star Realty/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Accept a broker offer first/),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Continue' }));
        await waitFor(() =>
            expect(router.visit).toHaveBeenCalledWith('/dashboard'),
        );
        expect(
            JSON.parse(
                String(
                    (fetchMock.mock.calls[0]?.[1] as RequestInit).body ?? '{}',
                ),
            ),
        ).toEqual({ path: 'alone', code: null });
    });

    it('says REOS checks daily while the licence is inactive', () => {
        render(
            <SwitchPage
                switch={{
                    ...blank,
                    number: '0797777',
                    check: 'inactive',
                    watching: true,
                }}
            />,
        );

        expect(
            screen.getByText("Your licence isn't active yet"),
        ).toBeInTheDocument();
        expect(screen.getByText(/checks every morning/)).toBeInTheDocument();
    });

    it("lists the brokerage's mentors, or says to join one", () => {
        const { rerender } = render(
            <MentorsPage
                brokerage="Lone Star Realty"
                mentors={[
                    {
                        id: 3,
                        user_id: 12,
                        name: 'Maya Chen',
                        school: 'Lone Star Realty',
                        bio: 'Twelve years in Katy.',
                        languages: ['en', 'es'],
                        topics: ['First listings'],
                        price_30: 0,
                        price_60: 0,
                        rating: 4.9,
                        ratings: 31,
                        next_slot: null,
                    },
                ]}
            />,
        );
        expect(screen.getByText('Maya Chen')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Mentors from Lone Star Realty. Book a 1-on-1 session.',
            ),
        ).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <MentorsPage brokerage={null} mentors={[]} />
            </I18nextProvider>,
        );
        expect(
            screen.getByText('Join a brokerage to see its mentors'),
        ).toBeInTheDocument();
    });
});
