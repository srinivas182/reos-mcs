import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { PillGroup } from '@/components/pill-group';

const minutes = [
    { value: '10', label: '10 min' },
    { value: '20', label: '20 min' },
    { value: '30', label: '30 min' },
    { value: '45', label: '45 min' },
    { value: '60', label: '1 h+' },
] as const;

function Minutes() {
    const [value, setValue] = useState<string>('20');

    return (
        <PillGroup
            label="How much time a day?"
            value={value}
            options={minutes}
            onChange={setValue}
        />
    );
}

/** Rounded choices on "Your plan · 3 quick questions" (candidate journey screen 8). */
const meta = {
    title: 'Forms/Pill group',
    component: Minutes,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof Minutes>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TimeADay: Story = {};
