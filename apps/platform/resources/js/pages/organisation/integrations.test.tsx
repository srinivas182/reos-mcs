import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import BrokerageIntegrations from '@/pages/organisation/integrations';

const router = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({
        url: '/organisation/integrations',
        props: { errors: {} },
    }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

type Props = ComponentProps<typeof BrokerageIntegrations>;

const salesforce: Props['tools'][number] = {
    id: 'salesforce',
    name: 'Salesforce',
    category: 'crm',
    status: 'enabled',
    accounts: 'tenant',
    action: 'connect',
    allowed: true,
    may_hide: true,
    agents_connected: 0,
    connects_with: 'sign_in',
    account: {
        connection: 7,
        label: 'admin@lonestar-realty.example',
        status: 'active',
        last_synced_at: new Date().toISOString(),
        people: 214,
        deals: null,
        agents: 38,
        unmatched_owners: ['assistant@lonestar.example'],
        mapping: { contact_stage: null, lead_stage: 'Status', leads: true },
    },
};

const props: Props = {
    brokerage: { id: 9, name: 'Lone Star Realty' },
    tools: [
        salesforce,
        {
            id: 'follow-up-boss',
            name: 'Follow Up Boss',
            category: 'crm',
            status: 'enabled',
            accounts: 'user',
            action: 'connect',
            allowed: true,
            may_hide: true,
            agents_connected: 12,
            connects_with: 'api_key',
            account: null,
        },
        {
            id: 'google-calendar',
            name: 'Google Calendar',
            category: 'calendar',
            status: 'enabled',
            accounts: 'user',
            action: 'connect',
            allowed: true,
            may_hide: false,
            agents_connected: 80,
            connects_with: 'sign_in',
            account: null,
        },
    ],
    stage_fields: {
        salesforce: {
            Contact: [{ name: 'Stage__c', label: 'Stage' }],
            Lead: [{ name: 'Status', label: 'Lead Status' }],
        },
    },
};

describe('Brokerage integrations (screen 40)', () => {
    it('opens with the title, purpose line and the brokerage account in counts only', () => {
        render(<BrokerageIntegrations {...props} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Integrations' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Accounts Lone Star Realty connects once/),
        ).toBeInTheDocument();
        expect(screen.getByText('214')).toBeInTheDocument();
        expect(screen.getByText('38')).toBeInTheDocument();
        expect(
            screen.getByText(/1 record owner has no REOS account/),
        ).toHaveTextContent('assistant@lonestar.example');
    });

    it('hides a tool from agents, and never one REOS needs everyone to have', async () => {
        render(<BrokerageIntegrations {...props} />);

        await userEvent.click(
            screen.getByRole('switch', {
                name: 'Agents may use Follow Up Boss',
            }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/organisation/integrations/tools/follow-up-boss',
            { allowed: false },
            { preserveScroll: true },
        );
        expect(
            screen.getByRole('switch', {
                name: 'Agents may use Google Calendar',
            }),
        ).toBeDisabled();
    });

    it("offers the usual Salesforce stage setup and lists the org's fields", async () => {
        render(<BrokerageIntegrations {...props} />);
        const form = screen
            .getByRole('heading', { name: "Where you keep a person's stage" })
            .closest('form')!;

        expect(
            within(form).getByRole('option', { name: 'Stage (Stage__c)' }),
        ).toBeInTheDocument();
        await userEvent.click(
            within(form).getByRole('button', { name: 'Use the usual setup' }),
        );
        expect(within(form).getByLabelText('Contact stage field')).toHaveValue(
            'Stage__c',
        );
    });

    it('offers Connect when the brokerage has no account yet', () => {
        render(
            <BrokerageIntegrations
                {...props}
                tools={[{ ...salesforce, account: null }]}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Connect Salesforce' }),
        ).toHaveAttribute('href', '/connections/salesforce/connect');
    });

    it("connects Lone Wolf with the brokerage's client ID, and shows deal counts only", async () => {
        const lw: Props['tools'][number] = {
            ...salesforce,
            id: 'lone-wolf',
            name: 'Lone Wolf Transactions',
            category: 'transactions',
            connects_with: 'api_key',
            account: null,
        };
        const user = userEvent.setup();
        const { unmount } = render(
            <BrokerageIntegrations {...props} tools={[lw]} />,
        );
        await user.type(
            screen.getByLabelText('Your Lone Wolf Transactions client ID'),
            '7f3a2c1e-0000-4c7d-9e2f-5b1a6c8d9e0f',
        );
        await user.click(
            screen.getByRole('button', {
                name: 'Connect Lone Wolf Transactions',
            }),
        );
        expect(router.post).toHaveBeenCalled();
        unmount();

        render(
            <BrokerageIntegrations
                {...props}
                tools={[
                    {
                        ...lw,
                        account: {
                            ...salesforce.account!,
                            label: 'Lone Star Realty',
                            people: 0,
                            deals: { count: 12, due_week: 3 },
                            mapping: null,
                        },
                    },
                ]}
            />,
        );
        expect(
            screen.getByText('12 · 3 deadlines this week'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/You see counts and deadlines only/),
        ).toBeInTheDocument();
    });
});
