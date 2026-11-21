import type { Meta, StoryObj } from '@storybook/react-vite';
import { RolePicker } from '@/components/role-picker';

/** "I am …" on the reos.com home page (web journey screen 1). */
const meta = {
    title: 'Public site/Role picker',
    component: RolePicker,
    parameters: { layout: 'padded' },
    args: { state: 'Texas' },
} satisfies Meta<typeof RolePicker>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Texas: Story = {};
