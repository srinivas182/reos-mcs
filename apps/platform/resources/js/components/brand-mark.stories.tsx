import type { Meta, StoryObj } from '@storybook/react-vite';
import { BrandMark } from '@/components/brand-mark';

/** The REOS mark, on the public site, sign-in and the sidebar. */
const meta = {
    title: 'Brand/Brand mark',
    component: BrandMark,
    parameters: { layout: 'centered' },
} satisfies Meta<typeof BrandMark>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

/** Smaller, in the public site's top bar. */
export const InTopBar: Story = { args: { className: 'size-10' } };
