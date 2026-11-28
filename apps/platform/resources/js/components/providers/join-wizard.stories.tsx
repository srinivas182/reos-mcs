import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { categories, mayaJoining } from './join-fixtures';
import { JoinWizard } from './join-wizard';

const meta = {
    title: 'Providers/Joining',
    component: JoinWizard,
    args: {
        categories,
        onboarding: mayaJoining,
        onCheckout: () => undefined,
        routes: {
            request: '/storybook/provider/categories/requests',
            category: '/storybook/provider/profile/category',
            fields: '/storybook/provider/profile/fields',
            status: '/storybook/provider/join/status',
            welcome: '/storybook/provider/join/welcome',
            business: '/storybook/provider/join/business',
            licence: '/storybook/settings/provider-licences',
            areas: '/storybook/provider/join/areas',
            area: (id: number) => `/storybook/provider/join/areas/${id}`,
            plan: '/storybook/provider/join/plan',
            rules: '/storybook/provider/join/rules',
        },
    },
} satisfies Meta<typeof JoinWizard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Maya Chen, a loan officer, at the plan step: Listed, Pro and Partner. */
export const ChooseAPlan: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('heading', {
                name: 'Choose your plan',
            }),
        ).toBeInTheDocument();
    },
};

/** Live: every step done and the NMLS ID verified. */
export const Live: Story = {
    args: {
        onboarding: {
            ...mayaJoining,
            status: 'live',
            done: 6,
            tier: 'pro',
            steps: mayaJoining.steps.map((s) => ({ ...s, done: true })),
            rules: {
                ...mayaJoining.rules,
                accepted_at: '2026-11-30T15:08:00Z',
                current: true,
            },
        },
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText(
                'Your profile is live. Agents can find you.',
            ),
        ).toBeInTheDocument();
    },
};
