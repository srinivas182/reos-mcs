import type { Meta, StoryObj } from '@storybook/react-vite';
import type { PlanRow } from '@/components/plan-dialogs';
import { PriceHistoryDialog } from '@/components/plan-dialogs';

const plans: PlanRow[] = [
    {
        id: 1,
        key: 'texas-pre-licence-complete',
        name: 'Texas Pre-licence Complete',
        kind: 'package',
        buyer: 'candidate',
        description: null,
        includes: ['6 courses, exam success, test prep'],
        features: ['learn.qualifying', 'learn.test_prep'],
        ai_help: 'Course answers free · sponsor fallback',
        credits: null,
        seats: null,
        status: 'live',
        prices: [
            {
                id: 2,
                state: 'TX',
                state_name: 'Texas',
                amount: 49900,
                floor: 39900,
                interval: 'once',
                current: true,
                starts_at: '2026-11-01T00:00:00Z',
                ends_at: null,
            },
            {
                id: 1,
                state: 'TX',
                state_name: 'Texas',
                amount: 44900,
                floor: null,
                interval: 'once',
                current: false,
                starts_at: '2026-06-01T00:00:00Z',
                ends_at: '2026-11-01T00:00:00Z',
            },
        ],
    },
];

/** Price history on Plans and pricing (screen 67): every price kept, with its dates. */
const meta = {
    title: 'Billing/Price history',
    component: PriceHistoryDialog,
    args: { open: true, onOpenChange: () => undefined, plans },
} satisfies Meta<typeof PriceHistoryDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TexasPreLicence: Story = {};
