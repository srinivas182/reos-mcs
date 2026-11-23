import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    aiLessons,
    disputes,
    questions,
    reviewItems,
    reviewLesson,
    reviewLines,
    reviewVersion,
    reviewVideos,
    starterBank,
} from '@/components/review/fixtures';
import { i18n } from '@/lib/i18n';
import AiLessons from '@/pages/builder/ai-lessons';
import QuestionBankPage from '@/pages/builder/questions';
import LessonReviewPage from '@/pages/builder/review';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/ai-lessons', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const review = (
    blockers: { text: string }[] = [{ text: '1 line has a legal flag' }],
) =>
    render(
        <LessonReviewPage
            lesson={reviewLesson}
            version={reviewVersion}
            versions={[
                {
                    version: 2,
                    status: 'ready',
                    review_state: 'in_review',
                    created_at: null,
                },
                {
                    version: 1,
                    status: 'ready',
                    review_state: 'in_review',
                    created_at: null,
                },
            ]}
            lines={reviewLines}
            videos={reviewVideos}
            items={reviewItems}
            blockers={blockers}
            pending_rerecord={1}
        />,
    );

beforeEach(() => Object.values(router).forEach((fn) => fn.mockClear()));

describe('AI lesson review (screen 44, A8.1)', () => {
    it('shows each line with its source, flags with the legal source, and blocks publishing', () => {
        review();

        expect(
            screen.getByText('Agency module script.pdf, page 3'),
        ).toBeInTheDocument();
        expect(
            screen.getAllByText(/Promises a result .*22 TAC §535.155/),
        ).toHaveLength(2);
        expect(screen.getByText('1 line has a legal flag')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Approve and publish' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Approve: Line 2' }),
        ).toBeDisabled();
    });

    it('uses a suggestion, records again and approves when nothing blocks', async () => {
        const user = userEvent.setup();
        review([]);

        await user.click(
            screen.getByRole('button', { name: 'Use suggestion: Line 2' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/23/review/lines/1',
            { action: 'use_suggestion' },
            expect.anything(),
        );
        await user.click(
            screen.getByRole('button', { name: 'Record again (1)' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/23/review/render',
        );
        await user.click(
            screen.getByRole('button', { name: 'Approve and publish' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/23/review/publish',
        );
    });

    it('edits a line in both languages and sends back with a note', async () => {
        const user = userEvent.setup();
        review();

        await user.click(screen.getByRole('button', { name: 'Edit: Line 1' }));
        const english = screen.getByLabelText('English');
        await user.clear(english);
        await user.type(
            english,
            'You disclose who you represent at the first contact.',
        );
        await user.click(screen.getByRole('button', { name: 'Save line' }));
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/23/review/lines/0',
            expect.objectContaining({
                action: 'edit',
                en: 'You disclose who you represent at the first contact.',
            }),
            expect.anything(),
        );

        await user.keyboard('{Escape}');
        await user.click(screen.getByRole('button', { name: 'Send back' }));
        await user.type(
            screen.getByLabelText('What needs to change'),
            'Rework line 2.',
        );
        await user.click(
            within(screen.getByRole('dialog')).getByRole('button', {
                name: 'Send back',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/23/review/send-back',
            { note: 'Rework line 2.' },
        );
    });

    it('lists AI lessons with what is still to do', () => {
        render(<AiLessons lessons={aiLessons} />);

        expect(
            screen.getByRole('link', {
                name: 'Review: Disclosing who you represent',
            }),
        ).toHaveAttribute('href', '/course-builder/lessons/23/review');
        expect(screen.getByText('Nothing')).toBeInTheDocument();
    });
});

describe('Question bank (screen 45, A8.1)', () => {
    const page = (tab: 'to_review' | 'disputed') =>
        render(
            <QuestionBankPage
                tab={tab}
                counts={{ to_review: 1, approved: 4, rejected: 0, disputed: 1 }}
                questions={tab === 'disputed' ? [] : questions}
                disputes={tab === 'disputed' ? disputes : []}
                topics={['agency', 'contracts']}
                portions={['national', 'state']}
                difficulties={['easy', 'medium', 'hard']}
            />,
        );

    it('approves and edits questions with their source and outline', async () => {
        const user = userEvent.setup();
        page('to_review');

        expect(
            screen.getByRole('link', { name: 'To review (1)' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            screen.getByText('Agency module script.pdf, p. 3'),
        ).toBeInTheDocument();
        expect(screen.getByText('Texas portion')).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', {
                name: 'Approve: When must an agent disclose who they represent?',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/question-bank/501/decision',
            { decision: 'approve' },
            expect.anything(),
        );

        await user.click(
            screen.getByRole('button', {
                name: 'Edit: When must an agent disclose who they represent?',
            }),
        );
        await user.selectOptions(screen.getByLabelText('Difficulty'), 'hard');
        await user.click(screen.getByRole('button', { name: 'Save question' }));
        expect(router.put).toHaveBeenCalledWith(
            '/question-bank/501',
            expect.objectContaining({
                difficulty: 'hard',
                portion: 'state',
                correct: 0,
            }),
            expect.anything(),
        );
    });

    it('resolves a learner report with what to tell them', async () => {
        const user = userEvent.setup();
        page('disputed');

        expect(
            screen.getByText('Says the answer is wrong'),
        ).toBeInTheDocument();
        await user.type(
            screen.getByLabelText('What to tell the learner'),
            'It is the first substantive contact (22 TAC §531.20).',
        );
        await user.click(screen.getByRole('button', { name: 'Keep as is' }));
        expect(router.post).toHaveBeenCalledWith(
            '/question-bank/disputes/71',
            {
                outcome: 'keep',
                resolution:
                    'It is the first substantive contact (22 TAC §531.20).',
            },
            expect.anything(),
        );
    });
});

describe('Starter bank tab (screen 45, A9.1)', () => {
    beforeEach(() => {
        router.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options?: { onFinish?: () => void },
            ) => options?.onFinish?.(),
        );
    });

    it('lets a school admin approve, retire and undo REOS starter questions', async () => {
        const user = userEvent.setup();
        render(
            <QuestionBankPage
                tab="starter"
                counts={{
                    to_review: 1,
                    approved: 4,
                    rejected: 0,
                    disputed: 1,
                    starter: 287,
                }}
                questions={[]}
                disputes={[]}
                starter={starterBank}
                topics={['agency', 'state_rules']}
                portions={['national', 'state']}
                difficulties={['easy', 'medium', 'hard']}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Starter bank (287)' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByText('Right answer: 10 acres')).toBeInTheDocument();

        await user.click(
            screen.getByRole('button', {
                name: 'Retire: A Texas urban homestead is protected up to',
            }),
        );
        expect(router.post).toHaveBeenLastCalledWith(
            '/question-bank/starter',
            { ids: [9102], decision: 'retire' },
            expect.anything(),
        );

        await user.click(
            screen.getByRole('button', { name: 'Approve all 2 shown' }),
        );
        expect(router.post).toHaveBeenLastCalledWith(
            '/question-bank/starter',
            { ids: [9101, 9102], decision: 'approve' },
            expect.anything(),
        );
        const call = vi.mocked(toast).mock.lastCall;
        expect(call?.[0]).toBe('2 questions approved.');
        const undo = call?.[1]?.action;
        if (undo && typeof undo === 'object' && 'onClick' in undo) {
            undo.onClick({} as never);
        }
        expect(router.post).toHaveBeenLastCalledWith(
            '/question-bank/starter',
            { ids: [9101, 9102], decision: 'restore' },
            expect.anything(),
        );
    });

    it('saves an edit to the starter question itself', async () => {
        const user = userEvent.setup();
        render(
            <QuestionBankPage
                tab="starter"
                counts={{
                    to_review: 1,
                    approved: 4,
                    rejected: 0,
                    disputed: 1,
                    starter: 287,
                }}
                questions={[]}
                disputes={[]}
                starter={starterBank}
                topics={['agency', 'state_rules']}
                portions={['national', 'state']}
                difficulties={['easy', 'medium', 'hard']}
            />,
        );

        await user.click(
            screen.getByRole('button', {
                name: 'Edit: A Texas urban homestead is protected up to',
            }),
        );
        await user.click(screen.getByRole('button', { name: 'Save question' }));
        expect(router.put).toHaveBeenCalledWith(
            '/question-bank/starter/9102',
            expect.objectContaining({ correct: 1, portion: 'state' }),
            expect.anything(),
        );
    });
});
