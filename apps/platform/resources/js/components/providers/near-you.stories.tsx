import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import {
    chenNetwork,
    chenReviewable,
    jamesNear,
    nobodyNear,
} from './near-fixtures';
import { NearYou, ProviderNetworkPanel } from './near-you';

const meta = {
    title: 'Providers/Providers near you',
    component: NearYou,
    args: {
        near: jamesNear,
        category: null,
        profileHref: (id: number) => `/storybook/providers/${id}`,
        categoryHref: (key: string | null) =>
            `/storybook/providers?category=${key ?? ''}`,
    },
} satisfies Meta<typeof NearYou>;

export default meta;

type Story = StoryObj<typeof meta>;

const routes = {
    connect: '/storybook/providers/3/connect',
    review: '/storybook/providers/3/reviews',
    requests: '/storybook/providers/requests',
};

/** James in Katy: Patel Inspections and Chen Home Lending near him. */
export const InKaty: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Serving Katy'),
        ).toBeInTheDocument();
    },
};

/** No deals or MLS record yet: every provider shows. */
export const NothingNear: Story = { args: { near: nobodyNear } };

/** A lender's profile: Request to connect, and one review. */
export const ConnectPanel: Story = {
    render: () => (
        <ProviderNetworkPanel
            name="Chen Home Lending"
            network={chenNetwork}
            routes={routes}
        />
    ),
};

/** Connected for 31 days: James can review. */
export const ReviewPanel: Story = {
    render: () => (
        <ProviderNetworkPanel
            name="Chen Home Lending"
            network={chenReviewable}
            routes={routes}
        />
    ),
};
