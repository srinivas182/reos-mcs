import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { StatePicker } from '@/components/state-picker';
import type { StateOption } from '@/lib/content';

const states: StateOption[] = [
    { code: 'AL', name: 'Alabama', active: false },
    { code: 'FL', name: 'Florida', active: false },
    { code: 'LA', name: 'Louisiana', active: true },
    { code: 'OK', name: 'Oklahoma', active: false },
    { code: 'TX', name: 'Texas', active: true },
];

/** The states a course is for (A5.3): live and preparing states first. */
const meta = {
    title: 'Content/State picker',
    component: StatePicker,
    args: { states, value: ['TX'], onChange: () => undefined },
    render: (args) => {
        const [value, setValue] = useState(args.value);

        return <StatePicker {...args} value={value} onChange={setValue} />;
    },
} satisfies Meta<typeof StatePicker>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Principles of Real Estate II, for Texas. */
export const Texas: Story = {};
export const EveryState: Story = { args: { value: [] } };
