import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Messages from '@/pages/messages/index';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({
        url: '/messages',
        props: { errors: {}, messages: { unread: 1, user: 7 } },
    }),
}));

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);
vi.stubGlobal(
    'fetch',
    vi.fn(() => Promise.resolve(new Response(JSON.stringify({ unread: 0 })))),
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const soon = new Date(Date.now() + 4 * 60_000).toISOString();

const props = {
    inbox: [
        {
            id: 'c-1',
            kind: 'direct' as const,
            title: 'Linda Park',
            people: [{ id: 2, name: 'Linda Park' }],
            last_message: {
                body: 'On it.',
                sender: 'Linda Park',
                deleted: false,
                at: new Date().toISOString(),
            },
            unread: 1,
            updated_at: null,
        },
        {
            id: 'c-2',
            kind: 'group' as const,
            title: 'Friday huddle',
            people: [],
            last_message: {
                body: null,
                sender: 'Ana Okoye',
                deleted: true,
                at: new Date().toISOString(),
            },
            unread: 0,
            updated_at: null,
        },
    ],
    conversation: {
        id: 'c-1',
        kind: 'direct' as const,
        title: 'Linda Park',
        people: [{ id: 2, name: 'Linda Park' }],
        reads: [
            { user: 2, read_at: new Date(Date.now() + 60_000).toISOString() },
        ],
        older: false,
        messages: [
            {
                id: 'm-1',
                body: 'Can you look at the Elm St offer?',
                sender: { id: 7, name: 'James Miller' },
                reply_to: null,
                edited: true,
                deleted: false,
                created_at: new Date().toISOString(),
                changeable_until: soon,
            },
            {
                id: 'm-2',
                body: 'On it.',
                sender: { id: 2, name: 'Linda Park' },
                reply_to: {
                    id: 'm-1',
                    body: 'Can you look at the Elm St offer?',
                    sender: 'James Miller',
                    deleted: false,
                },
                edited: false,
                deleted: false,
                created_at: new Date().toISOString(),
                changeable_until: null,
            },
        ],
    },
    contacts: [
        {
            id: 2,
            name: 'Linda Park',
            role: 'broker',
            organisation: 'Lone Star Realty',
        },
    ],
};

describe('Messages (web, B6.1)', () => {
    beforeEach(() => {
        router.post.mockClear();
        router.reload.mockClear();
    });

    it('lists conversations with unread counts and shows the open one', () => {
        render(<Messages {...props} />);
        const list = screen.getByRole('list', { name: 'Conversations' });
        expect(
            within(list).getByLabelText('1 unread message'),
        ).toBeInTheDocument();
        expect(
            within(list).getByText('This message was deleted.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { level: 2, name: 'Linda Park' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Edited')).toBeInTheDocument();
        expect(screen.getByText('Read')).toBeInTheDocument();
        // Edit and delete only on your own message.
        expect(
            screen.getAllByRole('button', { name: /Delete for everyone/ }),
        ).toHaveLength(1);
    });

    it('marks the open conversation read', () => {
        render(<Messages {...props} />);
        expect(fetch).toHaveBeenCalledWith(
            '/messages/c-1/read',
            expect.objectContaining({ method: 'POST' }),
        );
    });

    it('replies with Enter', async () => {
        render(<Messages {...props} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Reply to Linda Park' }),
        );
        expect(screen.getByText('Replying to Linda Park')).toBeInTheDocument();
        await userEvent.type(
            screen.getByLabelText('Message'),
            'Thanks!{Enter}',
        );
        expect(router.post).toHaveBeenCalledWith(
            '/messages/c-1',
            { body: 'Thanks!', reply_to: 'm-2' },
            expect.anything(),
        );
    });

    it('starts a new message with someone you work with', async () => {
        render(<Messages {...props} />);
        await userEvent.click(
            screen.getAllByRole('button', { name: 'New message' })[0],
        );
        const dialog = screen.getByRole('dialog', { name: 'New message' });
        await userEvent.click(within(dialog).getByLabelText(/Linda Park/));
        await userEvent.type(
            within(dialog).getByLabelText('Message'),
            'Hi Linda',
        );
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Send' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/messages',
            { people: [2], title: null, body: 'Hi Linda' },
            expect.anything(),
        );
    });
});
