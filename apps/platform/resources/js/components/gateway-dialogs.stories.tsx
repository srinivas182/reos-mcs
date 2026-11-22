import type { Meta, StoryObj } from '@storybook/react-vite';
import type { GatewayOptions, GatewayRow } from '@/components/gateway-dialogs';
import {
    GatewayDialog,
    PauseGatewayDialog,
} from '@/components/gateway-dialogs';

const paypal: GatewayRow = {
    id: 'paypal',
    name: 'PayPal',
    mode: 'sandbox',
    running: true,
    is_default: false,
    position: 1,
    states: ['TX'],
    used_for: ['package'],
    tenant_accounts: false,
    paused: false,
    paused_reason: null,
    capabilities: {
        recurring: false,
        tax: false,
        payouts: false,
        tenant_accounts: false,
    },
    health: 'healthy',
    orders_today: { paid: 12, expired: 1 },
};

const options: GatewayOptions = {
    kinds: ['plan', 'course', 'package', 'credit_pack', 'seat_pack'],
    states: [
        { code: 'FL', name: 'Florida' },
        { code: 'TX', name: 'Texas' },
    ],
};

/** How one gateway is used on Payment gateways (screen 66): states, products, default, tenant accounts. */
const meta = {
    title: 'Billing/Gateway settings',
    component: GatewayDialog,
    args: { gateway: paypal, options, onOpenChange: () => undefined },
} satisfies Meta<typeof GatewayDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const PayPalForTexasPackages: Story = {};

export const StripeWithTenantAccounts: Story = {
    args: {
        gateway: {
            ...paypal,
            id: 'stripe',
            name: 'Stripe',
            is_default: true,
            states: null,
            used_for: null,
            tenant_accounts: true,
            capabilities: {
                recurring: true,
                tax: true,
                payouts: true,
                tenant_accounts: true,
            },
        },
    },
};

/** The kill switch, with a reason for the audit log. */
export const PauseStripe: StoryObj<typeof PauseGatewayDialog> = {
    render: () => (
        <PauseGatewayDialog
            gateway={{ ...paypal, id: 'stripe', name: 'Stripe' }}
            onOpenChange={() => undefined}
        />
    ),
};
