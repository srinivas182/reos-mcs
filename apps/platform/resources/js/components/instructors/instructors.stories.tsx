import type { Meta, StoryObj } from '@storybook/react-vite';
import { CoursesDialog } from './courses-dialog';
import { instructors, schoolCourses } from './fixtures';
import { InstructorsTable } from './instructors-table';
import { SessionLimitsCard } from './session-limits';
import { TwinsDialog } from './twins-dialog';

const meta = {
    title: 'School/Instructors',
    component: InstructorsTable,
    args: { instructors, onEdit: () => {}, onTwins: () => {} },
} satisfies Meta<typeof InstructorsTable>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 52: consent given, consent withdrawn (with the date lessons come down), and an invitation. */
export const Table: Story = {};

/** Edit: the courses an instructor teaches. */
export const EditCourses: Story = {
    render: () => (
        <CoursesDialog
            instructor={instructors[0]}
            courses={schoolCourses}
            onClose={() => {}}
        />
    ),
};

/** Avatar ids: the instructor's twin at HeyGen and Synthesia (A7.3). */
export const AvatarIds: Story = {
    render: () => (
        <TwinsDialog
            instructor={{
                ...instructors[0],
                avatar: {
                    ...instructors[0].avatar,
                    twins: { heygen: 'a7f3c2e9b1d04', synthesia: null },
                },
            }}
            onClose={() => {}}
        />
    ),
};

/** 1-on-1 session prices (A10.2d): at most $40 for 30 minutes, no limit for 60. */
export const SessionPriceLimits: Story = {
    render: () => (
        <SessionLimitsCard
            limits={{ max_30_cents: 4000, max_60_cents: null }}
        />
    ),
};
