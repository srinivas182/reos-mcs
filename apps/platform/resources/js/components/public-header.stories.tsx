import type { Meta, StoryObj } from '@storybook/react-vite';
import { PublicHeader } from '@/components/public-header';

/** The reos.com top bar (web journey screen 1). */
const meta = {
    title: 'Public site/Top bar',
    component: PublicHeader,
    parameters: { layout: 'fullscreen' },
    args: { name: 'REOS', liveState: 'Texas', signedIn: false },
} satisfies Meta<typeof PublicHeader>;

export default meta;
type Story = StoryObj<typeof meta>;

/** A visitor: Sign in and Get started. */
export const Visitor: Story = {};

/** Maria Lopez, already signed in, sees "Go to dashboard". */
export const SignedIn: Story = { args: { signedIn: true } };
