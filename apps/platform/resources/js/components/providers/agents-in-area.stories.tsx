import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import {
    mayaAgents,
    mayaNoAgents,
    mayaSent,
    mayaUsedUp,
} from './agents-fixtures';
import { AgentsInArea } from './agents-in-area';

const meta = {
    title: 'Providers/Agents in my areas',
    component: AgentsInArea,
    args: {
        view: mayaAgents,
        sent: mayaSent,
        routes: {
            request: '/storybook/provider/requests',
            withdraw: (id: string) =>
                `/storybook/provider/requests/${id}/withdraw`,
        },
        onFilter: () => {},
    },
} satisfies Meta<typeof AgentsInArea>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Maya in Katy: three requests used of five, one waiting with Maria. */
export const InKaty: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', {
                name: 'Ask to connect with James M.',
            }),
        ).toBeEnabled();
    },
};

/** All five requests used this month. */
export const LimitReached: Story = { args: { view: mayaUsedUp } };

/** No agents in the areas yet. */
export const NoAgents: Story = { args: { view: mayaNoAgents, sent: [] } };
