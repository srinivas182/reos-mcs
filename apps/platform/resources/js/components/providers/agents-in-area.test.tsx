import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { mayaAgents, mayaSent, mayaUsedUp } from './agents-fixtures';
import { AgentsInArea } from './agents-in-area';

const api = vi.hoisted(() => ({ postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    request: '/provider/requests',
    withdraw: (id: string) => `/provider/requests/${id}/withdraw`,
};

describe('Agents in my areas (B16.1)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('asks an agent to connect and counts it toward the month', async () => {
        api.postJson.mockResolvedValue({
            data: {
                id: 'r-1',
                status: 'pending',
                expires_at: '2026-12-14T15:00:00Z',
            },
            usage: { ...mayaAgents.usage, used: 4, left: 1 },
        });
        render(
            <AgentsInArea
                view={mayaAgents}
                sent={mayaSent}
                routes={routes}
                onFilter={() => {}}
            />,
        );

        expect(
            screen.getByText(/3 of 5 requests used this month/),
        ).toBeInTheDocument();
        expect(screen.getAllByText('Top producer')).toHaveLength(2);
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Ask to connect with James M.',
            }),
        );
        await userEvent.type(
            screen.getByLabelText('A short note (optional)'),
            'Happy to help.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send request' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/provider/requests', {
            agent_id: 11,
            message: 'Happy to help.',
        });
        expect(toast.success).toHaveBeenCalledWith('Request sent to James M..');
        expect(
            await screen.findByText(/4 of 5 requests used this month/),
        ).toBeInTheDocument();
    });

    it('shows the limit message, and turns Ask off when the month is used', async () => {
        api.postJson.mockRejectedValue({
            errors: { limit: ["You've used all 5 requests for this month."] },
        });
        const { unmount } = render(
            <AgentsInArea
                view={mayaAgents}
                sent={[]}
                routes={routes}
                onFilter={() => {}}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Ask to connect with Dana B.' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send request' }),
        );
        expect(
            await screen.findByText(
                "You've used all 5 requests for this month.",
            ),
        ).toBeInTheDocument();
        unmount();

        render(
            <AgentsInArea
                view={mayaUsedUp}
                sent={[]}
                routes={routes}
                onFilter={() => {}}
            />,
        );
        expect(
            screen.getByRole('button', {
                name: 'Ask to connect with James M.',
            }),
        ).toBeDisabled();
    });

    it('filters by level and withdraws a waiting request', async () => {
        api.postJson.mockResolvedValue({ withdrawn: true });
        const onFilter = vi.fn();
        render(
            <AgentsInArea
                view={mayaAgents}
                sent={mayaSent}
                routes={routes}
                onFilter={onFilter}
            />,
        );

        await userEvent.selectOptions(screen.getByLabelText('Activity'), 'new');
        expect(onFilter).toHaveBeenCalledWith({ level: 'new', area: null });
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Withdraw the request to Maria G.',
            }),
        );
        expect(api.postJson).toHaveBeenCalledWith(
            '/provider/requests/01936b7a-0000-7000-8000-000000000501/withdraw',
        );
        expect(toast.success).toHaveBeenCalled();
    });
});
