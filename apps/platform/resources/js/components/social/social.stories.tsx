import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { AccountsList } from './accounts-list';
import { Composer } from './composer';
import { accounts, check, draft, postList } from './fixtures';
import { PostsList } from './posts-list';

const meta = {
    title: 'Social/Content studio',
    component: Composer,
    args: {
        accounts: accounts(),
        draft: draft(),
        listing: null,
        check: check(),
        onCheck: fn(),
        onUpload: fn(async () => ({
            value: 'social/1/a.jpg',
            preview: '/demo/listings/pecan-1.svg',
            kind: 'image',
        })),
        onSubmit: fn(),
    },
} satisfies Meta<typeof Composer>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Publish or schedule (screen 108): accounts with switches, the broker name on the preview, and checks. */
export const Publish: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('James Miller · Lone Star Realty'),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('switch', { name: /Instagram/ }),
        );
        await userEvent.click(
            canvas.getByRole('button', { name: 'Publish now' }),
        );
        await expect(args.onSubmit).toHaveBeenCalled();
    },
};

/** A post that fails a check can't be scheduled until it's fixed. */
export const FailsACheck: Story = {
    args: {
        check: check({
            ok: false,
            checks: [
                ...check().checks.slice(0, 3),
                { code: 'media', ok: false, detail: 'instagram' },
                { code: 'title', ok: false, detail: null },
            ],
        }),
        draft: draft({
            when: 'later',
            body: 'As your broker I will sell your home',
            picks: [{ connection: 4, channel: 'instagram', id: 'ig1' }],
        }),
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByRole('button', { name: 'Schedule post' }),
        ).toBeDisabled();
    },
};

/** My posts and results (screen 169). */
export const Results: Story = {
    render: () => (
        <PostsList
            posts={postList()}
            onEdit={fn()}
            onRemove={fn()}
            onRepost={fn()}
        />
    ),
};

/** Social accounts (screen 170). */
export const Accounts: Story = {
    render: () => (
        <AccountsList
            accounts={accounts()}
            connectUrl="/connections"
            onRefresh={fn()}
        />
    ),
};
