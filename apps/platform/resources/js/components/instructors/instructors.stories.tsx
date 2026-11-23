import type { Meta, StoryObj } from '@storybook/react-vite';
import { CoursesDialog } from './courses-dialog';
import { instructors, schoolCourses } from './fixtures';
import { InstructorsTable } from './instructors-table';

const meta = {
    title: 'School/Instructors',
    component: InstructorsTable,
    args: { instructors, onEdit: () => {} },
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
