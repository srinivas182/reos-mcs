import type { Meta, StoryObj } from '@storybook/react-vite';
import { LessonPlayer } from '@/components/lesson-player';

const transcript = [
    {
        seconds: 1,
        text: 'Welcome back. Today we talk about agency disclosure in Texas.',
    },
    {
        seconds: 20,
        text: 'Every license holder must explain who they represent.',
    },
    {
        seconds: 245,
        text: 'So before you talk about price or terms, hand them the IABS form.',
    },
    {
        seconds: 252,
        text: 'That is the Information About Brokerage Services notice.',
    },
];

/** A lesson's video and synced transcript (A5.3b), opened at a search result's moment. */
const meta = {
    title: 'Learn/Lesson player',
    component: LessonPlayer,
    args: {
        title: '6.2 Agency disclosure',
        video: { type: 'file', url: '', duration: 754, poster: null },
        transcript,
        startAt: 245,
    },
} satisfies Meta<typeof LessonPlayer>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Opened from a search for "IABS": starts at 4:05. */
export const AtTheMoment: Story = {};
export const TranscriptOnly: Story = { args: { video: null, startAt: null } };
