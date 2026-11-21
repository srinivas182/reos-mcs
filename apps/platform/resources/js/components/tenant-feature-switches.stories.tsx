import type { Meta, StoryObj } from '@storybook/react-vite';
import { fn } from 'storybook/test';
import { TenantFeatureSwitches } from '@/components/tenant-feature-switches';
import { loneStarFeatures } from '@/components/tenant-fixtures';

/** "Features for this tenant" (super admin, screen 60). */
const meta = {
    title: 'Tenants/Feature switches',
    component: TenantFeatureSwitches,
    parameters: { layout: 'padded' },
    args: { features: loneStarFeatures, onChange: fn() },
} satisfies Meta<typeof TenantFeatureSwitches>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Lone Star Realty: brokerage defaults, recruiting in beta, AI video lessons as an add-on. */
export const LoneStarRealty: Story = {};
