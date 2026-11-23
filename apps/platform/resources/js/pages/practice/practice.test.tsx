import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CandidateToday } from '@/components/practice/candidate-today';
import { CoachPanel } from '@/components/practice/coach';
import {
    attempt,
    coach,
    drillAttempt,
    hub,
    readinessMap,
    results,
    studyToday,
    topicPlan,
} from '@/components/practice/fixtures';
import { i18n } from '@/lib/i18n';
import PracticeIndex from '@/pages/practice/index';
import FixPage from '@/pages/practice/fix';
import ReadinessPage from '@/pages/practice/readiness';
import ResultsPage from '@/pages/practice/results';
import TestPage from '@/pages/practice/test';
import StudyPlanPage from '@/pages/study-plan';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    visit: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/practice', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('candidate practice (A8.3)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        router.post.mockClear();
        router.put.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it("shows the dashboard: readiness, exam, streak, Continue at its second and today's plan with why", () => {
        render(<CandidateToday study={studyToday} />);

        expect(
            screen.getByRole('img', {
                name: 'Readiness 72%: predicted pass chance of your weaker portion',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText(/112 days to your exam/)).toBeInTheDocument();
        expect(screen.getByText('3-day streak')).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: /Continue: 6.2 Agency disclosure/,
            }),
        ).toHaveAttribute('href', '/learn/lessons/7?t=245');
        expect(
            screen.getByText(/One of your weaker topics/),
        ).toBeInTheDocument();
    });

    it('starts the diagnostic and a topic quiz from the hub', async () => {
        const user = userEvent.setup();
        render(
            <PracticeIndex
                tests={hub}
                readiness={{
                    percent: 72,
                    topics: studyToday.topics,
                    weakest: ['math'],
                    tests: 1,
                }}
                weights={{
                    agency: 9,
                    contracts: 9,
                    ownership: 8,
                    finance: 7,
                    math: 7,
                    state_rules: 10,
                }}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Take it again' }));
        expect(router.post).toHaveBeenCalledWith('/practice/tests', {
            kind: 'diagnostic',
            topic: null,
        });
        await user.click(
            screen.getByRole('button', {
                name: 'Start the Real estate math quiz',
            }),
        );
        expect(router.post).toHaveBeenLastCalledWith('/practice/tests', {
            kind: 'topic',
            topic: 'math',
        });
    });

    it('saves each answer, flags, and warns about blanks before submitting', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(
            async (_url: string, init: RequestInit) => {
                const body = JSON.parse(init.body as string) as {
                    choice: number | null;
                    flagged: boolean;
                };

                return new Response(
                    JSON.stringify({
                        data: { ...attempt.questions[1], ...body },
                    }),
                    { status: 200 },
                );
            },
        );
        render(<TestPage attempt={attempt} />);

        expect(screen.getByRole('timer')).toHaveTextContent('45:00');
        expect(
            screen.getByRole('heading', { name: /loan-to-value/ }),
        ).toBeInTheDocument();
        await user.click(screen.getByLabelText('80%'));
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/practice/attempts/9/answers/102');
        expect(init.method).toBe('PUT');
        expect(JSON.parse(init.body as string)).toEqual({
            choice: 1,
            flagged: false,
        });

        await user.click(screen.getByRole('button', { name: 'Next' }));
        await user.click(
            screen.getAllByRole('button', { name: 'Review and submit' })[0]!,
        );
        const dialog = screen.getByRole('dialog');
        expect(
            within(dialog).getByText(/1 question is not answered/),
        ).toBeInTheDocument();
        expect(
            within(dialog).getByRole('button', {
                name: 'Question 1, answered, flagged',
            }),
        ).toBeInTheDocument();
        await user.click(
            within(dialog).getByRole('button', { name: 'Submit anyway' }),
        );
        expect(router.post).toHaveBeenCalledWith('/practice/attempts/9/submit');
    });

    it('reports topics weakest first with next steps and every answer with why', () => {
        render(
            <ResultsPage
                results={results}
                readiness={{
                    percent: 72,
                    topics: studyToday.topics,
                    weakest: ['math', 'finance'],
                    tests: 1,
                }}
            />,
        );

        expect(screen.getByText('72%')).toBeInTheDocument();
        expect(
            screen.getByText('At or above the 70% pass line'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Next, your plan spends extra time on Real estate math, Finance.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Fix my weakest topics' }),
        ).toHaveAttribute('href', '/study-plan');
        expect(screen.getByText(/Right answer: 80%/)).toBeInTheDocument();
    });

    it('moves a task to another day from the week', async () => {
        const user = userEvent.setup();
        render(
            <StudyPlanPage
                week={{
                    from: '2026-11-23',
                    previous: '2026-11-16',
                    next: '2026-11-30',
                    today: '2026-11-23',
                }}
                days={Array.from({ length: 7 }, (_, i) => ({
                    day: `2026-11-${23 + i}`,
                    minutes: i === 0 ? 20 : 0,
                    tasks: i === 0 ? studyToday.tasks : [],
                }))}
                summary={studyToday}
                calendar={null}
            />,
        );

        expect(
            screen.getByText('Weekly goal: 140 minutes'),
        ).toBeInTheDocument();
        await user.selectOptions(
            screen.getByLabelText('Move Real estate math quiz to'),
            '2026-11-25',
        );
        expect(router.put).toHaveBeenCalledWith(
            '/study-plan/tasks/1',
            { day: '2026-11-25' },
            { preserveScroll: true },
        );
    });
});

