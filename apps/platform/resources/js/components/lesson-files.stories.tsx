import type { Meta, StoryObj } from '@storybook/react-vite';
import { files, kinds } from '@/components/content-fixtures';
import { LessonFiles } from '@/components/lesson-files';

/** A lesson's files (A5.3): uploaded straight to storage, scanned, then processed. */
const meta = {
    title: 'Content/Lesson files',
    component: LessonFiles,
    args: { lessonId: 7, files, kinds },
} satisfies Meta<typeof LessonFiles>;

export default meta;
type Story = StoryObj<typeof meta>;

/** "Agency disclosure": a captioned video, slides, a worksheet being read and a refused file. */
export const VideoSlidesAndWorksheet: Story = {};
export const NoFilesYet: Story = { args: { files: [] } };
