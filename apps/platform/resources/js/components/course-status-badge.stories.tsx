import type { Meta, StoryObj } from '@storybook/react-vite';
import { CourseStatusBadge } from '@/components/course-status-badge';

/** A course's status in the content library (A5.3; screen 63). */
const meta = {
    title: 'Content/Course status',
    component: CourseStatusBadge,
    args: { status: 'live' },
} satisfies Meta<typeof CourseStatusBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Live: Story = {};
export const InReview: Story = { args: { status: 'in_review' } };
export const Draft: Story = { args: { status: 'draft' } };
/** "Contracts for new agents": CE, but no TREC approval number yet. */
export const NoApproval: Story = {
    args: { status: 'draft', noApproval: true },
};
