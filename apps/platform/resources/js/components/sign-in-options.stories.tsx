import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    OrDivider,
    PhoneSignIn,
    ProviderButtons,
} from '@/components/sign-in-options';

/**
 * The other ways to sign in, under the email form (journey screen w002).
 * Only options switched on in Settings › Integrations appear.
 */
const meta = {
    title: 'Forms/Sign-in options',
    component: ProviderButtons,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-md space-y-4">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof ProviderButtons>;

export default meta;
type Story = StoryObj<typeof meta>;

export const GoogleAndApple: Story = {
    args: { available: { google: true, apple: true, phone: true } },
    render: (args) => (
        <>
            <OrDivider />
            <ProviderButtons {...args} />
            <PhoneSignIn />
        </>
    ),
};

export const GoogleOnly: Story = {
    args: { available: { google: true, apple: false, phone: false } },
};

export const PhoneNumberStep: Story = {
    args: { available: { google: false, apple: false, phone: true } },
    render: () => <PhoneSignIn initiallyOpen />,
};

export const PhoneCodeStep: Story = {
    args: { available: { google: false, apple: false, phone: true } },
    render: () => <PhoneSignIn sentTo="+15125550100" />,
};
