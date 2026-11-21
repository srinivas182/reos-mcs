import type { Meta, StoryObj } from '@storybook/react-vite';
import { TwoStepNotice } from '@/components/two-step-notice';
import { Button } from '@/components/ui/button';

/**
 * Admin roles (broker, school admin, association admin, super admin) need
 * two-step. People are led to set it up instead of meeting a locked door.
 */
const meta = {
    title: 'Feedback/Two-step notice',
    component: TwoStepNotice,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof TwoStepNotice>;

export default meta;
type Story = StoryObj<typeof meta>;

/** The banner at the top of every page while the broker role waits. */
export const Banner: Story = {
    args: {
        role: 'Broker',
        action: <Button>Set up two-step</Button>,
        secondaryAction: <Button variant="outline">Use another role</Button>,
    },
};

/** On Security settings, above the passkey and authenticator choices. */
export const OnSecuritySettings: Story = {
    args: { role: 'Association admin', mode: 'security' },
};
