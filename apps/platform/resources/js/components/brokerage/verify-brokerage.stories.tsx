import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { VerifyBrokerage } from './verify-brokerage';

const meta = {
    title: 'Brokerage/Verify your brokerage',
    component: VerifyBrokerage,
    args: {
        routes: {
            lookup: '/storybook/start/brokerage/verify/lookup',
            confirm: '/storybook/start/brokerage/verify/confirm',
        },
        onVerified: () => {},
    },
} satisfies Meta<typeof VerifyBrokerage>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Grace Hill enters her TREC broker license to set up Bayou City Homes. */
export const EnterLicence: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByLabelText('Your TREC broker license number'),
        ).toBeInTheDocument();
    },
};
