import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { draft, james, listing } from './fixtures';
import { ListingCard } from './listing-card';
import { MemberCard } from './member-card';
import { PostDialog } from './post-dialog';

const meta = {
    title: 'Agent/My listings',
    component: ListingCard,
    args: { listing: listing() },
    decorators: [
        (Story) => (
            <div className="max-w-sm">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof ListingCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** An active HAR listing with Saturday's open house. */
export const Active: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Active')).toBeInTheDocument();
        await expect(canvas.getByText(/Listing agent/)).toBeInTheDocument();
        await expect(canvas.getByText(/Open house/)).toBeInTheDocument();
    },
};

/** Pending, where James is the co-listing agent. */
export const CoListed: Story = {
    args: {
        listing: listing({
            status: 'pending',
            role: 'co_list',
            address: '5520 Heron Lake Ln, Houston, TX 77041',
            price: 412500,
            baths: 2.5,
            open_house: null,
        }),
    },
};

/** Sold: the closing price, and no photo yet. */
export const Sold: Story = {
    args: {
        listing: listing({
            status: 'closed',
            close_price: 331000,
            photo: null,
            photos: [],
            can_post: false,
            open_house: null,
        }),
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Sold for $331,000'),
        ).toBeInTheDocument();
    },
};

/** "Is this you?": the HAR member record under James's license number. */
export const FoundByLicense: Story = {
    render: () => {
        const confirm = fn();

        return (
            <MemberCard member={james} onConfirm={confirm} onDecline={fn()} />
        );
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('James Miller')).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Yes, this is me' }),
        );
    },
};

/** A listing post: two of the four photos the MLS allows, with HAR's attribution always added. */
export const ListingPost: Story = {
    render: () => (
        <PostDialog
            draft={draft()}
            open
            onOpenChange={fn()}
            onSave={fn(async () => {})}
        />
    ),
    play: async () => {
        const body = within(document.body);
        await expect(
            await body.findByText(
                'Up to 4 photos, as the MLS allows. 2 chosen.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            body.getByRole('button', {
                name: 'Photo 3 of 2418 Willow Bend Dr, Katy, TX 77494',
            }),
        );
        await expect(
            body.getByText('Up to 4 photos, as the MLS allows. 3 chosen.'),
        ).toBeInTheDocument();
        await expect(
            body.getByText(
                'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
            ),
        ).toBeInTheDocument();
    },
};
