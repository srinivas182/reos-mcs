import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { accounts, check, post, postList } from '@/components/social/fixtures';
import { generation, maker, video } from '@/components/studio/fixtures';
import { i18n } from '@/lib/i18n';
import StudioPage from '@/pages/studio/index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/studio', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the content studio (B11.1)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async (url: string) => {
            const data = url.includes('/check')
                ? check()
                : url.includes('/studio/videos')
                  ? video({ status: 'rendering', video: null })
                  : url.includes('/studio/generations')
                    ? generation()
                    : url.includes('/studio/posts')
                      ? post({ status: 'publishing' })
                      : null;
            const body =
                url === '/studio' ? { props: { posts: postList() } } : { data };
            return new Response(JSON.stringify(body), {
                status: url.endsWith('/studio/posts') ? 201 : 200,
                headers: { 'Content-Type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        window.history.replaceState({}, '', '/studio');
    });

    afterEach(() => vi.unstubAllGlobals());

    it('checks the post, publishes it to the chosen accounts and shows results', async () => {
        const user = userEvent.setup();
        render(
            <StudioPage
                accounts={accounts()}
                posts={postList()}
                listing={null}
                maker={maker()}
            />,
        );

        expect(screen.getByText('Partly published')).toBeInTheDocument();
        await user.type(
            screen.getByLabelText('Post text'),
            'Market update for Austin',
        );
        await user.click(screen.getByRole('switch', { name: /Facebook/ }));
        await waitFor(() =>
            expect(
                screen.getByText('James Miller · Lone Star Realty'),
            ).toBeInTheDocument(),
        );
        await user.click(screen.getByRole('button', { name: 'Publish now' }));

        await waitFor(() =>
            expect(
                fetchMock.mock.calls.some(
                    ([u, i]) =>
                        u === '/studio/posts' &&
                        (i as RequestInit).method === 'POST',
                ),
            ).toBe(true),
        );
        const [, init] = fetchMock.mock.calls.find(
            ([u]) => u === '/studio/posts',
        ) as [string, RequestInit];
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toMatchObject({
            body: 'Market update for Austin',
            targets: [{ connection: 4, channel: 'facebook', id: 'p1' }],
            when: 'now',
        });
    });

    it('makes a listing post and puts the chosen design and caption in the composer (B12.1)', async () => {
        const user = userEvent.setup();
        render(
            <StudioPage
                accounts={accounts()}
                posts={postList()}
                listing={null}
                maker={maker()}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Make' }));
        await waitFor(() =>
            expect(screen.getByText('Pick a version')).toBeInTheDocument(),
        );
        await user.click(screen.getByRole('button', { name: 'Use this' }));
        await waitFor(() =>
            expect(screen.getByLabelText('Post text')).toHaveValue(
                generation().captions[0].text,
            ),
        );
    });

    it('makes a listing video, and puts a ready one in the composer (B12.2)', async () => {
        const user = userEvent.setup();
        render(
            <StudioPage
                accounts={accounts()}
                posts={postList()}
                listing={null}
                maker={maker({ videos: [video()] })}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Listing video' }));
        await waitFor(() =>
            expect(
                screen.getByText('Rendering your video…'),
            ).toBeInTheDocument(),
        );
        const [, init] = fetchMock.mock.calls.find(
            ([u]) => u === '/studio/videos',
        ) as [string, RequestInit];
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toEqual({
            source: {
                type: 'listing',
                id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e66',
            },
        });

        await user.click(screen.getByRole('button', { name: 'Use in a post' }));
        await waitFor(() =>
            expect(screen.getByLabelText('Post text')).toHaveValue(
                'Step inside this Katy home',
            ),
        );
    });
});
