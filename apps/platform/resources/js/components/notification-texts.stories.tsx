import type { Meta, StoryObj } from '@storybook/react-vite';
import { NotificationTexts } from '@/components/notification-texts';

/** Settings › Notifications › Texts (B6.2c), for James Miller at Lone Star Realty. */
const meta = {
    title: 'Notifications/Texts',
    component: NotificationTexts,
    args: { texts: { phone: '••• ••• 0100', on: true, available: true } },
} satisfies Meta<typeof NotificationTexts>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TextsOn: Story = {};
export const NumberVerifiedTextsOff: Story = {
    args: { texts: { phone: '••• ••• 0100', on: false, available: true } },
};
export const AddANumber: Story = {
    args: { texts: { phone: null, on: false, available: true } },
};
export const NotSetUpYet: Story = {
    args: { texts: { phone: null, on: false, available: false } },
};
