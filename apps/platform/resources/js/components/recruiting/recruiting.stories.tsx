import type { Meta, StoryObj } from '@storybook/react-vite';
import { offer } from '@/components/licence/fixtures';
import { OfferDialog } from './offer-dialog';
import type { RecruitCandidate } from './types';

const ana: RecruitCandidate = {
    id: 41,
    first_name: 'Ana',
    city: 'Katy',
    stage: 'exam_booked',
    offer: null,
};

const meta = {
    title: 'Broker/Recruiting',
    component: OfferDialog,
    args: { candidate: ana, onClose: () => undefined },
} satisfies Meta<typeof OfferDialog>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A new offer to Ana in Katy: office, split, fees, training, mentoring. */
export const NewOffer: Story = {};

/** Changing an open offer. */
export const ChangeOffer: Story = {
    args: {
        candidate: {
            ...ana,
            offer: offer({
                brokerage: 'Lone Star Realty',
                office_city: 'Houston',
                fit: false,
            }),
        },
    },
};
