import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { CeCompliance, Sponsorships } from './ce-compliance';
import { loneStarCe, loneStarSponsorships } from './ce-fixtures';

const api = vi.hoisted(() => ({ postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() => Object.assign(vi.fn(), { success: vi.fn() }));
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('CE compliance and sponsorship (A16.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('reminds all due agents in one action', async () => {
        api.postJson.mockResolvedValue({
            data: { sent: 2, skipped: 0, table: loneStarCe },
        });
        render(
            <CeCompliance
                table={loneStarCe}
                routes={{ remind: '/remind', assign: '/assign' }}
            />,
        );

        expect(
            screen.getByText('2 of 3 agents due · 0 done'),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Behind')).toHaveLength(2);
        await userEvent.click(
            screen.getByRole('button', { name: 'Remind all due agents' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/remind');
        expect(toast.success).toHaveBeenCalledWith('Reminded 2 agents.');
    });

    it('assigns a course to agents I choose', async () => {
        api.postJson.mockResolvedValue({
            data: { assigned: 1, table: loneStarCe },
        });
        render(
            <CeCompliance
                table={loneStarCe}
                routes={{ remind: '/remind', assign: '/assign' }}
            />,
        );

        await userEvent.selectOptions(screen.getByLabelText('Who'), 'selected');
        await userEvent.click(screen.getByLabelText('Dana Brooks'));
        await userEvent.click(screen.getByRole('button', { name: 'Assign' }));
        expect(api.postJson).toHaveBeenCalledWith('/assign', {
            course_id: 1,
            agents: [5],
            due_on: null,
        });
    });

    it('asks who takes the open deals when releasing an agent with deals', async () => {
        api.postJson.mockResolvedValue({ data: loneStarSponsorships });
        render(<Sponsorships board={loneStarSponsorships} route="/release" />);

        expect(screen.getByText('Confirmed by TREC')).toBeInTheDocument();
        await userEvent.selectOptions(
            screen.getByLabelText('Hand open deals to'),
            '5',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Release' }));
        expect(api.postJson).toHaveBeenCalledWith(
            '/release',
            expect.objectContaining({ agent_id: 3, handover_to: 5 }),
        );
    });
});
