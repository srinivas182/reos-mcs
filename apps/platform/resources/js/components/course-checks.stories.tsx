import type { Meta, StoryObj } from '@storybook/react-vite';
import { CourseChecks } from '@/components/course-checks';

/** A course's checks and review on screen 63's course page (A5.3b). */
const meta = {
    title: 'Content/Course checks',
    component: CourseChecks,
    args: {
        course: {
            id: 3,
            title: 'Principles of Real Estate II',
            status: 'in_review',
        },
        checks: {
            approvals: [],
            flags: [
                {
                    key: `${'a'.repeat(64)}:guarantee`,
                    rule: 'guarantee',
                    lesson_id: 7,
                    lesson_title: '6.2 Agency disclosure',
                    kind: 'transcript',
                    heading: null,
                    page: null,
                    seconds: 312,
                    excerpt:
                        'Follow these steps and we [[guarantee]] you pass on the first try.',
                    cleared: false,
                },
            ],
            open: 1,
            clean: false,
        },
    },
} satisfies Meta<typeof CourseChecks>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Principles II: a promise in the video transcript blocks publishing. */
export const OneLegalFlag: Story = {};

/** "Contracts for new agents": CE, no TREC approval number yet. */
export const ApprovalPending: Story = {
    args: {
        course: { id: 5, title: 'Contracts for new agents', status: 'draft' },
        checks: { approvals: ['TX'], flags: [], open: 0, clean: false },
    },
};

export const Clean: Story = {
    args: { checks: { approvals: [], flags: [], open: 0, clean: true } },
};
