import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    madeFixture,
    momentsFixture,
} from '@/components/candidate-studio/fixtures';
import { i18n } from '@/lib/i18n';
import CandidateStudioPage from '@/pages/candidate-studio/index';

const { postJson } = vi.hoisted(() => ({ postJson: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
}));
vi.mock('@/lib/content', () => ({ postJson }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the candidate studio on the web (B13.3)', () => {
    afterEach(() => vi.clearAllMocks());

    it('makes a pass post, checks it and posts it', async () => {
        postJson.mockImplementation(async (url: string) => {
            if (url.includes('/moments/')) return { data: madeFixture() };
            if (url.endsWith('/check'))
                return { data: { ok: true, issues: [] } };

            return { data: { id: 'p-1', status: 'publishing' } };
        });
        render(
            <CandidateStudioPage
                moments={momentsFixture()}
                targets={[
                    {
                        connection: 3,
                        channel: 'facebook',
                        id: 'page-1',
                        name: 'Jordan Reyes',
                    },
                ]}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Make the post' }),
        );
        expect(postJson).toHaveBeenCalledWith(
            '/candidate-studio/moments/exam_pass',
        );
        await userEvent.click(
            await screen.findByRole('button', { name: 'Check' }),
        );
        expect(await screen.findByText('Ready to share.')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Post now' }));
        await waitFor(() =>
            expect(postJson).toHaveBeenCalledWith(
                '/candidate-studio/posts/c-1',
                expect.objectContaining({
                    caption: 0,
                    design: 0,
                    targets: [
                        { connection: 3, channel: 'facebook', id: 'page-1' },
                    ],
                }),
            ),
        );
    });
});
