import type { Meta, StoryObj } from '@storybook/react-vite';
import { reviewItems, reviewLines } from './fixtures';
import { LineCard } from './line-card';
import { ReviewItems } from './review-items';

const meta = {
    title: 'Instructor/AI lesson review',
    component: LineCard,
    args: {
        lessonId: 23,
        line: reviewLines[1],
        locked: false,
        onEdit: () => {},
    },
} satisfies Meta<typeof LineCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 44: a flagged line with its legal source, a suggestion and a comment. */
export const FlaggedLine: Story = {};

export const ApprovedLine: Story = { args: { line: reviewLines[0] } };

/** Edited, waiting to be recorded again. */
export const EditedLine: Story = { args: { line: reviewLines[2] } };

export const CheckinsAndFlashcards: Story = {
    render: () => (
        <ReviewItems lessonId={23} items={reviewItems} locked={false} />
    ),
};
