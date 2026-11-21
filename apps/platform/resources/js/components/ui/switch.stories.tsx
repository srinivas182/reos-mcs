import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { Switch } from '@/components/ui/switch';

/** An on/off switch with a 48 px touch target. */
const meta = {
    title: 'Form controls/Switch',
    component: Switch,
    args: { checked: true, onCheckedChange: () => undefined, 'aria-label': 'Spanish UI' },
    render: (args) => {
        const [on, setOn] = useState(args.checked);

        return <Switch {...args} checked={on} onCheckedChange={setOn} />;
    },
} satisfies Meta<typeof Switch>;

export default meta;
type Story = StoryObj<typeof meta>;

export const On: Story = {};
export const Off: Story = { args: { checked: false, 'aria-label': 'AI video lessons for brokerage training' } };
