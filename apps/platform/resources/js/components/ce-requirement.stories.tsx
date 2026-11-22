import type { Meta, StoryObj } from '@storybook/react-vite';
import { CeHoursTile, CeRequirementList } from '@/components/ce-requirement';
import { texasCe } from '@/components/licence-fixtures';

/**
 * CE from the state's rules (A5.2): the dashboard tile (screen 12) and
 * "What you still need" under each licence.
 */
const meta = {
    title: 'Learning/CE requirement',
    component: CeRequirementList,
    parameters: { layout: 'padded' },
    args: { ce: texasCe },
} satisfies Meta<typeof CeRequirementList>;

export default meta;
type Story = StoryObj<typeof meta>;

/** James Miller: 9 of 18 hours, Legal Update II and electives to go. */
export const NineOfEighteen: Story = {};

/** All done for this renewal. */
export const Complete: Story = {
    args: {
        ce: {
            ...texasCe,
            hours_done: 18,
            hours_left: 0,
            complete: true,
            parts: texasCe.parts.map((p) => ({
                ...p,
                hours_done: p.hours,
                done: true,
            })),
        },
    },
};

/** The dashboard's CE tile. */
export const DashboardTile: Story = {
    render: () => <CeHoursTile ce={texasCe} />,
};
