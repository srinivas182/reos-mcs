import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CandidateToday } from '@/components/practice/candidate-today';
import { CoachPanel } from '@/components/practice/coach';
import { burstQueue } from '@/components/practice/flashcard-session';
import { SaveFlashcard } from '@/components/practice/save-flashcard';
import {
    attempt,
    coach,
    deck,
    drillAttempt,
    hub,
    mathKinds,
    mathProblem,
    readinessMap,
    results,
    studyToday,
    topicPlan,
} from '@/components/practice/fixtures';
import { i18n } from '@/lib/i18n';
import PracticeIndex from '@/pages/practice/index';
import FixPage from '@/pages/practice/fix';
import FlashcardsPage from '@/pages/practice/flashcards';
import SpeedPage from '@/pages/practice/speed';
import MathPage from '@/pages/practice/math';
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

describe('math lab (screen 48, A9.2c)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    const reply = (data: unknown, status = 200) =>
        new Response(JSON.stringify({ data }), {
            status,
            headers: { 'Content-Type': 'application/json' },
        });

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('works a problem step by step: hint when wrong, the answer when right, then a variant', async () => {
        const user = userEvent.setup();
        fetchMock
            .mockResolvedValueOnce(reply(mathProblem, 201))
            .mockResolvedValueOnce(
                reply({
                    correct: false,
                    hint: 'Lenders use the lower of the price and the appraisal.',
                    answer: null,
                    solved: false,
                }),
            )
            .mockResolvedValueOnce(
                reply({
                    correct: true,
                    hint: null,
                    answer: '$318,000',
                    solved: false,
                }),
            );
        render(<MathPage kinds={mathKinds} problem={null} />);

        expect(screen.getByText('5 solved')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Start: Loan-to-value' }),
        );
        expect(
            await screen.findByText(/The home appraises at \$318,000/),
        ).toBeInTheDocument();

        await user.type(
            screen.getByLabelText('The value the lender uses'),
            '325000',
        );
        await user.click(screen.getByRole('button', { name: 'Check step' }));
        expect(
            await screen.findByText(
                'Lenders use the lower of the price and the appraisal.',
            ),
        ).toBeInTheDocument();
        expect(JSON.parse(String(fetchMock.mock.calls[1]?.[1]?.body))).toEqual({
            kind: 'ltv',
            seed: 4711,
            step: 1,
            value: '325000',
        });

        await user.clear(screen.getByLabelText('The value the lender uses'));
        await user.type(
            screen.getByLabelText('The value the lender uses'),
            '318,000',
        );
        await user.click(screen.getByRole('button', { name: 'Check step' }));
        expect(await screen.findByText('Right: $318,000')).toBeInTheDocument();

        // Wrong once, then Show the answer.
        fetchMock.mockReset();
        fetchMock
            .mockResolvedValueOnce(
                reply({
                    correct: false,
                    hint: 'Multiply that value by the loan-to-value ratio.',
                    answer: null,
                    solved: false,
                }),
            )
            .mockResolvedValueOnce(
                reply({
                    correct: false,
                    hint: null,
                    answer: '$302,100',
                    solved: false,
                }),
            )
            .mockResolvedValueOnce(
                reply({
                    correct: true,
                    hint: null,
                    answer: '$22,900',
                    solved: true,
                }),
            )
            .mockResolvedValueOnce(reply({ ...mathProblem, seed: 99 }, 201));
        await user.type(
            screen.getByLabelText('The most the lender will lend'),
            '300000',
        );
        await user.click(screen.getByRole('button', { name: 'Check step' }));
        await user.click(
            await screen.findByRole('button', { name: 'Show the answer' }),
        );
        expect(
            await screen.findByText('The answer: $302,100'),
        ).toBeInTheDocument();

        await user.type(
            screen.getByLabelText("The buyer's down payment"),
            '22900',
        );
        await user.click(screen.getByRole('button', { name: 'Check step' }));
        expect(await screen.findByText('Solved')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'New numbers, same kind' }),
        );
        expect(JSON.parse(String(fetchMock.mock.calls[3]?.[1]?.body))).toEqual({
            kind: 'ltv',
        });
    });
});

