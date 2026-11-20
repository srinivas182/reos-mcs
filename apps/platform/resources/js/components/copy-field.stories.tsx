import type { Meta, StoryObj } from '@storybook/react-vite';
import { CopyField } from '@/components/copy-field';

/** A value to paste into another system, with a Copy button. */
const meta = {
    title: 'Forms/Copy field',
    component: CopyField,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-xl">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof CopyField>;

export default meta;
type Story = StoryObj<typeof meta>;

export const RedirectAddress: Story = {
    args: {
        label: 'Redirect address',
        hint: "Add this address to the app in the vendor's developer console.",
        value: 'https://reos.com/integrations/google-workspace/callback',
    },
};
