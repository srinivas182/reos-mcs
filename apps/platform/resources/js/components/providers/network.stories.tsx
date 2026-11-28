import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { ConnectionCard, ProviderRequestsInbox } from './network';
import {
    bothShared,
    emptyNetwork,
    jamesNetwork,
    mayaSide,
} from './network-fixtures';

const routes = {
    accept: (id: string) => `/storybook/providers/requests/${id}/accept`,
    decline: (id: string) => `/storybook/providers/requests/${id}/decline`,
    share: (id: string) => `/storybook/provider-connections/${id}/share`,
    block: (id: string) => `/storybook/provider-connections/${id}/block`,
    preferences: '/storybook/providers/preferences',
    chat: (c: string) => `/storybook/messages?c=${c}`,
};

const meta = {
    title: 'Providers/Provider requests',
    component: ProviderRequestsInbox,
    args: { network: jamesNetwork, routes },
} satisfies Meta<typeof ProviderRequestsInbox>;

export default meta;

type Story = StoryObj<typeof meta>;

/** James: Patel Inspections waiting, connected with Chen Home Lending, title companies off. */
export const WithRequest: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', {
                name: 'Accept Patel Inspections',
            }),
        ).toBeEnabled();
    },
};

/** Nothing waiting and no connections yet. */
export const Empty: Story = { args: { network: emptyNetwork } };

/** Both shared: phone and email show. */
export const ContactsShared: Story = {
    render: () => (
        <ul>
            <ConnectionCard connection={bothShared} routes={routes} />
        </ul>
    ),
};

/** Maya's side, waiting for her to share. */
export const ProviderSide: Story = {
    render: () => (
        <ul>
            <ConnectionCard connection={mayaSide} routes={routes} />
        </ul>
    ),
};
