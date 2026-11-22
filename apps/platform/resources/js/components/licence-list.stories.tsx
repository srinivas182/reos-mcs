import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    licenceStates,
    louisianaLicence,
    texasLicence,
} from '@/components/licence-fixtures';
import { AddLicenceDialog, LicenceList } from '@/components/licence-list';

/**
 * Licences in Settings › Account and roles (web screen 23, A5.1): every
 * state licence with its status, sponsor, renewal date and how it was
 * checked. Licences in states that aren't live are tracked for renewal.
 */
const meta = {
    title: 'Settings/Licences',
    component: LicenceList,
    parameters: { layout: 'padded' },
    args: { licences: [texasLicence, louisianaLicence], states: licenceStates },
} satisfies Meta<typeof LicenceList>;

export default meta;
type Story = StoryObj<typeof meta>;

/** James Miller: Texas checked with TREC; Louisiana waiting for review and tracked only. */
export const TexasAndLouisiana: Story = {};

/** Before any licence is added. */
export const NoLicencesYet: Story = { args: { licences: [] } };

/** A sponsoring broker: Broker Responsibility is part of their CE (A5.2). */
export const SponsoringBroker: Story = {
    args: {
        licences: [
            {
                ...texasLicence,
                type: 'broker',
                number: '9123456',
                broker_role: 'sponsoring_broker',
                sponsor_name: null,
            },
        ],
    },
};

/** "Add a state license": the state and number, checked with the regulator. */
export const AddDialog: Story = {
    render: () => (
        <AddLicenceDialog open onOpenChange={() => {}} states={licenceStates} />
    ),
};
