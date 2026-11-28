import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { categories, mayaJoining } from './join-fixtures';
import { JoinWizard } from './join-wizard';

const api = vi.hoisted(() => ({
    postJson: vi.fn(),
    putJson: vi.fn(),
    getJson: vi.fn(),
    deleteJson: vi.fn(),
    postForm: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    request: '/requests',
    category: '/category',
    fields: '/fields',
    status: '/status',
    welcome: '/welcome',
    business: '/business',
    licence: '/licences',
    areas: '/areas',
    area: (id: number) => `/areas/${id}`,
    plan: '/plan',
    rules: '/rules',
};

describe('Joining the provider network (B15.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('opens at the first step left, and buys the plan that fits the areas on reos.com', async () => {
        const onCheckout = vi.fn();
        api.postJson.mockResolvedValue({
            data: { checkout_url: 'https://reos.com/checkout/12?signup=abc' },
        });
        render(
            <JoinWizard
                categories={categories}
                onboarding={mayaJoining}
                routes={routes}
                onCheckout={onCheckout}
            />,
        );

        expect(screen.getByText('4 of 6 done')).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Choose your plan' }),
        ).toBeInTheDocument();
        // Two areas: Pro is picked, not Listed.
        expect(screen.getByRole('radio', { name: /Pro/ })).toBeChecked();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Continue to payment on reos.com',
            }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/plan', { tier: 'pro' });
        expect(onCheckout).toHaveBeenCalledWith(
            'https://reos.com/checkout/12?signup=abc',
        );
    });

    it('shows agents and providers in each area, and adds a radius', async () => {
        api.postJson.mockResolvedValue({ data: mayaJoining });
        render(
            <JoinWizard
                categories={categories}
                onboarding={mayaJoining}
                routes={routes}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Service areas' }),
        );
        expect(screen.getByText('15 miles around 77494')).toBeInTheDocument();
        expect(
            screen.getByText(
                '540 active agents · 6 providers in your category',
            ),
        ).toBeInTheDocument();
        await userEvent.selectOptions(
            screen.getByLabelText('Area type'),
            'radius',
        );
        await userEvent.type(screen.getByLabelText('ZIP code'), '77450');
        await userEvent.click(screen.getByRole('button', { name: 'Add area' }));
        expect(api.postJson).toHaveBeenCalledWith('/areas', {
            kind: 'radius',
            city: null,
            postal_code: '77450',
            radius_miles: 10,
        });
    });

    it('accepts the network rules only once they are ticked', async () => {
        api.postJson.mockResolvedValue({
            data: {
                ...mayaJoining,
                rules: {
                    ...mayaJoining.rules,
                    accepted_at: '2026-11-30T15:08:00Z',
                    current: true,
                },
            },
        });
        render(
            <JoinWizard
                categories={categories}
                onboarding={mayaJoining}
                routes={routes}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Network rules' }),
        );
        expect(
            screen.getByText(/never per lead, referral or closing/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Accept the rules' }),
        ).toBeDisabled();
        await userEvent.click(
            screen.getByLabelText("I've read the rules and I'll follow them"),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Accept the rules' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/rules', {
            version: '2026-11-30',
            accept: true,
        });
    });
});
