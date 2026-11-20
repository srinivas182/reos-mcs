import type { Meta, StoryObj } from '@storybook/react-vite';
import { ApprovalBadge, ModeBadge } from '@/components/integration-badges';

/** How an integration runs, and where its vendor approval stands. */
const meta = {
    title: 'Display/Integration badges',
    component: ModeBadge,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof ModeBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Modes: Story = {
    args: { mode: 'simulator' },
    render: () => (
        <div className="flex flex-wrap gap-2">
            <ModeBadge mode="off" />
            <ModeBadge mode="simulator" />
            <ModeBadge mode="sandbox" />
            <ModeBadge mode="live" />
            <ModeBadge mode="live" paused />
        </div>
    ),
};

export const Approvals: Story = {
    args: { mode: 'simulator' },
    render: () => (
        <div className="flex flex-wrap gap-2">
            <ApprovalBadge status="not_started" />
            <ApprovalBadge status="in_review" />
            <ApprovalBadge status="approved" />
            <ApprovalBadge status="blocked" />
            <ApprovalBadge status="not_needed" />
        </div>
    ),
};
