import type { Meta, StoryObj } from '@storybook/react-vite';
import { hits } from '@/components/content-fixtures';
import { KnowledgeResults } from '@/components/knowledge-results';

/** Knowledge base results (A5.3): each opens the lesson at the page, moment or heading. */
const meta = {
    title: 'Content/Knowledge results',
    component: KnowledgeResults,
    args: { query: 'IABS first contact', results: hits },
} satisfies Meta<typeof KnowledgeResults>;

export default meta;
type Story = StoryObj<typeof meta>;

export const SlideTranscriptAndText: Story = {};
export const NothingFound: Story = {
    args: { query: 'mineral rights in Louisiana', results: [] },
};
