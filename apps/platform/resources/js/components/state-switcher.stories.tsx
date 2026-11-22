import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { StateSwitcher } from '@/components/state-switcher';

/**
 * The state chip in the top bar (A5.1). People licensed or studying in more
 * than one live state switch here; content, plans and the CE card follow.
 */
const meta = {
    title: 'Navigation/State switcher',
    component: StateSwitcher,
    parameters: { layout: 'padded' },
    args: { onSwitch: fn(), region: { code: 'TX', name: 'Texas' } },
} satisfies Meta<typeof StateSwitcher>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Most people work in Texas alone: the chip just names it. */
export const OneState: Story = {};

/** James Miller holds Texas and Florida licences. */
export const TexasAndFlorida: Story = {
    args: {
        region: {
            code: 'TX',
            name: 'Texas',
            options: [
                { code: 'FL', name: 'Florida' },
                { code: 'TX', name: 'Texas' },
            ],
        },
    },
};
