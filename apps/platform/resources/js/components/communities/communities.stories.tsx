import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { CommunityList, CommunityPanel, JoinCard } from './community';
import {
    jamesCommunities,
    jamesView,
    mayaCommunity,
    mayaInvite,
    mayaResults,
    noRoutes,
    roster,
} from './fixtures';
import { InvitePanel, ResultsView, RosterPanel, ScheduleForm } from './owner';

const meta = {
    title: 'Communities/Community, sessions and results',
    component: CommunityPanel,
    args: {
        community: mayaCommunity,
        routes: noRoutes,
        onChanged: () => undefined,
    },
} satisfies Meta<typeof CommunityPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Maya Chen's community as she runs it: 18 going to her loan estimate session. */
export const Owner: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('18 going'),
        ).toBeInTheDocument();
        await expect(
            within(canvasElement).getByText(
                "Information only. Doesn't count toward CE.",
            ),
        ).toBeInTheDocument();
    },
};

/** James Miller, a member: going to one session, a paid one to RSVP. */
export const Member: Story = { args: { community: jamesView } };

/** A new community: nothing yet. */
export const Empty: Story = {
    args: {
        community: { ...mayaCommunity, posts: [], resources: [], sessions: [] },
    },
};

/** The invite link and QR code. */
export const Invite: Story = {
    render: () => (
        <InvitePanel
            name={mayaCommunity.name}
            inviteUrl={mayaCommunity.invite_url ?? ''}
            qrUrl={null}
            newCodeUrl="#"
            onChanged={() => undefined}
        />
    ),
};

/** Scheduling on Pro: free only. */
export const Schedule: Story = {
    render: () => (
        <ScheduleForm url="#" canCharge={false} onScheduled={() => undefined} />
    ),
};

/** Ticking who came. */
export const Roster: Story = {
    render: () => (
        <RosterPanel
            title="Reading a loan estimate with your buyers"
            rows={roster}
            url="#"
            onSaved={() => undefined}
        />
    ),
};

/** Results after the session. */
export const Results: Story = {
    render: () => <ResultsView results={mayaResults} />,
};

/** My communities, and none yet. */
export const Mine: Story = {
    render: () => (
        <CommunityList communities={jamesCommunities} href={() => '#'} />
    ),
};
export const MineEmpty: Story = {
    render: () => <CommunityList communities={[]} href={() => '#'} />,
};

/** The invite, before joining. */
export const Join: Story = {
    render: () => (
        <JoinCard invite={mayaInvite} joinUrl="#" via="qr" openUrl="#" />
    ),
};
export const JoinNotVerified: Story = {
    render: () => (
        <JoinCard
            invite={{ ...mayaInvite, verified: false }}
            joinUrl="#"
            via="link"
            openUrl="#"
        />
    ),
};
