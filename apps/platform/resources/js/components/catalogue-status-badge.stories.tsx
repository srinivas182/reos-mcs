import type { Meta, StoryObj } from '@storybook/react-vite';
import { CatalogueStatusBadge } from '@/components/catalogue-status-badge';

/** Catalogue statuses (web journey screen 64). */
const meta = {
    title: 'Integrations/Catalogue status',
    component: CatalogueStatusBadge,
    parameters: { layout: 'padded' },
    args: { status: 'enabled' },
} satisfies Meta<typeof CatalogueStatusBadge>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Enabled: Story = {};
export const Beta: Story = { args: { status: 'beta' } };
export const ComingSoon: Story = { args: { status: 'coming_soon' } };
export const Paused: Story = { args: { status: 'paused' } };
/** What people see for a paused tool. */
export const PausedForPeople: Story = {
    args: { status: 'paused', audience: 'people' },
};
export const Retired: Story = { args: { status: 'retired' } };
