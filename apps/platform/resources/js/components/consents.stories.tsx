import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    AgreementNote,
    ConsentHistory,
    PrivacySwitches,
} from '@/components/consents';
import type { ConsentRecordRow, ConsentSwitch } from '@/components/consents';

const switches: ConsentSwitch[] = [
    { purpose: 'marketing_email', on: false, chosen: false, at: null },
    { purpose: 'sms', on: true, chosen: true, at: '2026-11-20T15:04:00Z' },
    { purpose: 'ai_personal_data', on: true, chosen: false, at: null },
    { purpose: 'provider_network', on: true, chosen: false, at: null },
    { purpose: 'recruiting', on: false, chosen: false, at: null },
];

const records: ConsentRecordRow[] = [
    {
        id: 6,
        purpose: 'sms',
        subject: null,
        granted: true,
        version: '2026-11-22',
        method: 'app',
        by_admin: false,
        at: '2026-11-20T15:04:00Z',
        current: true,
        withdrawable: true,
    },
    {
        id: 4,
        purpose: 'session_recording',
        subject: 'session:12',
        granted: true,
        version: '2026-11-22',
        method: 'web',
        by_admin: false,
        at: '2026-11-19T17:30:00Z',
        current: true,
        withdrawable: true,
    },
    {
        id: 2,
        purpose: 'privacy_notice',
        subject: null,
        granted: true,
        version: '2026-11-22',
        method: 'web',
        by_admin: false,
        at: '2026-11-18T09:12:00Z',
        current: true,
        withdrawable: false,
    },
    {
        id: 1,
        purpose: 'terms',
        subject: null,
        granted: true,
        version: '2026-11-22',
        method: 'web',
        by_admin: false,
        at: '2026-11-18T09:12:00Z',
        current: true,
        withdrawable: false,
    },
];

/** Privacy switches on Settings › Privacy (A6.3): Maria Lopez, a candidate. */
const meta = {
    title: 'Privacy/Privacy switches',
    component: PrivacySwitches,
    args: { switches, onChange: () => undefined },
} satisfies Meta<typeof PrivacySwitches>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Candidate: Story = {};
export const ForAdmin: Story = { args: { person: 'Maria Lopez' } };
export const YourConsents: Story = {
    render: () => (
        <ConsentHistory records={records} onWithdraw={() => undefined} />
    ),
};
export const NoConsentsYet: Story = {
    render: () => <ConsentHistory records={[]} />,
};
export const SignUpAgreement: Story = { render: () => <AgreementNote /> };
