import { render as baseRender, screen, within } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import BrokerageSettings from '@/pages/organisation/settings';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/organisation/settings', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    tenant: {
        id: 9,
        name: 'Lone Star Realty',
        type: 'brokerage' as const,
        address: 'lone-star.reos.com',
        live_address: 'lone-star.reos.com',
        seats: 142,
        payer: 'organisation' as const,
    },
    domain: null,
    brand: {
        organisation_id: 9,
        type: 'brokerage' as const,
        name: 'Lone Star Realty',
        app_label: 'Lone Star Learning',
        logo_url: null,
        primary: '#0F766E',
        palette: { action: '#0F766E', pressed: '#0C5E58', tint: '#E7F1F0' },
        email_from_name: null,
        reply_to: null,
    },
    people: [
        {
            id: 1,
            name: 'Linda Park',
            role: 'broker' as const,
            scope: null,
            two_step: true,
        },
        {
            id: 2,
            name: 'Marcus Lee',
            role: 'broker' as const,
            scope: 'North team',
            two_step: false,
        },
    ],
    invite: {
        roles: [
            { value: 'agent' as const, label: 'Licensed agent', seat: true },
            { value: 'broker' as const, label: 'Broker', seat: false },
        ],
        teams: [{ id: 6, name: 'Okoye Team' }],
        can_text: true,
        seats: {
            role: 'agent' as const,
            limit: 142,
            used: 118,
            pending: 3,
            left: 21,
            payer: 'organisation' as const,
        },
        locale: 'en' as const,
    },
};

describe('Brokerage settings (screen 41)', () => {
    it('shows the domain card, the people with their scope and two-step, the plan and the brand', () => {
        render(<BrokerageSettings {...props} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Brokerage settings',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Custom domain' }),
        ).toBeInTheDocument();

        const linda = screen.getByRole('row', { name: /Linda Park/ });
        expect(within(linda).getByText('Everything')).toBeInTheDocument();
        expect(within(linda).getByText('On')).toBeInTheDocument();
        const marcus = screen.getByRole('row', { name: /Marcus Lee/ });
        expect(within(marcus).getByText('North team')).toBeInTheDocument();
        expect(within(marcus).getByText('Off')).toBeInTheDocument();

        expect(screen.getByText(/142 seats/)).toBeInTheDocument();
        expect(screen.getByText('Lone Star Learning')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Edit brand' }),
        ).toHaveAttribute('href', '/organisation/branding');
    });

    it('says when nobody else runs the brokerage yet', () => {
        render(<BrokerageSettings {...props} people={[]} />);

        expect(
            screen.getByText(i18n.t('brokerageSettings.noPeople')),
        ).toBeInTheDocument();
    });
});
