import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { SeatsCard } from './seats-card';
import { TrecRoster } from './trec-roster';
import type { TrecRosterData } from './types';

const api = vi.hoisted(() => ({ postJson: vi.fn(), putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const roster = (
    over: Partial<TrecRosterData['counts']> = {},
): TrecRosterData => ({
    trec_number: '0600123',
    counts: {
        sponsored: 2,
        member: 0,
        invited: 0,
        on_reos: 1,
        contact: 0,
        needs_contact: 1,
        ...over,
    },
    agents: [
        {
            licence: '0722222',
            name: 'Maria Lopez',
            type: 'SALE',
            expires_on: null,
            state: 'needs_contact',
            contact: null,
        },
        {
            licence: '0733333',
            name: 'Omar Haddad',
            type: 'SALE',
            expires_on: null,
            state: 'on_reos',
            contact: null,
        },
    ],
});

describe('Bring in your agents (A15.1b)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('adds a contact, then invites everyone who can be reached in one step', async () => {
        api.postJson
            .mockResolvedValueOnce({
                data: {
                    saved: 1,
                    refused: [],
                    roster: roster({ contact: 1, needs_contact: 0 }),
                },
            })
            .mockResolvedValueOnce({
                data: {
                    sent: 2,
                    needs_contact: 0,
                    roster: roster({
                        invited: 2,
                        on_reos: 0,
                        needs_contact: 0,
                    }),
                },
            });
        render(
            <TrecRoster
                roster={roster()}
                routes={{
                    contacts: '/roster/contacts',
                    invite: '/roster/invite',
                }}
            />,
        );
        expect(
            screen.getByText(
                /2 sponsored on TREC · 0 in your brokerage · 0 invited · 1 ready to invite · 1 need an email or phone/,
            ),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Add emails or phones' }),
        );
        await userEvent.type(
            screen.getByLabelText('License numbers and contacts'),
            '0722222, maria@example.com',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save contacts' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/roster/contacts', {
            lines: '0722222, maria@example.com',
        });
        expect(await screen.findByText('1 contact saved.')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Invite all 2 agents' }),
        );
        expect(api.postJson).toHaveBeenLastCalledWith('/roster/invite');
        expect(
            await screen.findByText('2 invitations sent.'),
        ).toBeInTheDocument();
    });
});

describe('Seats and who pays (A15.1b)', () => {
    it('saves who pays and pooling', async () => {
        const plan = {
            seats: 25,
            used: 1,
            pending: 2,
            left: 22,
            payer: 'organisation' as const,
            credits_per_seat: 300,
            credits_pooled: true,
            pool: 7500,
            candidate_pool: 0,
            teams: 0,
            chosen: false,
        };
        api.putJson.mockResolvedValue({
            data: {
                ...plan,
                payer: 'split',
                credits_pooled: false,
                pool: null,
                chosen: true,
            },
        });
        render(
            <SeatsCard
                plan={plan}
                route="/organisation/seats"
                moreHref="/settings/billing"
            />,
        );
        await userEvent.click(
            screen.getByLabelText(/Split: the brokerage pays the seat/),
        );
        await userEvent.click(
            screen.getByLabelText(/Pool AI credits across all seats/),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));

        expect(api.putJson).toHaveBeenCalledWith('/organisation/seats', {
            payer: 'split',
            credits_pooled: false,
        });
        expect(
            await screen.findByText(/1 of 25 seats used/),
        ).toBeInTheDocument();
        expect(screen.queryByText(/Pool this month/)).not.toBeInTheDocument();
    });
});
