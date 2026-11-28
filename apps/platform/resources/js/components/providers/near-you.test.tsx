import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { chenNetwork, chenReviewable, jamesNear } from './near-fixtures';
import { NearYou, ProviderNetworkPanel } from './near-you';

const api = vi.hoisted(() => ({ postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    connect: '/providers/3/connect',
    review: '/providers/3/reviews',
    requests: '/providers/requests',
};

describe('Providers near you (B16.3)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists providers near the agent first, with ratings', () => {
        render(
            <NearYou
                near={jamesNear}
                category={null}
                profileHref={(id) => `/providers/${id}`}
                categoryHref={(key) => `/providers?category=${key ?? ''}`}
            />,
        );

        expect(screen.getByText('Serving Katy')).toBeInTheDocument();
        expect(screen.getAllByText('Near you')).toHaveLength(2);
        expect(
            screen.getByText('4.8 out of 5 · 12 reviews'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: "View Chen Home Lending's profile",
            }),
        ).toHaveAttribute('href', '/providers/3');
        expect(
            screen.queryByRole('link', { name: /Title company/ }),
        ).not.toBeInTheDocument();
    });

    it('connects in one tap from the profile (the second tap)', async () => {
        api.postJson.mockResolvedValue({ data: { id: 'c-1' } });
        render(
            <ProviderNetworkPanel
                name="Chen Home Lending"
                network={chenNetwork}
                routes={routes}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Request to connect with Chen Home Lending',
            }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/providers/3/connect');
        expect(toast.success).toHaveBeenCalledWith(
            'Connected with Chen Home Lending. Your chat is in Messages.',
        );
        expect(
            await screen.findByRole('link', { name: 'Open connection' }),
        ).toHaveAttribute('href', '/providers/requests');
    });

    it('posts a review with stars and a note', async () => {
        api.postJson.mockResolvedValue({ data: chenReviewable });
        render(
            <ProviderNetworkPanel
                name="Chen Home Lending"
                network={chenReviewable}
                routes={routes}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: '4 stars' }));
        await userEvent.type(
            screen.getByLabelText(
                'What was it like to work with them? (optional)',
            ),
            'Quick and clear.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Post review' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/providers/3/reviews', {
            rating: 4,
            body: 'Quick and clear.',
        });
    });
});
