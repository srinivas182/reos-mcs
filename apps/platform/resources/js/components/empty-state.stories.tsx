import type { Meta, StoryObj } from '@storybook/react-vite';
import { GraduationCap, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';

/** Every empty list teaches what the page is for and offers exactly one main next step. */
const meta = {
    title: 'Display/Empty state',
    component: EmptyState,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof EmptyState>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Contacts: Story = {
    args: {
        icon: Users,
        title: 'Your contacts live here',
        description:
            'Connect Follow Up Boss or kvCORE and your contacts appear here, kept in sync both ways.',
        action: <Button>Connect your CRM</Button>,
        secondaryAction: <Button variant="ghost">Import a spreadsheet</Button>,
    },
};

export const Courses: Story = {
    args: {
        icon: GraduationCap,
        title: 'No courses yet',
        description:
            'Start your 180 hours of Texas pre-licensing. Lessons are short and work on your phone.',
        action: <Button size="lg">Choose a course</Button>,
    },
};
