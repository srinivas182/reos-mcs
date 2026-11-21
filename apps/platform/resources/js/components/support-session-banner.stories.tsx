import type { Meta, StoryObj } from '@storybook/react-vite';
import { SupportSessionNotice } from '@/components/support-session-banner';

/** Shown on every page while REOS support is inside a tenant (A4.1c). */
const meta = {
    title: 'Tenants/Support session banner',
    component: SupportSessionNotice,
    parameters: { layout: 'fullscreen' },
    args: {
        tenant: 'Lone Star Realty',
        endsAt: new Date(Date.now() + 42 * 60_000).toISOString(),
    },
} satisfies Meta<typeof SupportSessionNotice>;

export default meta;
type Story = StoryObj<typeof meta>;

export const FortyTwoMinutesLeft: Story = {};
