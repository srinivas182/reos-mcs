import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { GrantRoleForm } from '@/components/grant-role-form';

/** Give a person a role on the People screen; only valid organisations are offered. */
const meta = {
    title: 'Forms/Grant role',
    component: GrantRoleForm,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-2xl">
                <Story />
            </div>
        ),
    ],
    args: {
        onSubmit: fn(),
        roleOptions: [
            { role: 'candidate', organisationTypes: ['school', 'platform'] },
            { role: 'agent', organisationTypes: ['brokerage', 'platform'] },
            { role: 'broker', organisationTypes: ['brokerage'] },
            { role: 'instructor', organisationTypes: ['school'] },
            { role: 'school_admin', organisationTypes: ['school'] },
        ],
        organisations: [
            { id: 1, name: 'REOS', type: 'platform' },
            { id: 2, name: 'Lone Star Realty', type: 'brokerage' },
            { id: 3, name: 'Texas Realty School', type: 'school' },
        ],
    },
} satisfies Meta<typeof GrantRoleForm>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithError: Story = {
    args: {
        error: "That role can't be held in this kind of organisation.",
    },
};
