import type { Meta, StoryObj } from '@storybook/react-vite';
import type { FlagOptions, FlagRow } from '@/components/feature-flag-dialogs';
import {
    ReleaseDialog,
    RolloutDialog,
} from '@/components/feature-flag-dialogs';

const options: FlagOptions = {
    stages: ['off', 'internal', 'tenants', 'states', 'percent', 'everyone'],
    roles: [
        'candidate',
        'agent',
        'broker',
        'mentor',
        'instructor',
        'school_admin',
        'provider',
        'association_admin',
    ],
    states: [{ code: 'TX', name: 'Texas' }],
    tenants: [
        { id: 4, name: 'Summit Realty', type: 'brokerage' },
        { id: 7, name: 'Lone Star Real Estate School', type: 'school' },
        { id: 9, name: 'Harbor Homes', type: 'brokerage' },
    ],
    window_minutes: 10,
};

const messages: FlagRow = {
    key: 'messages',
    name: 'Messages',
    description:
        'Conversations and announcements, live on the web and the phone.',
    owner: 'Team B',
    tenant_feature: false,
    stage: 'tenants',
    tenants: [{ id: 4, name: 'Summit Realty' }],
    states: [],
    percent: 0,
    roles: ['agent', 'broker'],
    error_threshold: 5,
    stage_changed_at: '2026-11-22T09:00:00Z',
    killed_at: null,
    rolled_back_at: null,
    rolled_back_from: null,
    uses: 412,
    errors: 3,
};

/** Change rollout on screen 70 (A6.2): REOS staff → named tenants → states → a share → everyone. */
const meta = {
    title: 'Back office/Feature rollout',
    component: RolloutDialog,
    args: { flag: messages, options, onOpenChange: () => undefined },
} satisfies Meta<typeof RolloutDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NamedTenants: Story = {};
export const ShareOfTenants: Story = {
    args: {
        flag: {
            ...messages,
            stage: 'percent',
            states: ['TX'],
            percent: 25,
            roles: null,
        },
    },
};
export const NewRelease: Story = {
    render: () => (
        <ReleaseDialog
            open
            flags={[
                { key: 'messages', name: 'Messages', stage: 'tenants' },
                { key: 'video_rooms', name: 'Video rooms', stage: 'internal' },
            ]}
            roles={['agent', 'broker', 'school_admin']}
            today="2026-11-22"
            onOpenChange={() => undefined}
        />
    ),
};
