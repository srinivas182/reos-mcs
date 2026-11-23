import {
    act,
    render as baseRender,
    screen,
    waitFor,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { counting, question } from '@/components/player/fixtures';
import { i18n } from '@/lib/i18n';
import type { PlayerState } from '@/lib/seat-time';
import LessonPage from '@/pages/learn/lesson';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/learn/lessons/7', props: { errors: {} } }),
}));

const lesson = {
    id: 7,
    title: 'Agency disclosure',
    language: 'en' as const,
    minutes: 120,
    course: { id: 3, title: 'Texas Agency Law Update' },
    sections: [{ heading: 'Disclosure', text: 'Disclose agency early.' }],
    documents: [],
    video: null,
    transcript: [],
    captions: null,
    previous: null,
    next: 8,
};

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const reply = (body: unknown) =>
    Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));

describe('course player (screen 5, A8.2)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('sends a heartbeat and shows what has counted, and why not', async () => {
        fetchMock.mockImplementation(() =>
            reply({
                data: { ...counting, reason: 'hidden' } satisfies PlayerState,
            }),
        );
        render(
            <LessonPage
                lesson={lesson}
                at={{ page: null, seconds: null, heading: null }}
                player={{ ...counting, counted: 0, percent: 0 }}
            />,
        );

        expect(
            await screen.findByText('1 h 12 m of 2 h counted'),
        ).toBeInTheDocument();
        expect(screen.getByText(/This tab isn't in front/)).toBeInTheDocument();
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/learn/lessons/7/heartbeat');
        expect(JSON.parse(init.body as string)).toMatchObject({
            playing: true,
            visible: true,
            position: 0,
        });
    });

    it('stops for a check-in until it is answered, and explains a wrong answer', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation((url: string) =>
            url.endsWith('/checkins')
                ? reply({
                      correct: false,
                      explanation:
                          'It is given at the first substantive communication.',
                      source: 'TREC Rule §531.20',
                      state: counting,
                  })
                : reply({ data: { ...counting, checkin: question } }),
        );
        render(
            <LessonPage
                lesson={lesson}
                at={{ page: null, seconds: null, heading: null }}
                player={counting}
            />,
        );

        await screen.findByRole('dialog');
        expect(screen.queryByRole('button', { name: 'Close' })).toBeNull();
        await user.click(screen.getByLabelText('At closing'));
        await user.click(screen.getByRole('button', { name: 'Answer' }));

        expect(
            await screen.findByText(/You're still counted as present/),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Source: TREC Rule §531.20'),
        ).toBeInTheDocument();
        const sent = fetchMock.mock.calls.find(([u]) =>
            String(u).endsWith('/checkins'),
        ) as [string, RequestInit];
        expect(JSON.parse(sent[1].body as string)).toEqual({
            key: 'checkin:12',
            choice: 2,
        });

        await user.click(screen.getByRole('button', { name: 'Continue' }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    });

    it('says when it cannot reach REOS', async () => {
        fetchMock.mockImplementation(() =>
            Promise.reject(new Error('offline')),
        );
        render(
            <LessonPage
                lesson={lesson}
                at={{ page: null, seconds: null, heading: null }}
                player={counting}
            />,
        );

        expect(await screen.findByRole('alert')).toHaveTextContent(
            /couldn't reach REOS/,
        );
        await act(async () => {});
    });
});
