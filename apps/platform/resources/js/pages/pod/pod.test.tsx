import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { challengeRows, podView } from '@/components/pods/fixtures';
import { i18n } from '@/lib/i18n';
import BrokerChallengesPage from '@/pages/organisation/challenges';
import PodPage from '@/pages/pod/index';

const { post, put, undo } = vi.hoisted(() => ({
    post: vi.fn(
        (
            _u: string,
            _d: unknown,
            o?: { onSuccess?: () => void; onFinish?: () => void },
        ) => {
            o?.onSuccess?.();
            o?.onFinish?.();
        },
    ),
    put: vi.fn(
        (
            _u: string,
            _d: unknown,
            o?: { onSuccess?: () => void; onFinish?: () => void },
        ) => {
            o?.onSuccess?.();
            o?.onFinish?.();
        },
    ),
    undo: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { post, put },
}));
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
}));
vi.mock('@/lib/undo-toast', () => ({ undoToast: undo }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('pod and challenges on the web (A13.2)', () => {
    afterEach(() => vi.clearAllMocks());

    it('shows the pod, links the call and messages, and leaves with Undo', async () => {
        render(<PodPage pod={podView()} challenges={challengeRows()} />);
        expect(screen.getByRole('link', { name: 'Join call' })).toHaveAttribute(
            'href',
            '/sessions/1c2d3e4f-5a6b-4c7d-8e9f-0a1b2c3d4e5f',
        );
        expect(
            screen
                .getByRole('link', { name: 'Message pod' })
                .getAttribute('href'),
        ).toContain('c=7f6e5d4c');
        await userEvent.click(
            screen.getByRole('button', { name: 'Leave pod' }),
        );
        expect(post).toHaveBeenCalledWith('/pod/leave', {}, expect.anything());
        expect(undo).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'You left the pod.' }),
        );
    });

    it('saves the goal and joins a challenge', async () => {
        render(<PodPage pod={podView()} challenges={challengeRows()} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Save goal' }),
        );
        expect(put).toHaveBeenCalledWith(
            '/pod/goal',
            { text: '20 conversations and 2 appointments' },
            expect.anything(),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Join' }));
        expect(post).toHaveBeenCalledWith(
            '/brokerage-challenges/5b6c7d8e-9f0a-4b1c-8d2e-3f4a5b6c7d8e/join',
            {},
            expect.anything(),
        );
    });

    it('offers to join when not in a pod', async () => {
        render(
            <PodPage
                pod={{ status: 'none', waiting_since: null, pod: null }}
                challenges={[]}
            />,
        );
        expect(screen.getByText('No challenges right now')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Join a pod' }),
        );
        expect(post).toHaveBeenCalledWith('/pod/join', {}, expect.anything());
    });

    it('lets the broker create a challenge', async () => {
        render(
            <BrokerChallengesPage
                brokerage="Lone Star Realty"
                metrics={['conversations', 'posts']}
                challenges={challengeRows()}
            />,
        );
        await userEvent.type(
            screen.getByLabelText('Name'),
            'December conversations',
        );
        await userEvent.type(screen.getByLabelText('Starts'), '2026-12-01');
        await userEvent.type(screen.getByLabelText('Ends'), '2026-12-31');
        await userEvent.click(
            screen.getByRole('button', { name: 'Create challenge' }),
        );
        expect(post).toHaveBeenCalledWith(
            '/organisation/challenges',
            {
                title: 'December conversations',
                metric: 'conversations',
                starts_on: '2026-12-01',
                ends_on: '2026-12-31',
            },
            expect.anything(),
        );
    });
});
