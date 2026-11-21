import type { Meta, StoryObj } from '@storybook/react-vite';
import { CustomDomainCard } from '@/components/custom-domain-card';
import type { TenantDomain } from '@/types';

const pending: TenantDomain = {
    hostname: 'learn.lonestarrealty.com',
    status: 'pending',
    ssl: 'pending',
    live: false,
    records: [
        {
            type: 'CNAME',
            name: 'learn.lonestarrealty.com',
            value: 'tenants.reos.com',
            verified: false,
        },
        {
            type: 'TXT',
            name: '_reos-verify.learn.lonestarrealty.com',
            value: 'reos-4f1c9a7be02d6c35a18e90f4d7b2c611',
            verified: false,
        },
    ],
    error: null,
    last_checked_at: null,
    verified_at: null,
};

/** A tenant's own app address with the DNS records to add (screens 41 and 57). */
const meta = {
    title: 'Branding/Custom domain',
    component: CustomDomainCard,
    args: { domain: null, address: 'lone-star.reos.com' },
} satisfies Meta<typeof CustomDomainCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NoDomainYet: Story = {};
export const WaitingForDns: Story = { args: { domain: pending } };
export const Live: Story = {
    args: {
        domain: {
            ...pending,
            status: 'active',
            ssl: 'active',
            live: true,
            records: pending.records.map((record) => ({
                ...record,
                verified: true,
            })),
            verified_at: '2026-11-21T15:00:00Z',
            last_checked_at: '2026-11-21T15:00:00Z',
        },
    },
};
