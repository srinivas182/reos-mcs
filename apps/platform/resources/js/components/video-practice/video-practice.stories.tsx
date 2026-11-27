import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, within } from 'storybook/test';
import { overviewFixture, reviewFixture } from './fixtures';
import {
    ReviewView,
    VideoRecentList,
    VideoRecorder,
} from './video-practice-view';

const meta = {
    title: 'Practice/Video practice',
    component: ReviewView,
    args: {
        review: reviewFixture(),
        lessonHref: (lesson: string) => `/learn?topic=${lesson}`,
    },
} satisfies Meta<typeof ReviewView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A pitch that runs fast, with a few fillers and no close. */
export const Review: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('66 out of 100')).toBeInTheDocument();
        await expect(
            canvas.getByText('169 words a minute'),
        ).toBeInTheDocument();
        await expect(canvas.getByText('“um” × 3')).toBeInTheDocument();
    },
};

/** Words that need fixing before the video is posted. */
export const BeforeYouPost: Story = {
    args: {
        review: reviewFixture({
            compliance: [
                { code: 'claims', phrase: 'I guarantee' },
                { code: 'broker', phrase: null },
            ],
        }),
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Misleading claims: “I guarantee”'),
        ).toBeInTheDocument();
    },
};

/** The recorder before recording (the browser here has no camera). */
export const Recorder: StoryObj<typeof VideoRecorder> = {
    render: () => (
        <VideoRecorder
            kinds={['listing_pitch', 'video_intro']}
            maxSeconds={120}
            busy={false}
            onReview={fn()}
        />
    ),
};

/** Recent reviews, and the empty state. */
export const Recent: StoryObj<typeof VideoRecentList> = {
    render: () => (
        <div className="space-y-6">
            <VideoRecentList
                recent={overviewFixture().recent}
                href={(id) => `/video-practice/${id}`}
            />
            <VideoRecentList
                recent={[]}
                href={(id) => `/video-practice/${id}`}
            />
        </div>
    ),
};
