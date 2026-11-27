import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import {
    activeSession,
    assessmentFixture,
    overviewFixture,
    scoredSession,
} from './fixtures';
import {
    AssessmentCard,
    PracticePicker,
    RecentList,
    SessionView,
} from './role-play-view';

const meta = {
    title: 'Practice/Role-play',
    component: SessionView,
    args: {
        session: activeSession(),
        busy: false,
        onSay: fn(),
        onFinish: fn(),
        lessonHref: (lesson: string) => `/learn?topic=${lesson}`,
    },
} satisfies Meta<typeof SessionView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Mid-practice: the seller pushes back on price; the agent types an answer. */
export const Answering: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('3 answers left')).toBeInTheDocument();
        await userEvent.type(
            canvas.getByLabelText('Your answer'),
            'I understand. The comparable sales on Willow Bend support 485,000.',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Send' }));
        await expect(args.onSay).toHaveBeenCalledWith(
            'I understand. The comparable sales on Willow Bend support 485,000.',
        );
    },
};

/** Scored: 72 out of 100, the seller agreed, and three fixes with lessons. */
export const Scored: Story = {
    args: { session: scoredSession() },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('72 out of 100')).toBeInTheDocument();
        await expect(canvas.getAllByRole('listitem')).not.toHaveLength(0);
        await expect(
            canvas.getByRole('link', {
                name: 'Lesson: Asking for the next step',
            }),
        ).toHaveAttribute('href', '/learn?topic=asking_for_business');
    },
};

/** The five skills and the people to practise with. */
export const Skills: StoryObj<typeof PracticePicker> = {
    render: () => (
        <PracticePicker
            types={overviewFixture().types}
            busy={false}
            onStart={fn()}
        />
    ),
};

/** The skills assessment, two rounds of five done. */
export const AssessmentUnderWay: StoryObj<typeof AssessmentCard> = {
    render: () => (
        <AssessmentCard
            assessment={assessmentFixture()}
            busy={false}
            onAssess={fn()}
        />
    ),
};

/** The skills assessment finished, with the weakest skills first (screen 7). */
export const AssessmentFinished: StoryObj<typeof AssessmentCard> = {
    render: () => (
        <AssessmentCard
            assessment={assessmentFixture({
                done: 5,
                next: null,
                completed: true,
                results: [
                    { type: 'listing', score: 84 },
                    { type: 'objection', score: 70 },
                    { type: 'prospecting', score: 38 },
                    { type: 'appraisal_gap', score: 55 },
                    { type: 'follow_up', score: 66 },
                ],
                weakest: ['prospecting', 'appraisal_gap'],
                completed_at: '2026-11-27T11:00:00-06:00',
            })}
            busy={false}
            onAssess={fn()}
        />
    ),
};

/** Recent practice, and the empty state before the first one. */
export const Recent: StoryObj<typeof RecentList> = {
    render: () => (
        <div className="space-y-6">
            <RecentList
                recent={overviewFixture().recent}
                href={(id) => `/role-play/${id}`}
            />
            <RecentList recent={[]} href={(id) => `/role-play/${id}`} />
        </div>
    ),
};
