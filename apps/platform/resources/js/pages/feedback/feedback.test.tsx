import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { viewFixture } from '@/components/feedback/fixtures';
import { i18n } from '@/lib/i18n';
import FeedbackPage from '@/pages/feedback/index';

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
    put: vi.fn((_u: string, _d: unknown, o?: { onFinish?: () => void }) =>
        o?.onFinish?.(),
    ),
    undo: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { post, put },
}));
vi.mock('@/lib/undo-toast', () => ({ undoToast: undo }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('feedback from agents on the web (A13.3b)', () => {
    afterEach(() => vi.clearAllMocks());

    it("rates the other side's agent", async () => {
        const view = viewFixture();
        render(<FeedbackPage {...view} />);
        for (const group of screen.getAllByRole('radiogroup')) {
            await userEvent.click(
                within(group).getByRole('radio', { name: '5 stars' }),
            );
        }
        await userEvent.type(
            screen.getByLabelText('Comment (optional)'),
            'Great to work with.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send feedback' }),
        );
        expect(post).toHaveBeenCalledWith(
            '/feedback',
            {
                deal: view.to_rate[0].deal,
                agent: 21,
                communication: 5,
                responsiveness: 5,
                professionalism: 5,
                comment: 'Great to work with.',
            },
            expect.anything(),
        );
    });

    it('reports with Undo and shows the average publicly', async () => {
        render(<FeedbackPage {...viewFixture()} />);
        await userEvent.click(
            screen.getAllByRole('button', { name: 'Report' })[0],
        );
        await userEvent.type(
            screen.getByLabelText('Why are you reporting this?'),
            'Not about the deal.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Report it' }),
        );
        expect(post).toHaveBeenCalledWith(
            '/feedback/f1/report',
            { reason: 'Not about the deal.' },
            expect.anything(),
        );
        expect(undo).toHaveBeenCalledWith(
            expect.objectContaining({
                message:
                    "Reported. It's left out of your average while REOS support looks.",
            }),
        );
        await userEvent.click(
            screen.getByRole('switch', {
                name: 'Show my average on my agent page',
            }),
        );
        expect(put).toHaveBeenCalledWith(
            '/feedback/visibility',
            { public: true },
            expect.anything(),
        );
    });
});
