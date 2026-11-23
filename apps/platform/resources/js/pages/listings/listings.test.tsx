import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { draft, james, overview } from '@/components/listings/fixtures';
import { i18n } from '@/lib/i18n';
import ListingsPage from '@/pages/listings/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));
const toast = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/deals/listings', props: { errors: {} } }),
}));
vi.mock('@/lib/undo-toast', () => ({ undoToast: toast }));
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('My listings (B10.1)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(
            async () =>
                new Response(JSON.stringify({ data: overview() }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        );
        vi.stubGlobal('fetch', fetchMock);
        router.reload.mockClear();
        toast.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('shows the listings, on the market first, marked as sample data', () => {
        render(<ListingsPage listings={overview()} />);

        expect(screen.getByText('Sample data')).toBeInTheDocument();
        expect(screen.getByText('$489,000')).toBeInTheDocument();
        expect(screen.getByText('Sold for $331,000')).toBeInTheDocument();
        expect(screen.getAllByText(/Co-listing agent/)).toHaveLength(1);
        expect(screen.getByText('Your MLS memberships')).toBeInTheDocument();
    });

    it('asks "Is this you?" for a record found by license, and links it', async () => {
        const user = userEvent.setup();
        render(
            <ListingsPage
                listings={overview({
                    linked: [],
                    listings: [],
                    candidates: [james],
                })}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Yes, this is me' }),
        );
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        const [url, init] = fetchMock.mock.calls[0] as [
            string,
            RequestInit | undefined,
        ];
        expect(url).toContain('/deals/listings/members');
        expect(
            JSON.parse(typeof init?.body === 'string' ? init.body : '{}'),
        ).toEqual({ mls: 'mls-har', member_key: 'HAR-M1' });
    });

    it('unlinks with Undo instead of asking first', async () => {
        const user = userEvent.setup();
        render(<ListingsPage listings={overview()} />);

        await user.click(screen.getByRole('button', { name: 'Unlink HAR' }));
        await waitFor(() =>
            expect(toast).toHaveBeenCalledWith(
                expect.objectContaining({ message: 'Unlinked from HAR' }),
            ),
        );
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/deals/listings/members/unlink',
        );
    });

    it('says what to do with no license, or no membership found', () => {
        const { rerender } = render(
            <ListingsPage
                listings={overview({
                    licensed: false,
                    linked: [],
                    listings: [],
                    sample: false,
                })}
            />,
        );
        expect(
            screen.getByText('Add your license to see your listings'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Add a license' }),
        ).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <ListingsPage
                    listings={overview({
                        linked: [],
                        listings: [],
                        sample: false,
                    })}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByText('No MLS membership found yet'),
        ).toBeInTheDocument();
    });

    it('makes a listing post with the photos the MLS allows', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(
            async () =>
                new Response(JSON.stringify({ data: draft() }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        );
        render(<ListingsPage listings={overview()} />);

        await user.click(
            screen.getByRole('button', {
                name: 'Create a post for 2418 Willow Bend Dr, Katy, TX 77494',
            }),
        );
        expect(
            await screen.findByText(
                'Up to 4 photos, as the MLS allows. 2 chosen.',
            ),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Save post' }));

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        const [url, init] = fetchMock.mock.calls[1] as [
            string,
            RequestInit | undefined,
        ];
        expect(url).toContain('/post');
        expect(init?.method).toBe('PUT');
        expect(
            JSON.parse(typeof init?.body === 'string' ? init.body : '{}'),
        ).toMatchObject({ photos: draft().chosen });
    });
});
