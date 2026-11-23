import type { Meta, StoryObj } from '@storybook/react-vite';
import { ContactPanel } from './contact-panel';
import { insight, panel } from './fixtures';
import { InsightCard } from './insight-card';
import { ReachDialog } from './reach-dialog';

const meta = {
    title: 'Contacts/Contact panel',
    component: ContactPanel,
    args: { panel, onClose: () => {} },
} satisfies Meta<typeof ContactPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 13: Maria Lopez from three CRMs and the phone, with the AI next step. */
export const WithInsight: Story = {};

/** Before the agent asks for a summary. */
export const NoInsightYet: Story = {
    args: { panel: { ...panel, insight: null } },
};

/** No phone number, no notes and no tasks yet. */
export const NewLead: Story = {
    args: {
        panel: {
            ...panel,
            contact: {
                ...panel.contact,
                name: 'Angela Brooks',
                phones: [],
                stage: null,
                sources: [panel.contact.sources[3]],
            },
            notes: [],
            tasks: [],
            activity: [],
            insight: null,
            segment: 'new',
            last_touch_at: null,
        },
    },
};

/** The insight while it's being written. */
export const Writing: StoryObj<typeof InsightCard> = {
    render: () => <InsightCard insight={null} busy onAsk={() => {}} />,
};

/** Demo mode: Bedrock isn't connected, so the simulator wrote it. */
export const Simulated: StoryObj<typeof InsightCard> = {
    render: () => (
        <InsightCard
            insight={{ ...insight, engine: 'simulator' }}
            busy={false}
            onAsk={() => {}}
        />
    ),
};

/** Text from your own phone: the suggested text, a QR code to scan. */
export const TextButton: StoryObj<typeof ReachDialog> = {
    render: () => (
        <ReachDialog
            channel="text"
            name="Maria Lopez"
            phone="+18325550123"
            draft={insight.draft}
            onLogged={() => {}}
        />
    ),
};
