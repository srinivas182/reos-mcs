import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import {
    ProviderLicenceReviewQueue,
    ProviderLicences,
    type ProviderLicenceRow,
} from './provider-licences';

const verified: ProviderLicenceRow = {
    id: 1,
    kind: 'trec_inspector',
    number: '0024567',
    status: 'verified',
    name_on_record: 'Pat Rivera',
    licence_type: 'PRIN',
    expires_on: '2027-08-31',
    checked_via: 'trec-licence',
    review_note: null,
    consumer_url: null,
};

const meta = {
    title: 'Provider/Professional license',
    component: ProviderLicences,
    args: { licences: [verified] },
} satisfies Meta<typeof ProviderLicences>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Pat Rivera's TREC inspector license, verified from TREC's data. */
export const Verified: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Verified')).toBeInTheDocument();
        await expect(
            canvas.getByText(/Checked with TREC's public license data/),
        ).toBeInTheDocument();
    },
};

/** An NMLS ID: the REOS team is checking. */
export const WithReviewer: Story = {
    args: {
        licences: [
            {
                ...verified,
                id: 2,
                kind: 'nmls',
                number: '123456',
                status: 'review',
                name_on_record: null,
                licence_type: null,
                expires_on: null,
                checked_via: 'reviewer',
                review_note:
                    'NMLS has no public lookup: a reviewer checks NMLS Consumer Access.',
                consumer_url:
                    'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/123456',
            },
        ],
    },
};

/** On People: an NMLS ID to check on NMLS Consumer Access. */
export const ReviewQueue: Story = {
    render: () => (
        <ProviderLicenceReviewQueue
            items={[
                {
                    id: 2,
                    person: { id: 9, name: 'Max Chen' },
                    kind: 'nmls',
                    number: '123456',
                    name_on_record: null,
                    note: 'NMLS has no public lookup: a reviewer checks NMLS Consumer Access.',
                    consumer_url:
                        'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/123456',
                    created_at: '2026-11-24T10:00:00Z',
                },
            ]}
        />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByRole('link', { name: 'Open on NMLS Consumer Access' }),
        ).toHaveAttribute(
            'href',
            'https://www.nmlsconsumeraccess.org/EntityDetails.aspx/INDIVIDUAL/123456',
        );
    },
};
