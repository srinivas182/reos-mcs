import type { Meta, StoryObj } from '@storybook/react-vite';
import { PlanCard } from '@/components/plan-card';

/** A plan on Plan and billing (screen 29), opening checkout on reos.com. */
const meta = {
    title: 'Billing/Plan card',
    component: PlanCard,
    args: {
        price: {
            id: 1,
            product: 'Texas Pre-licence Complete',
            kind: 'package',
            description: 'Everything to get licensed in Texas.',
            includes: [
                'All 6 qualifying courses (180 hours), TREC approved',
                'Exam success course, test prep, full simulations',
                'Pass Promise: retake plan if you don’t pass',
            ],
            state: 'TX',
            interval: 'once',
            amount: 49900,
        },
        charge: 39900,
        sponsor: 'Lone Star Realty',
        aiHelp: 'Course answers free · sponsor fallback',
    },
} satisfies Meta<typeof PlanCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Sponsored: Story = {};
export const Monthly: Story = {
    args: {
        price: {
            id: 2,
            product: 'Texas Pre-licence Monthly',
            kind: 'plan',
            description: null,
            includes: ['All courses while subscribed'],
            state: 'TX',
            interval: 'month',
            amount: 7900,
        },
        charge: 7900,
        sponsor: null,
        aiHelp: 'Course answers free',
    },
};
