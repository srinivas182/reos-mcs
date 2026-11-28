import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { FilePanel, FilesBoard } from './files-board';
import { dealCoordinators, fileDetail, filesBoard } from './fixtures';
import { SharePanel } from './share-panel';

const routes = { list: '#', share: '#', update: () => '#', remove: () => '#' };

const meta = {
    title: 'Coordinators/Share a deal and My files',
    component: SharePanel,
    args: { routes, closed: false, initial: dealCoordinators },
} satisfies Meta<typeof SharePanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** James shared 1302 Live Oak St with Tina Brooks: documents and dates on. */
export const Shared: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('switch', {
                name: 'Parties and contacts for Tina Brooks',
            }),
        ).not.toBeChecked();
    },
};

/** No coordinators connected yet. */
export const NoCoordinators: Story = {
    args: { initial: { coordinators: [], shares: [] } },
};

/** The deal closed: access ended, nothing new can be shared. */
export const Closed: Story = {
    args: {
        closed: true,
        initial: {
            ...dealCoordinators,
            shares: dealCoordinators.shares.map((s) => ({
                ...s,
                status: 'ended',
                end_reason: s.end_reason ?? 'closed',
            })),
        },
    },
};

/** Tina's files, nearest deadline first. */
export const MyFiles: Story = {
    render: () => <FilesBoard board={filesBoard} href={() => '#'} />,
};

/** No files yet. */
export const NoFiles: Story = {
    render: () => (
        <FilesBoard
            board={{
                counts: { active: 0, due_this_week: 0, missing: 0 },
                files: [],
            }}
            href={() => '#'}
        />
    ),
};

/** One file: dates and documents shared, parties not; a drafted message. */
export const OneFile: Story = {
    render: () => (
        <FilePanel
            file={fileDetail}
            routes={{ tick: '#', message: '#', chat: () => '#' }}
        />
    ),
};
