import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { AnswerCard } from './answer-card';
import { AskForm } from './ask-form';
import { BrokerReply } from './broker-reply';
import { brokerData, question } from './fixtures';
import { PolicyKnowledge } from './policy-knowledge';
import { QuestionList } from './question-list';

const meta = {
    title: 'Virtual broker/Answer',
    component: AnswerCard,
    args: { question: question(), onEscalate: fn() },
} satisfies Meta<typeof AnswerCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A money question with the broker (screen 61): TREC's rule first, the expected time and why. */
export const WithYourBroker: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('With your broker')).toBeInTheDocument();
        await expect(canvas.getByText(/Money is involved/)).toBeInTheDocument();
        await expect(
            canvas.getAllByText('22 TAC §535.146').length,
        ).toBeGreaterThan(0);
        await expect(
            canvas.queryByRole('button', { name: 'Send to my broker' }),
        ).toBeNull();
    },
};

/** Answered by the virtual broker: the agent can still send it to the broker. */
export const Answered: Story = {
    args: {
        question: question({
            question: 'Do I leave a business card after a showing?',
            answer: 'The policy manual (page 9) says: Agents confirm showings through ShowingTime and leave a business card on the kitchen counter after every showing.',
            status: 'answered',
            reason: null,
            confidence: 'medium',
            due_at: null,
            deal: null,
            sources: [
                {
                    kind: 'manual',
                    title: '9. Showings',
                    citation: null,
                    page: 9,
                    url: null,
                    effective_from: '2026-11-20',
                    excerpt:
                        'Agents confirm showings through ShowingTime and leave a business card on the kitchen counter after every showing.',
                },
            ],
        }),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Send to my broker' }),
        );
        await expect(args.onEscalate).toHaveBeenCalled();
    },
};

/** The broker's reply (screen 62), labelled and marked Corrected. */
export const FromYourBroker: Story = {
    args: {
        question: question({
            status: 'corrected',
            broker: 'Linda Park',
            broker_answer:
                'Deposit it with the title company by the second working day. I updated the manual.',
        }),
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('From your broker, Linda Park'),
        ).toBeInTheDocument();
        await expect(canvas.getByText('Corrected')).toBeInTheDocument();
    },
};

/** Ask (web screen 16). */
export const Ask: StoryObj<typeof AskForm> = {
    render: (args) => <AskForm {...args} />,
    args: { brokerage: 'Lone Star Realty', onAsk: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.type(
            canvas.getByLabelText("Ask Lone Star Realty's virtual broker"),
            'Who approves my yard sign?',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Ask' }));
        await expect(args.onAsk).toHaveBeenCalledWith(
            'Who approves my yard sign?',
        );
    },
};

/** The conversation list, and its empty state. */
export const Conversations: StoryObj<typeof QuestionList> = {
    render: (args) => <QuestionList {...args} />,
    args: {
        questions: brokerData().waiting.concat(brokerData().answered),
        selected: null,
        onSelect: fn(),
        showAgent: true,
        emptyTitle: 'Nothing waiting',
        emptyBody: 'Questions appear here.',
    },
};

export const NoQuestions: StoryObj<typeof QuestionList> = {
    render: (args) => <QuestionList {...args} />,
    args: {
        questions: [],
        selected: null,
        onSelect: fn(),
        emptyTitle: 'Ask your first question',
        emptyBody: 'Answers show where they come from.',
    },
};

/** Answer an agent (broker journey screen 12): the draft, Confirmed or Corrected, Add to policy knowledge. */
export const AnswerAnAgent: StoryObj<typeof BrokerReply> = {
    render: (args) => <BrokerReply {...args} />,
    args: { question: question(), onSend: fn() },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Sent as is, the agent sees it as Confirmed.'),
        ).toBeInTheDocument();
        await userEvent.type(
            canvas.getByLabelText("Your answer (the virtual broker's draft)"),
            ' I updated the manual.',
        );
        await expect(
            canvas.getByText(
                'You changed the draft: the agent sees it as Corrected.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Send answer' }),
        );
        await expect(args.onSend).toHaveBeenCalled();
    },
};

/** Teach the virtual broker (broker screen 34): manual, TREC conflicts, FAQ and escalation. */
export const Knowledge: StoryObj<typeof PolicyKnowledge> = {
    render: (args) => <PolicyKnowledge {...args} />,
    args: {
        data: brokerData(),
        onUpload: fn(),
        onRemoveFaq: fn(),
        onSaveSettings: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('1 conflict with TREC rules'),
        ).toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', { name: 'Remove' }));
        await expect(args.onRemoveFaq).toHaveBeenCalledWith(4);
        await userEvent.selectOptions(
            canvas.getByLabelText('Escalations go to'),
            '2',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Save' }));
        await expect(args.onSaveSettings).toHaveBeenCalledWith(2, 4);
    },
};

/** Before the first upload. */
export const NoManual: StoryObj<typeof PolicyKnowledge> = {
    render: (args) => <PolicyKnowledge {...args} />,
    args: {
        data: brokerData({
            manual: null,
            versions: [],
            conflicts: [],
            faq: [],
        }),
        onUpload: fn(),
        onRemoveFaq: fn(),
        onSaveSettings: fn(),
    },
};
