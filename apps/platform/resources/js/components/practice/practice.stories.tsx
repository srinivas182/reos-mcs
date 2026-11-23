import type { Meta, StoryObj } from '@storybook/react-vite';
import { CandidateToday } from './candidate-today';
import { studyToday } from './fixtures';
import { ReadinessRing, TopicBars } from './readiness-ring';

const meta = {
    title: 'Candidate/Today and readiness',
    component: CandidateToday,
    args: { study: studyToday },
} satisfies Meta<typeof CandidateToday>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Web screen 4: readiness, streak, exam date, Continue and today's plan with reasons. */
export const Dashboard: Story = {};

/** Before the diagnostic: day one is the diagnostic, readiness not known yet. */
export const BeforeDiagnostic: Story = {
    args: {
        study: {
            ...studyToday,
            readiness: null,
            diagnostic_done: false,
            continue: null,
            tasks: [
                {
                    id: 1,
                    kind: 'diagnostic',
                    why: 'baseline',
                    minutes: 45,
                    done: false,
                    day: '2026-11-23',
                    lesson: null,
                    topic: null,
                },
            ],
        },
    },
};

/** Behind: the plan says so gently and never moves the exam. */
export const Behind: Story = {
    args: { study: { ...studyToday, behind_minutes: 340 } },
};

export const Ring: Story = {
    render: () => (
        <div className="flex gap-6">
            <ReadinessRing percent={72} />
            <ReadinessRing percent={null} />
        </div>
    ),
};

export const ByTopic: Story = {
    render: () => (
        <div className="max-w-lg">
            <TopicBars
                topics={[
                    { topic: 'math', percent: 29, weight: 7 },
                    { topic: 'finance', percent: 43, weight: 7 },
                    { topic: 'agency', percent: 100, weight: 9 },
                    { topic: 'state_rules', percent: null, weight: 10 },
                ]}
            />
        </div>
    ),
};
