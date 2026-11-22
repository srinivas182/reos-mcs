import type { Meta, StoryObj } from '@storybook/react-vite';
import type { ProviderCategoryRow } from '@/components/provider-category-dialog';
import { ProviderCategoryDialog } from '@/components/provider-category-dialog';

const coordinator: ProviderCategoryRow = {
    id: 4,
    key: 'transaction-coordinator',
    name: 'Transaction coordinator',
    description: null,
    licence_check: 'ID check + E&O',
    verification: 'ID check + E&O certificate',
    profile_fields: ['Files per month', 'Tools used', 'Per-file fee'],
    special_permissions: 'Deal sharing with access switches',
    areas_on_pro: 3,
    status: 'live',
    requests: 0,
    providers: 96,
    prices: [
        { state: 'TX', tier: 'basic', monthly: 2900 },
        { state: 'TX', tier: 'pro', monthly: 8900 },
        { state: 'TX', tier: 'premium', monthly: 19900 },
    ],
};

/** Category settings on screen 68 (A6.1): licence check, profile fields and flat monthly prices. */
const meta = {
    title: 'Back office/Provider category',
    component: ProviderCategoryDialog,
    args: {
        open: true,
        category: coordinator,
        options: {
            tiers: ['basic', 'pro', 'premium'],
            statuses: ['live', 'draft', 'requested', 'retired'],
            states: [{ code: 'TX', name: 'Texas' }],
        },
        onOpenChange: () => undefined,
    },
} satisfies Meta<typeof ProviderCategoryDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TransactionCoordinator: Story = {};
export const NewCategory: Story = { args: { category: null } };
