import type { Meta, StoryObj } from '@storybook/react-vite';
import { ApprovalDialog } from './approval-dialog';
import { ApprovalsCard } from './approvals-card';
import { ApprovalsTable } from './approvals-table';
import { approvals, options, summary } from './fixtures';

const meta = {
    title: 'School/State approvals',
    component: ApprovalsTable,
    args: { approvals, onOpen: () => {} },
} satisfies Meta<typeof ApprovalsTable>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 50: approved, waiting for TREC, and one expiring in 25 days. */
export const Table: Story = {};

/** Add approval, with Texas seat-time rules filled in. */
export const AddApproval: Story = {
    render: () => (
        <ApprovalDialog
            mode={{ kind: 'add' }}
            options={options}
            onClose={() => {}}
        />
    ),
};

/** Renew: the expiry date starts empty. */
export const Renew: Story = {
    render: () => (
        <ApprovalDialog
            mode={{ kind: 'renew', approval: approvals[2] }}
            options={options}
            onClose={() => {}}
        />
    ),
};

/** The school dashboard's card (screen 49). */
export const DashboardCard: Story = {
    render: () => <ApprovalsCard summary={summary} />,
};

export const DashboardAllGood: Story = {
    render: () => (
        <ApprovalsCard summary={{ courses: 2, in_force: 2, attention: [] }} />
    ),
};
