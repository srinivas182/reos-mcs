import type { Meta, StoryObj } from '@storybook/react-vite';
import { BrandPreview } from '@/components/brand-preview';

/** What members will see, before saving (Branding and domain, screen 57). */
const meta = {
    title: 'Branding/Preview',
    component: BrandPreview,
    parameters: { layout: 'padded' },
    args: {
        name: 'Texas REALTORS®',
        type: 'association',
        logoUrl: null,
        colour: '#1E3A8A',
        address: 'texas-realtors.reos.com',
        sender: 'Texas REALTORS®',
    },
} satisfies Meta<typeof BrandPreview>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TexasRealtors: Story = {};

/** A brokerage in teal. */
export const LoneStarRealty: Story = {
    args: {
        name: 'Lone Star Realty',
        type: 'brokerage',
        colour: '#0F766E',
        address: 'lone-star-realty.reos.com',
        sender: 'Lone Star Realty',
    },
};
