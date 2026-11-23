import type { Meta, StoryObj } from '@storybook/react-vite';
import { AddContactDialog } from './add-contact-dialog';

const meta = {
    title: 'Contacts/Add contact',
    component: AddContactDialog,
    args: {
        crms: [
            {
                connection: 1,
                integration: 'follow-up-boss',
                name: 'Follow Up Boss',
                account: 'james.miller@lonestar.example',
                takes_new_people: true,
                is_default: true,
            },
            {
                connection: 2,
                integration: 'lofty',
                name: 'Lofty',
                account: null,
                takes_new_people: true,
                is_default: false,
            },
            {
                connection: 3,
                integration: 'boldtrail',
                name: 'BoldTrail (kvCORE)',
                account: null,
                takes_new_people: false,
                is_default: false,
            },
        ],
    },
} satisfies Meta<typeof AddContactDialog>;

export default meta;

type Story = StoryObj<typeof meta>;

/** James has three CRMs; new contacts go to Follow Up Boss. */
export const TwoCrms: Story = {};

/** No CRM yet: the contact stays in REOS. */
export const NoCrm: Story = { args: { crms: [] } };
