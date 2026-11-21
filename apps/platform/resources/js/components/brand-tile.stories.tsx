import type { Meta, StoryObj } from '@storybook/react-vite';
import { BrandTile } from '@/components/brand-tile';

/** A tenant's logo, or its initials on its colour. */
const meta = {
    title: 'Branding/Tile',
    component: BrandTile,
    args: { name: 'Texas REALTORS®', logoUrl: null, colour: '#1E3A8A' },
} satisfies Meta<typeof BrandTile>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Initials: Story = {};
export const NoColourYet: Story = {
    args: { name: 'Bayou Realty', colour: null },
};
