import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { FilePanel, FilesBoard } from './files-board';
import { dealCoordinators, fileDetail, filesBoard } from './fixtures';
import { SharePanel } from './share-panel';

const api = vi.hoisted(() => ({
    getJson: vi.fn(),
    postJson: vi.fn(),
    putJson: vi.fn(),
    deleteJson: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    list: '/deals/d/coordinators',
    share: '/deals/d/coordinators',
    update: (id: string) => `/deal-shares/${id}`,
    remove: (id: string) => `/deal-shares/${id}`,
};

describe('Coordinators (B17.1)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('changes a switch and removes a coordinator', async () => {
        api.putJson.mockResolvedValue({});
        api.deleteJson.mockResolvedValue({ removed: true });
        api.getJson.mockResolvedValue({
            data: {
                ...dealCoordinators,
                shares: dealCoordinators.shares.slice(1),
            },
        });
        render(
            <SharePanel
                routes={routes}
                closed={false}
                initial={dealCoordinators}
            />,
        );

        await userEvent.click(
            screen.getByRole('switch', {
                name: 'Parties and contacts for Tina Brooks',
            }),
        );
        expect(api.putJson).toHaveBeenCalledWith(
            `/deal-shares/${dealCoordinators.shares[0]?.id}`,
            {
                switches: {
                    dates: true,
                    documents: true,
                    parties: true,
                    messages: false,
                },
            },
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove Tina Brooks' }),
        );
        expect(api.deleteJson).toHaveBeenCalled();
        expect(toast.success).toHaveBeenCalledWith(
            'Tina Brooks no longer has access.',
        );
        expect(
            await screen.findByText('Omar Diaz · Access ended: removed'),
        ).toBeInTheDocument();
    });

    it('shares with a connected coordinator using the default switches', async () => {
        api.postJson.mockResolvedValue({ data: { id: 'x' } });
        api.getJson.mockResolvedValue({ data: dealCoordinators });
        render(
            <SharePanel
                routes={routes}
                closed={false}
                initial={{ ...dealCoordinators, shares: [] }}
            />,
        );

        await userEvent.selectOptions(
            screen.getByLabelText('Coordinator'),
            '41',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Share deal' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/deals/d/coordinators', {
            coordinator_id: 41,
            switches: {
                dates: true,
                documents: true,
                parties: false,
                messages: false,
            },
        });
    });

    it('says when nobody is connected, and when the deal is closed', () => {
        const { unmount } = render(
            <SharePanel
                routes={routes}
                closed={false}
                initial={{ coordinators: [], shares: [] }}
            />,
        );
        expect(screen.getByText('No coordinators yet')).toBeInTheDocument();
        unmount();
        render(
            <SharePanel routes={routes} closed initial={dealCoordinators} />,
        );
        expect(
            screen.getByText("Closed and cancelled deals can't be shared."),
        ).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Share deal' })).toBeNull();
    });

    it('shows My files and an empty board', () => {
        const { unmount } = render(
            <FilesBoard
                board={filesBoard}
                href={(id) => `/provider/files/${id}`}
            />,
        );
        expect(
            screen.getByText('Next: Option period ends, Nov 27'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Open the file for 1302 Live Oak St',
            }),
        ).toHaveAttribute('href', `/provider/files/${filesBoard.files[0]?.id}`);
        unmount();
        render(
            <FilesBoard
                board={{
                    counts: { active: 0, due_this_week: 0, missing: 0 },
                    files: [],
                }}
                href={() => '#'}
            />,
        );
        expect(screen.getByText('No files yet')).toBeInTheDocument();
    });

    it('ticks a date, hides parts not shared, and sends the drafted message', async () => {
        api.putJson.mockResolvedValue({ ticked: true });
        api.postJson.mockResolvedValue({ data: { conversation: 'c1' } });
        render(
            <FilePanel
                file={fileDetail}
                routes={{
                    tick: '/t',
                    message: '/m',
                    chat: (c) => `/messages?c=${c}`,
                }}
            />,
        );

        expect(
            screen.getByText("James Miller hasn't shared this part."),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByLabelText('Option period ends · Nov 27'),
        );
        expect(api.putJson).toHaveBeenCalledWith('/t', {
            item: '11',
            done: true,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Send message' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/m', {
            body: fileDetail.draft,
        });
        expect(
            await screen.findByRole('link', { name: 'Open the chat' }),
        ).toHaveAttribute('href', '/messages?c=c1');
    });
});
