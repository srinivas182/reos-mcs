import type { Meta, StoryObj } from '@storybook/react-vite';
import { CheckStatus } from '@/components/check-status';

/** The result of "Test connection" for one set of integration keys. */
const meta = {
    title: 'Display/Check status',
    component: CheckStatus,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-xl">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof CheckStatus>;

export default meta;
type Story = StoryObj<typeof meta>;

const base = {
    trigger: 'manual',
    checked_by: 'REOS Operations',
    duration_ms: 412,
    created_at: '2026-11-20T15:42:00Z',
    parameters: {},
} as const;

export const NotTested: Story = { args: { check: null } };

export const Connected: Story = {
    args: {
        check: {
            ...base,
            outcome: 'passed',
            message: 'integrations.checks.passed',
        },
    },
};

export const Rejected: Story = {
    args: {
        check: {
            ...base,
            outcome: 'failed',
            message: 'integrations.checks.rejected',
        },
    },
};

export const HealthCheckUnreachable: Story = {
    args: {
        check: {
            ...base,
            trigger: 'scheduled',
            checked_by: null,
            outcome: 'failed',
            message: 'integrations.checks.unreachable',
        },
    },
};

export const NoAutomaticCheckYet: Story = {
    args: {
        check: {
            ...base,
            outcome: 'unverified',
            message: 'integrations.checks.noAutomaticCheck',
            parameters: { sprint: 7 },
        },
    },
};
