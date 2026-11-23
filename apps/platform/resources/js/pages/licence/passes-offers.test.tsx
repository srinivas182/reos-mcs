import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { journey, offer, passFeed } from '@/components/licence/fixtures';
import { i18n } from '@/lib/i18n';
import LicenceJourneyPage from '@/pages/licence/index';
import OffersPage from '@/pages/licence/offers';
import PassesPage from '@/pages/licence/passes';
import RecruitingPage from '@/pages/recruiting/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/licence-journey', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (body: unknown) =>
    new Response(JSON.stringify(body), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
    });
const bodyOf = (call: unknown[] | undefined) => {
    const body = (call?.[1] as RequestInit | undefined)?.body;

    return JSON.parse(typeof body === 'string' ? body : '{}') as Record<
        string,
        unknown
    >;
};

describe('after the exam (A10.3b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async () => reply({ data: {} }));
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        Object.values(router).forEach((f) => f.mockClear());
    });

    afterEach(() => vi.unstubAllGlobals());

    it('shares the news with the people chosen, or nothing on Not now', async () => {
        const user = userEvent.setup();
        render(
            <LicenceJourneyPage
                journey={journey({
                    news: 'prompt',
                    results: { ...journey().results, passed_both: true },
                })}
            />,
        );

        await user.click(screen.getByRole('checkbox', { name: /My school/ }));
        await user.type(
            screen.getByLabelText('A tip for others (optional)'),
            'Math lab every day.',
        );
        await user.click(screen.getByRole('button', { name: 'Share' }));
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/news',
        );
        expect(bodyOf(fetchMock.mock.calls[0])).toEqual({
            to_group: true,
            to_school: false,
            to_state: true,
            city: null,
            tip: 'Math lab every day.',
        });
    });

    it('opts in to broker offers with a city', async () => {
        const user = userEvent.setup();
        render(<LicenceJourneyPage journey={journey()} />);

        await user.click(
            screen.getByRole('switch', { name: 'Get offers from brokerages' }),
        );
        await user.type(
            screen.getByLabelText('City you want to work in'),
            'Katy',
        );
        await user.click(screen.getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/offers/opt-in',
        );
        expect(bodyOf(fetchMock.mock.calls[0])).toEqual({
            on: true,
            city: 'Katy',
        });
    });

    it('sends congrats on a pass', async () => {
        const user = userEvent.setup();
        render(<PassesPage feed={passFeed()} />);

        expect(
            screen.getByText('14 people passed in Texas this week'),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Send congrats' }));
        await user.click(screen.getByRole('button', { name: 'Send congrats' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/passes/7/congrats',
        );
        expect(bodyOf(fetchMock.mock.calls[0])).toEqual({
            message: 'Congratulations, Jordan!',
        });
    });

    it('accepts an offer', async () => {
        const user = userEvent.setup();
        render(
            <OffersPage
                offers={{ opted_in: true, city: 'Katy', offers: [offer()] }}
            />,
        );

        expect(screen.getByText('70% to you')).toBeInTheDocument();
        expect(screen.getByText('In your city')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Accept' }));
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            `/licence-journey/offers/${offer().id}/accept`,
        );
    });

    it('lets a brokerage make an offer, and shows contact details only after Accept', async () => {
        const user = userEvent.setup();
        const { rerender } = render(
            <RecruitingPage
                brokerage={{ name: 'Lone Star Realty', states: ['TX'] }}
                candidates={[
                    {
                        id: 41,
                        first_name: 'Ana',
                        city: 'Katy',
                        stage: 'exam_booked',
                        offer: null,
                    },
                ]}
            />,
        );

        expect(screen.getByText('Ana · Katy')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Make an offer' }));
        await user.clear(screen.getByLabelText("Agent's split (%)"));
        await user.type(screen.getByLabelText("Agent's split (%)"), '75');
        await user.click(screen.getByRole('button', { name: 'Send offer' }));
        expect(router.put).toHaveBeenCalledWith(
            '/recruiting/candidates/41/offer',
            expect.objectContaining({
                office_city: 'Katy',
                split_percent: 75,
                mentoring: true,
            }),
            expect.anything(),
        );

        rerender(
            <I18nextProvider i18n={i18n}>
                <RecruitingPage
                    brokerage={{ name: 'Lone Star Realty', states: ['TX'] }}
                    candidates={[
                        {
                            id: 41,
                            first_name: 'Ana',
                            city: 'Katy',
                            stage: 'passed',
                            offer: offer({
                                status: 'accepted',
                                contact: {
                                    name: 'Ana Ruiz',
                                    email: 'ana@example.com',
                                    phone: null,
                                },
                            }),
                        },
                    ]}
                />
            </I18nextProvider>,
        );
        expect(screen.getByText(/ana@example.com/)).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Make an offer' }),
        ).toBeNull();
    });
});
