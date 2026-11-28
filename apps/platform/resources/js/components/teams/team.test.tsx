import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { LeadRoutingPanel, SplitSheetsPanel, TeamLeads } from './team';
import {
    okoyeRouting,
    okoyeSplits,
    priyaOffered,
    priyaSheet,
} from './team-fixtures';

const api = vi.hoisted(() => ({ postJson: vi.fn(), putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Teams (A17.3)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('saves routing rules and toggles the rotation', async () => {
        api.putJson.mockResolvedValue({ data: okoyeRouting });
        render(
            <LeadRoutingPanel
                board={okoyeRouting}
                routes={{
                    rules: '/team/routing',
                    member: (id) => `/team/routing/members/${id}`,
                    lead: '/team/leads',
                }}
            />,
        );

        expect(screen.getByText(/Claimed by Maria Garcia/)).toBeInTheDocument();
        expect(screen.getByText(/Off-hours reply sent/)).toBeInTheDocument();
        await userEvent.selectOptions(
            screen.getByLabelText('If nobody claims'),
            'leader',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save rules' }),
        );
        expect(api.putJson).toHaveBeenCalledWith(
            '/team/routing',
            expect.objectContaining({ fallback: 'leader', claim_minutes: 5 }),
        );
        await userEvent.click(
            screen.getByRole('switch', { name: 'Tom Reyes in rotation' }),
        );
        expect(api.putJson).toHaveBeenLastCalledWith(
            '/team/routing/members/3',
            { active: true, languages: ['en'] },
        );
    });

    it("shows the agreement and each sheet's shares", () => {
        render(
            <SplitSheetsPanel
                board={okoyeSplits}
                routes={{ agreement: '/team/agreement', send: '/team/splits' }}
            />,
        );

        expect(screen.getByText('The agent gets 50%')).toBeInTheDocument();
        expect(
            screen.getAllByText(
                /Brokerage \$2,400 · team \$3,600 · agent \$6,000/,
            ),
        ).toHaveLength(2);
        expect(screen.getByText(/In QuickBooks/)).toBeInTheDocument();
    });

    it('claims a lead and signs a sheet', async () => {
        api.postJson.mockResolvedValue({ data: {} });
        render(
            <TeamLeads
                offered={priyaOffered}
                splits={[priyaSheet]}
                routes={{
                    claim: (id) => `/team/leads/${id}/claim`,
                    sign: (id) => `/team/splits/${id}/sign`,
                    question: (id) => `/team/splits/${id}/question`,
                }}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Claim Ray Ng' }),
        );
        expect(api.postJson).toHaveBeenCalledWith(
            `/team/leads/${priyaOffered[0]!.id}/claim`,
        );
        expect(toast.success).toHaveBeenCalled();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Sign the split for 1420 Pecan St, Katy',
            }),
        );
        expect(api.postJson).toHaveBeenLastCalledWith(
            `/team/splits/${priyaSheet.id}/sign`,
        );
        expect(await screen.findByText(/Signed$/)).toBeInTheDocument();
    });
});
