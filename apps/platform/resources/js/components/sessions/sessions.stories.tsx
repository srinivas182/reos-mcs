import type { Meta, StoryObj } from '@storybook/react-vite';
import { contacts, session } from '@/components/sessions/fixtures';
import { ScheduleDialog } from '@/components/sessions/schedule-dialog';
import { SessionCard } from '@/components/sessions/session-card';
import { SessionNotes } from '@/components/sessions/session-notes';
import { SessionRoom } from '@/components/sessions/session-room';
import { SessionShares } from '@/components/sessions/session-shares';

/** Video sessions (B6.3): Dr. Rachel Hughes and Sofia Reyes at Texas Realty School. */
const meta = {
    title: 'Sessions/Session card',
    component: SessionCard,
    args: { session: session(), me: 7 },
} satisfies Meta<typeof SessionCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Scheduled: Story = {};
export const OpenToJoin: Story = {
    args: { session: session({ can_join: true, status: 'live' }) },
};
export const LiveClass: Story = {
    args: {
        session: session({
            kind: 'live_class',
            title: 'Exam prep: contracts and disclosures',
        }),
    },
};
export const Ended: Story = {
    args: { session: session({ status: 'ended' }) },
};
export const Room: Story = {
    render: () => (
        <SessionRoom
            session={session({ status: 'live', can_join: true })}
            connection={{
                simulated: true,
                url: null,
                token: null,
                ends_at: new Date(Date.now() + 90_000).toISOString(),
            }}
            me={7}
            onLeave={() => undefined}
            onEnd={() => undefined}
        />
    ),
};
export const Notes: Story = {
    render: () => (
        <SessionNotes
            session={session({
                status: 'ended',
                notes: 'Practice exam score: 68%. Focus on agency and contracts.',
                notes_updated_at: '2026-11-24T21:46:00Z',
                notes_updated_by: 'Dr. Rachel Hughes',
            })}
        />
    ),
};
export const Schedule: Story = {
    render: () => (
        <ScheduleDialog
            open
            onOpenChange={() => undefined}
            contacts={contacts}
            lengths={[15, 30, 45, 60, 90, 120]}
        />
    ),
};
export const Shared: Story = {
    render: () => (
        <SessionShares
            shares={[
                {
                    id: 1,
                    kind: 'lesson',
                    at: '2026-11-24T21:10:00Z',
                    by: 'Dr. Rachel Hughes',
                    lesson: {
                        id: 12,
                        title: 'Earnest money and the option fee',
                        course: 'Principles of Real Estate II',
                    },
                    topic: null,
                    questions: [],
                },
                {
                    id: 2,
                    kind: 'questions',
                    at: '2026-11-24T21:20:00Z',
                    by: 'Dr. Rachel Hughes',
                    lesson: null,
                    topic: 'agency',
                    questions: [
                        {
                            id: 3,
                            question:
                                'A buyer’s agent learns the seller will accept less. The buyer asks. What should the agent do?',
                            options: [
                                'Keep it to themselves',
                                'Share it with the buyer, their client',
                                'Tell the seller’s agent first',
                                'Ask the broker to decide',
                            ],
                            correct: 1,
                            explanation:
                                'A buyer’s agent owes the buyer loyalty and disclosure of facts that matter to them.',
                            source: 'TREC Rule 535.2',
                        },
                    ],
                },
            ]}
        />
    ),
};