describe('Readiness (screens 54 and 44, A9.2)', () => {
    it('shows each portion against the booking line and what booking still needs', () => {
        render(<ReadinessPage map={readinessMap} />);

        expect(
            screen.getByRole('img', {
                name: 'National portion: 84% predicted pass chance',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('6 points to the booking line'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('At or above the 90% booking line'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('1 of 2 recent simulations passed'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'Book the exam' }),
        ).not.toBeInTheDocument();
    });

    it('unlocks booking with the testing vendor at 90% on both', () => {
        render(
            <ReadinessPage
                map={{
                    ...readinessMap,
                    portions: { national: 92, state: 95 },
                    booking: {
                        ...readinessMap.booking,
                        ready: true,
                        national: true,
                        simulations: 2,
                    },
                }}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Book the exam' }),
        ).toHaveAttribute(
            'href',
            'https://www.pearsonvue.com/us/en/tx/realestate.html',
        );
    });

    it('ranks topics weakest first, filters by portion, and practises the weakest', async () => {
        const user = userEvent.setup();
        render(<ReadinessPage map={readinessMap} />);

        expect(screen.getByText('8 of 10 answers so far')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Fixing Finance could add 7 points to your pass chance on the National portion.',
            ),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Texas' }));
        expect(
            screen.queryByRole('button', { name: 'Practice Finance' }),
        ).not.toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'All' }));

        await user.click(
            screen.getByRole('button', { name: 'Fix my weakest' }),
        );
        expect(router.post).toHaveBeenCalledWith('/practice/topic-plans', {
            topic: 'finance',
        });
    });
});

describe('drills and fix plans (screens 38, 46 and 47, A9.2b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        router.post.mockReset();
        router.visit.mockReset();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('starts the smart drill and follows the ranking', async () => {
        const user = userEvent.setup();
        render(<CoachPanel coach={coach} />);

        expect(
            screen.getByText('Brings back 4 questions you missed'),
        ).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Start drill' }));
        expect(router.post).toHaveBeenCalledWith('/practice/tests', {
            kind: 'drill',
        });

        await user.click(
            screen.getByRole('button', {
                name: 'Carry on: Finance (2 of 5 done)',
            }),
        );
        expect(router.visit).toHaveBeenCalledWith('/practice/fix/31');
    });

    it('shows the fix plan against its goal and continues to the next step', async () => {
        const user = userEvent.setup();
        render(<FixPage plan={topicPlan} />);

        expect(screen.getByText('Now: 58%')).toBeInTheDocument();
        expect(
            screen.getByText('Watch: Mortgage insurance'),
        ).toBeInTheDocument();
        expect(screen.getByText('28 minutes left')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Review' })).toHaveAttribute(
            'href',
            '/learn/lessons/7?t=185',
        );

        await user.click(screen.getByRole('button', { name: 'Continue' }));
        expect(router.post).toHaveBeenCalledWith('/practice/fix/31/continue');
    });

    it('keeps the drill streak and strikes out an option with Hint', async () => {
        const user = userEvent.setup();
        fetchMock.mockResolvedValue(
            new Response(JSON.stringify({ data: { eliminated: 3 } }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        render(<TestPage attempt={drillAttempt} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Smart drill' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Streak: 2 right in a row'),
        ).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Hint' }));
        expect(fetchMock).toHaveBeenCalledWith(
            '/practice/attempts/14/questions/103/hint',
            expect.objectContaining({ method: 'POST' }),
        );
        expect(await screen.findByText(/Not this one:/)).toBeInTheDocument();
        expect(screen.getAllByRole('radio')[3]).toBeDisabled();
    });
});
