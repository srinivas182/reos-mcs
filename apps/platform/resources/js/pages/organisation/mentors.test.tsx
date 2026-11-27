import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { program } from '@/components/mentoring/fixtures';
import { i18n } from '@/lib/i18n';
import MentorProgramPage from '@/pages/organisation/mentors';

const { put, post } = vi.hoisted(() => {
    // Inertia calls onFinish once the visit is done.
    const visit = vi.fn(
        (_url: string, _data: unknown, options?: { onFinish?: () => void }) =>
            options?.onFinish?.(),
    );

    return { put: visit, post: vi.fn() };
});

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { put, post },
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the mentor program (A13.1a, broker screens 21–23)', () => {
    beforeEach(() => {
        // The checkboxes and switch (Radix) measure themselves; jsdom has no ResizeObserver.
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        vi.stubGlobal(
            'fetch',
            vi.fn(
                async () =>
                    new Response(
                        JSON.stringify({
                            data: [
                                {
                                    id: 11,
                                    name: 'Sarah Kim',
                                    score: 7,
                                    reasons: ['language', 'focus', 'capacity'],
                                    room: 2,
                                },
                            ],
                        }),
                        {
                            status: 200,
                            headers: { 'Content-Type': 'application/json' },
                        },
                    ),
            ),
        );
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it("saves a mentor's price and assigns the best match", async () => {
        const user = userEvent.setup();
        render(<MentorProgramPage {...program()} />);

        await user.click(
            screen.getAllByRole('button', { name: 'Settings' })[0],
        );
        const price = screen.getByLabelText('60 minutes (US$)');
        await user.clear(price);
        await user.type(price, '50');
        await user.click(screen.getByRole('button', { name: 'Save' }));
        expect(put).toHaveBeenCalledWith(
            '/organisation/mentors/11',
            expect.objectContaining({
                price_60_cents: 5000,
                topics: ['first_deals', 'contracts'],
            }),
            expect.anything(),
        );

        await user.click(screen.getByRole('button', { name: 'Assign' }));
        await waitFor(() =>
            expect(
                screen.getByText('Best matches for Priya Shah'),
            ).toBeInTheDocument(),
        );
        await user.click(
            screen
                .getAllByRole('button', { name: 'Assign' })
                .at(-1) as HTMLElement,
        );
        expect(post).toHaveBeenCalledWith(
            '/organisation/mentors/assign',
            { agent: 21, mentor: 11 },
            expect.anything(),
        );
    });
});
