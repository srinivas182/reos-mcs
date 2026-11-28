import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { loneStarQueue } from './approvals-fixtures';
import { ApprovalsQueue } from './approvals-queue';

const api = vi.hoisted(() => ({ postJson: vi.fn(), putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    act: (kind: string, id: string) => `/approvals/${kind}/${id}`,
    settings: '/approvals/settings',
};

describe('Approvals queue (A15.3)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        globalThis.ResizeObserver ??= class {
            observe() {}
            unobserve() {}
            disconnect() {}
        } as unknown as typeof ResizeObserver;
    });

    it('shows the oldest item with its checks and approves it', async () => {
        api.postJson.mockResolvedValue({
            data: {
                ...loneStarQueue,
                counts: { posts: 0, pages: 1, questions: 1, overdue: 0 },
                items: loneStarQueue.items.slice(1),
            },
        });
        render(<ApprovalsQueue queue={loneStarQueue} routes={routes} />);

        expect(
            screen.getByText('1 past your 4-hour limit'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                '"best deal in town": Say what makes it a good value instead.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Approve' }));
        expect(api.postJson).toHaveBeenCalledWith(
            '/approvals/post/01936f0e-0000-7000-8000-000000000001',
            { action: 'approve', text: null, add_to_knowledge: false },
        );
        expect(
            await screen.findByRole('heading', { name: 'Public page' }),
        ).toBeInTheDocument();
    });

    it('answers a question and adds it to the knowledge', async () => {
        api.postJson.mockResolvedValue({
            data: { ...loneStarQueue, items: [] },
        });
        render(<ApprovalsQueue queue={loneStarQueue} routes={routes} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Questions (1)' }),
        );
        const list = screen.getByRole('list', { name: 'Approvals' });
        await userEvent.click(
            within(list).getByRole('button', {
                name: /Can I pay a referral fee/,
            }),
        );
        await userEvent.type(
            screen.getByLabelText('Your answer'),
            'No. It goes through the brokerage.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send answer' }),
        );
        expect(api.postJson).toHaveBeenCalledWith(
            '/approvals/question/01936f0e-0000-7000-8000-000000000003',
            {
                action: 'answer',
                text: 'No. It goes through the brokerage.',
                add_to_knowledge: true,
            },
        );
        expect(await screen.findByText('Nothing waiting')).toBeInTheDocument();
    });

    it('lets the designated broker set the fallback, and hides it from others', async () => {
        api.putJson.mockResolvedValue({
            data: { ...loneStarQueue, fallback_hours: 2 },
        });
        const { unmount } = render(
            <ApprovalsQueue queue={loneStarQueue} routes={routes} />,
        );
        const hours = screen.getByLabelText('Working hours to wait');
        await userEvent.clear(hours);
        await userEvent.type(hours, '2');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(api.putJson).toHaveBeenCalledWith('/approvals/settings', {
            fallback_hours: 2,
        });
        unmount();

        render(
            <ApprovalsQueue
                queue={{
                    ...loneStarQueue,
                    can: { posts: false, questions: true, settings: false },
                    items: [],
                }}
                routes={routes}
            />,
        );
        expect(
            screen.queryByText('Office admin fallback'),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: /Posts/ }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Nothing waiting')).toBeInTheDocument();
    });
});
