import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { iabsThread, limitedAnswer } from '@/components/ask/fixtures';
import { i18n } from '@/lib/i18n';
import AskPage from '@/pages/ask/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/ask', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (data: unknown) =>
    new Response(JSON.stringify({ data }), {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
    });
const pack = {
    candidate: true,
    beyond_course: false,
    sponsor: null,
    credits_left: null,
};

describe('Ask on the web (candidate screen 13, A10.1)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('invites a first question, then shows the answer from the course with the lesson to watch', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(async () => reply(iabsThread));
        render(
            <AskPage threads={[]} thread={null} package={pack} state="Texas" />,
        );

        expect(
            screen.getByText('Ask anything about the exam'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Your study mate: answers from your courses, with the lesson to watch. Always free.',
            ),
        ).toBeInTheDocument();
        await user.type(
            screen.getByLabelText('Your question'),
            'When is the IABS form given?',
        );
        await user.click(screen.getByRole('button', { name: 'Ask' }));

        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/ask');
        expect(JSON.parse(init.body as string)).toEqual({
            mode: 'ask',
            question: 'When is the IABS form given?',
            thread: null,
        });
        expect(
            await screen.findByText('From your course content · Texas'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Watch at 3:20' }),
        ).toHaveAttribute('href', '/learn/lessons/7?t=200');
        expect(router.reload).toHaveBeenCalledWith({ only: ['threads'] });

        await user.click(
            screen.getByRole('button', { name: 'Explain simpler' }),
        );
        expect(
            JSON.parse(
                (fetchMock.mock.calls[1] as [string, RequestInit])[1]
                    .body as string,
            ),
        ).toEqual({ mode: 'simpler', question: null, thread: iabsThread.uuid });
    });

    it('says plainly when the course does not cover a question, with no follow-ups or price', () => {
        render(
            <AskPage
                threads={[iabsThread]}
                thread={{
                    ...iabsThread,
                    messages: [iabsThread.messages[0]!, limitedAnswer],
                }}
                package={pack}
                state="Texas"
            />,
        );

        expect(
            screen.getByText('Not covered in your course'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Quiz me' }),
        ).not.toBeInTheDocument();
        expect(screen.queryByText(/credit/i)).not.toBeInTheDocument();
    });
});
