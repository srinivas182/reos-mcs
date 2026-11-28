import { act, render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { BrokerageRules } from './brokerage-rules';
import { StaffPermissions } from './staff-permissions';

const api = vi.hoisted(() => ({
    postJson: vi.fn(),
    putJson: vi.fn(),
    deleteJson: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    check: '/check',
    store: '/rules',
    update: (id: number) => `/rules/${id}`,
    destroy: (id: number) => `/rules/${id}`,
    restore: (id: number) => `/rules/${id}/restore`,
};
const conflict = {
    rule: 'brokerage.compensation',
    citation: '22 TAC §535.3',
    url: 'https://www.trec.texas.gov/',
    excerpt: 'Agents may accept referral fees directly.',
    rule_text:
        'A sales agent may accept compensation only from their sponsoring broker.',
};

describe('Brokerage rules (A15.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('flags a conflict with TREC as the rule is typed, then saves it', async () => {
        api.postJson.mockImplementation((url: string) =>
            Promise.resolve(
                url === '/check'
                    ? { data: { conflict } }
                    : {
                          data: {
                              id: 9,
                              topic: 'Referral fees',
                              text: 'Agents may accept referral fees directly.',
                              conflict,
                              updated_at: null,
                          },
                      },
            ),
        );
        render(<BrokerageRules rules={[]} canManage routes={routes} />);
        expect(screen.getByText('No brokerage rules yet')).toBeInTheDocument();

        await userEvent.type(screen.getByLabelText('Topic'), 'Referral fees');
        await userEvent.type(
            screen.getByLabelText('Rule'),
            'Agents may accept referral fees directly.',
        );
        await act(async () => {
            await new Promise((r) => setTimeout(r, 600));
        });
        expect(
            await screen.findByText('Conflicts with 22 TAC §535.3'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Read the TREC rule/ }),
        ).toHaveAttribute('href', 'https://www.trec.texas.gov/');

        await userEvent.click(screen.getByRole('button', { name: 'Add rule' }));
        expect(api.postJson).toHaveBeenCalledWith('/rules', {
            topic: 'Referral fees',
            text: 'Agents may accept referral fees directly.',
        });
        expect(
            await screen.findByText(
                'Agents may accept referral fees directly.',
            ),
        ).toBeInTheDocument();
    });

    it('shows rules read-only to someone without the duty', () => {
        render(
            <BrokerageRules
                rules={[
                    {
                        id: 1,
                        topic: 'Listings',
                        text: 'Send listings to the office.',
                        conflict: null,
                        updated_at: null,
                    },
                ]}
                canManage={false}
                routes={routes}
            />,
        );
        expect(
            screen.getByText('Your designated broker manages policies.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Edit/ }),
        ).not.toBeInTheDocument();
    });
});

describe('People and permissions (A15.2)', () => {
    it('lets the designated broker turn a duty on', async () => {
        const people = [
            {
                user_id: 1,
                name: 'Linda Park',
                title: 'designated_broker' as const,
                team: null,
                duties: [],
                editable: false,
            },
            {
                user_id: 2,
                name: 'Ana Ortiz',
                title: 'office_admin' as const,
                team: null,
                duties: [],
                editable: true,
            },
        ];
        api.putJson.mockResolvedValue({
            data: {
                people: [
                    people[0],
                    { ...people[1], duties: ['approve_posts'] },
                ],
            },
        });
        render(
            <StaffPermissions
                people={people}
                designated
                route={(id) => `/staff/${id}`}
            />,
        );
        expect(screen.getByText('Can do everything')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('checkbox', { name: 'Approve posts' }),
        );
        expect(api.putJson).toHaveBeenCalledWith('/staff/2', {
            duties: ['approve_posts'],
        });
        expect(
            await screen.findByRole('checkbox', { name: 'Approve posts' }),
        ).toBeChecked();
    });
});
