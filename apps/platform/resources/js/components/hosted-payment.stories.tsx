import type { Meta, StoryObj } from '@storybook/react-vite';
import { HostedPayment } from '@/components/hosted-payment';
import type { StartedCheckout } from '@/lib/billing';

const started: StartedCheckout = {
    order: '9b1c2f7e-3a41-4f0e-9d2a-1c5e7b8a6f10',
    gateway: 'paypal',
    client_secret: null,
    redirect: null,
    simulator: true,
    tax: 3300,
    total: 43300,
};

/**
 * Checkout at PayPal or Authorize.net (screen 3, B5.2a): the ZIP code for
 * the sales tax, then the gateway's own page (or the Simulator's test Pay).
 */
const meta = {
    title: 'Billing/Hosted payment',
    component: HostedPayment,
    args: {
        gateway: 'paypal',
        simulator: false,
        onStart: (): Promise<StartedCheckout> =>
            Promise.resolve({ ...started, simulator: false }),
        onPay: () => undefined,
    },
} satisfies Meta<typeof HostedPayment>;

export default meta;
type Story = StoryObj<typeof meta>;

export const PayPal: Story = {};

export const AuthorizeNetOnTheSimulator: Story = {
    args: {
        gateway: 'authorize-net',
        simulator: true,
        onStart: () =>
            Promise.resolve({ ...started, gateway: 'authorize-net' }),
    },
};
