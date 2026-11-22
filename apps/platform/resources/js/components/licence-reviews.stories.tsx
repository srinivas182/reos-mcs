import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    licenceReviews,
    louisianaLicence,
    texasLicence,
} from '@/components/licence-fixtures';
import {
    LicenceReviewQueue,
    PersonLicences,
} from '@/components/licence-reviews';

/**
 * Licence reviews on People (super admin, A5.1): no licence counts without
 * a regulator match or a reviewer's approval.
 */
const meta = {
    title: 'Admin/Licence reviews',
    component: LicenceReviewQueue,
    parameters: { layout: 'padded' },
    args: { items: licenceReviews },
} satisfies Meta<typeof LicenceReviewQueue>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Two licences waiting: one in a state with no check yet, one under another name. */
export const Waiting: Story = {};

/** On James Miller's page: his checked Texas licence and the Louisiana one to review. */
export const OnAPersonsPage: Story = {
    render: () => (
        <PersonLicences licences={[texasLicence, louisianaLicence]} />
    ),
};
