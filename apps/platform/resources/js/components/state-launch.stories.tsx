import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ChecklistItem, LaunchState } from '@/components/state-launch';
import { AddStateDialog, LaunchChecklist } from '@/components/state-launch';

const items: ChecklistItem[] = [
    'rules',
    'approvals',
    'advertising',
    'plans',
    'integrations',
    'legal',
];

const louisiana: LaunchState = {
    code: 'LA',
    name: 'Louisiana',
    status: 'in_preparation',
    regulator: 'LREC',
    exam_vendor: 'PSI',
    qualifying_hours: 90,
    ce_hours: 12,
    checklist: {
        rules: true,
        approvals: true,
        advertising: true,
        plans: false,
        integrations: false,
        legal: false,
    },
};

/**
 * The launch checklist on States (web screen 61, A5.1): a state goes live
 * only when every item is done, and that day its waitlist is told.
 */
const meta = {
    title: 'Admin/State launch checklist',
    component: LaunchChecklist,
    parameters: { layout: 'padded' },
    args: { state: louisiana, items },
} satisfies Meta<typeof LaunchChecklist>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Louisiana, half way: rules, approvals and advertising done. */
export const LouisianaInPreparation: Story = {};

/** "Add state": start preparing Oklahoma. */
export const AddState: Story = {
    render: () => (
        <AddStateDialog
            open
            onOpenChange={() => {}}
            states={[
                louisiana,
                {
                    ...louisiana,
                    code: 'OK',
                    name: 'Oklahoma',
                    status: 'waitlist',
                    regulator: 'OREC',
                },
            ]}
        />
    ),
};
