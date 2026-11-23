import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { journey } from '@/components/licence/fixtures';
import { i18n } from '@/lib/i18n';
import ExamDayPage from '@/pages/licence/exam-day';
import LicenceJourneyPage from '@/pages/licence/index';
import ResultPage from '@/pages/licence/result';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    post: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/licence-journey', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
const bodyOf = (call: unknown[] | undefined) => {
    const body = (call?.[1] as RequestInit | undefined)?.body;

    return JSON.parse(typeof body === 'string' ? body : '{}') as Record<
        string,
        unknown
    >;
};

describe('the licence journey (A10.3a)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async () => reply({ data: journey() }));
        vi.stubGlobal('fetch', fetchMock);
        // Radix checkboxes measure themselves; jsdom has no ResizeObserver.
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        router.reload.mockClear();
        router.visit.mockClear();
        router.post.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('shows the window and steps, checks a step off, and saves the exam date', async () => {
        const user = userEvent.setup();
        render(<LicenceJourneyPage journey={journey()} />);

        expect(
            screen.getByText('344 days left, until Tue, Nov 2'),
        ).toBeInTheDocument();
        expect(screen.getByText("You're ready to book")).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Book with Pearson VUE' }),
        ).toHaveAttribute(
            'href',
            'https://www.pearsonvue.com/us/en/tx/realestate.html',
        );

        await user.click(
            screen.getAllByRole('button', { name: 'Mark done' })[0]!,
        );
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/steps/fingerprints',
        );
        expect(bodyOf(fetchMock.mock.calls[0])).toEqual({
            done: true,
            on: null,
        });

        await user.clear(screen.getByLabelText('Test centre'));
        await user.type(
            screen.getByLabelText('Test centre'),
            'Pearson VUE Katy',
        );
        await user.click(
            screen.getByRole('button', { name: 'Save exam date' }),
        );
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(bodyOf(fetchMock.mock.calls[1])).toEqual({
            exam_on: '2026-12-14',
            time: '08:30',
            centre: 'Pearson VUE Katy',
        });
    });

    it('offers the retake plan after a missed portion', async () => {
        const user = userEvent.setup();
        render(
            <LicenceJourneyPage
                journey={journey({
                    retake: {
                        portions: ['state'],
                        topics: ['state_rules'],
                        more_education: false,
                        attempts_left: { state: 2 },
                    },
                })}
            />,
        );

        expect(
            screen.getByText('State portion: 2 attempts left.'),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Start' }));
        expect(router.post).toHaveBeenCalledWith('/practice/topic-plans', {
            topic: 'state_rules',
        });
    });

    it('ticks the exam-day checklist', async () => {
        const user = userEvent.setup();
        render(<ExamDayPage journey={journey()} />);

        expect(screen.getByText(/two current IDs/)).toBeInTheDocument();
        await user.click(
            screen.getByRole('checkbox', { name: 'Exam confirmation' }),
        );
        await waitFor(() => expect(router.reload).toHaveBeenCalled());
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/licence-journey/kit/confirmation',
        );
        expect(
            screen.getByRole('link', { name: 'Directions' }),
        ).toBeInTheDocument();
    });

    it('enters a result with the weak areas from the score report', async () => {
        const user = userEvent.setup();
        render(
            <ResultPage
                journey={journey({
                    results: { ...journey().results, can_enter: true },
                })}
            />,
        );

        const state = screen.getByRole('group', { name: 'State portion' });
        await user.click(state.querySelectorAll('input')[1]!);
        await user.click(screen.getByRole('checkbox', { name: 'Texas rules' }));
        await user.click(screen.getByRole('button', { name: 'Save result' }));

        await waitFor(() =>
            expect(router.visit).toHaveBeenCalledWith('/licence-journey'),
        );
        expect(bodyOf(fetchMock.mock.calls[0])).toEqual({
            national: true,
            state: false,
            weak_topics: ['state_rules'],
        });
    });
});