describe('flashcards and the speed round (screens 31 and 50, A9.2d)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    const reply = (data: unknown, status = 200) =>
        new Response(JSON.stringify({ data }), {
            status,
            headers: { 'Content-Type': 'application/json' },
        });

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('works through cards due, then new ones; Again brings a card back in the burst', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(() =>
            Promise.resolve(reply({ applied: 1, cards: [] })),
        );
        expect(
            burstQueue(deck, Date.parse('2026-11-23T16:00:00Z')).map(
                (c) => c.id,
            ),
        ).toEqual([61, 62, 63]);
        render(<FlashcardsPage deck={deck} topic={null} />);

        await user.click(
            screen.getByRole('button', { name: 'Start a 2-minute burst' }),
        );
        expect(
            screen.getByText('When must borrower-paid PMI end automatically?'),
        ).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Show the answer' }),
        );
        await user.click(screen.getByRole('button', { name: 'Again' }));
        const body = JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body));
        expect(body.reviews[0]).toMatchObject({ card_id: 61, rating: 1 });
        expect(body.reviews[0].client_id).toMatch(/^[0-9a-f-]{36}$/);

        expect(
            screen.getByText('What does IABS stand for?'),
        ).toBeInTheDocument();
        expect(screen.getByText('Card 2 · 2 to go')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Show the answer' }),
        );
        await user.click(screen.getByRole('button', { name: 'Got it' }));
        await user.click(
            screen.getByRole('button', { name: 'Show the answer' }),
        );
        await user.click(screen.getByRole('button', { name: 'Hard' }));
        // The missed card comes back.
        expect(
            screen.getByText('When must borrower-paid PMI end automatically?'),
        ).toBeInTheDocument();
    });

    it('saves a card from answer review', async () => {
        const user = userEvent.setup();
        fetchMock.mockResolvedValue(reply({ id: 70 }, 201));
        render(
            <SaveFlashcard
                source="review"
                front="What does IABS stand for?"
                back="Information About Brokerage Services."
                questionId={12}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Save as flashcard' }),
        );
        expect(
            await screen.findByText('Saved to your flashcards.'),
        ).toBeInTheDocument();
        expect(JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))).toEqual({
            source: 'review',
            front: 'What does IABS stand for?',
            back: 'Information About Brokerage Services.',
            question_id: 12,
        });
    });

    it("plays a speed round: true or false, the score, then the server's result", async () => {
        const user = userEvent.setup();
        fetchMock
            .mockResolvedValueOnce(
                reply(
                    {
                        id: 5,
                        seconds: 60,
                        best: 27,
                        statements: [
                            {
                                id: 1,
                                prompt: 'How many acres are in a section?',
                                answer: '640',
                                truth: true,
                                topic: 'math',
                            },
                            {
                                id: 2,
                                prompt: 'Who appoints TREC members?',
                                answer: 'The voters',
                                truth: false,
                                topic: 'state_rules',
                            },
                        ],
                    },
                    201,
                ),
            )
            .mockResolvedValueOnce(
                reply({
                    score: 1,
                    answered: 2,
                    best: 27,
                    record: false,
                    misses: 1,
                }),
            );
        render(<SpeedPage best={27} />);

        expect(screen.getByText('Best: 27')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Start' }));
        expect(await screen.findByText('Answer: 640')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'True' }));
        expect(screen.getByText('Score: 1')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'True' }));

        expect(await screen.findByText("Time's up")).toBeInTheDocument();
        expect(JSON.parse(String(fetchMock.mock.calls[1]?.[1]?.body))).toEqual({
            taps: [
                { id: 1, true: true },
                { id: 2, true: true },
            ],
        });
        expect(
            screen.getByText('1 miss saved to your flashcards.'),
        ).toBeInTheDocument();
    });
});
