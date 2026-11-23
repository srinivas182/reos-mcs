import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { CalmDown } from './calm-down';
import { journey, offer, passFeed } from './fixtures';
import { OfferCard } from './offer-card';
import { OffersOptIn } from './offers-opt-in';
import { PassCard } from './pass-card';
import { ShareNews } from './share-news';
import { ReadyToBook } from './ready-to-book';
import { RetakePlan } from './retake-plan';
import { StepsList } from './steps-list';

const meta = {
    title: 'Candidate/Licence journey',
    component: StepsList,
    args: { steps: journey().steps, onCheck: fn() },
} satisfies Meta<typeof StepsList>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 16: Texas steps from TREC, two done, each with what to do. */
export const Steps: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('1. Qualifying education'),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'Mark done' })[0]!,
        );
        await expect(args.onCheck).toHaveBeenCalledWith('fingerprints', true);
    },
};

/** Screen 15: ready to book with Pearson VUE, the exam date saved. */
export const Ready: Story = {
    render: () => <ReadyToBook journey={journey()} onBook={fn()} />,
};

/** Not ready yet: what's left before booking. */
export const NotReady: Story = {
    render: () => (
        <ReadyToBook
            journey={journey({
                readiness: {
                    percent: 78,
                    national: 84,
                    state: 78,
                    line: 90,
                    ready: false,
                    simulations: 1,
                    needed: 2,
                },
                exam: {
                    ...journey().exam,
                    on: null,
                    time: null,
                    centre: null,
                    days_to: null,
                    directions_url: null,
                },
            })}
            onBook={fn()}
        />
    ),
};

/** Screen 19: the state portion missed; the national portion kept. */
export const Retake: Story = {
    render: () => (
        <RetakePlan
            retake={{
                portions: ['state'],
                topics: ['state_rules'],
                more_education: false,
                attempts_left: { state: 2 },
            }}
            onStart={fn()}
        />
    ),
};

/** Every attempt used: more education first. */
export const RetakeMoreEducation: Story = {
    render: () => (
        <RetakePlan
            retake={{
                portions: ['national'],
                topics: ['finance', 'math'],
                more_education: true,
                attempts_left: { national: 0 },
            }}
            onStart={fn()}
        />
    ),
};

/** Screen 14: the two-minute calm-down. */
export const CalmDownAudio: Story = {
    render: () => <CalmDown />,
};

/** f05: share the news after passing; never scores. */
export const Share: Story = {
    render: () => (
        <ShareNews state="Texas" city="Katy" onShare={fn()} onSkip={fn()} />
    ),
};

/** Screen 17 from the tracker: opted in, two offers waiting. */
export const OffersOptedIn: Story = {
    render: () => (
        <OffersOptIn
            state="Texas"
            offers={{ opted_in: true, city: 'Katy', open: 2 }}
            onSave={fn()}
        />
    ),
};

/** Screen 17: the best-fit offer, with Accept and Decline. */
export const Offer: Story = {
    render: () => (
        <OfferCard offer={offer()} onAccept={fn()} onDecline={fn()} />
    ),
};

/** An accepted offer: the brokerage will reach out. */
export const OfferAccepted: Story = {
    render: () => (
        <OfferCard
            offer={offer({
                status: 'accepted',
                fit: false,
                rating: null,
                ratings: 0,
            })}
        />
    ),
};

/** f06: Jordan from Katy passed, with a tip and Send congrats. */
export const Pass: Story = {
    render: () => <PassCard pass={passFeed().passes[0]!} onCongrats={fn()} />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Jordan from Katy passed the Texas exam!'),
        ).toBeInTheDocument();
    },
};
