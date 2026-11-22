import type { Meta, StoryObj } from '@storybook/react-vite';
import { SponsorCodesCard } from '@/components/sponsor-codes-card';

/** Sponsor codes on Texas Realty School's Plan tab (B5.1b). */
const meta = {
    title: 'Billing/Sponsor codes',
    component: SponsorCodesCard,
    args: {
        tenantId: 3,
        products: [{ id: 1, name: 'Texas Pre-licence Complete' }],
        states: [{ code: 'TX', name: 'Texas' }],
        codes: [
            {
                id: 1,
                code: 'TEX-7K4M2Q',
                product: 'Texas Pre-licence Complete',
                state: 'TX',
                seats: 25,
                used: 2,
                access_months: 12,
                expires_at: '2027-06-30T23:59:59Z',
                active: true,
                people: ['Sofia Reyes', 'Marcus Lee'],
            },
            {
                id: 2,
                code: 'TEX-P9WXR3',
                product: 'Texas Exam Prep only',
                state: 'TX',
                seats: 10,
                used: 10,
                access_months: null,
                expires_at: null,
                active: false,
                people: [],
            },
        ],
    },
} satisfies Meta<typeof SponsorCodesCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const SpringCohort: Story = {};
export const NoCodesYet: Story = { args: { codes: [] } };
