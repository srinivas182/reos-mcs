import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { ChooseContract } from './choose-contract';
import { ContractAsk } from './contract-ask';
import { overview, review } from './fixtures';
import { ReviewProgress } from './review-progress';
import { ReviewResult } from './review-result';

const meta = {
    title: 'Contracts/Review result',
    component: ReviewResult,
    args: {
        review: review(),
        onPage: fn(),
        onAddDeadlines: fn(),
        onSendToBroker: fn(),
    },
} satisfies Meta<typeof ReviewResult>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Result (screen 65): High first, dated findings with pages and rules, deadlines, what changed. */
export const Result: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText("The sales price doesn't add up"),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('link', { name: /22 TAC §537.11/ }),
        ).toBeInTheDocument();
        await expect(canvas.getByText('was 440,000.00')).toBeInTheDocument();
        await userEvent.click(
            canvas.getAllByRole('button', { name: 'p. 3' })[0],
        );
        await expect(args.onPage).toHaveBeenCalledWith(3);
        await userEvent.click(
            canvas.getByRole('button', {
                name: 'Add deadlines to my calendar',
            }),
        );
        await expect(args.onAddDeadlines).toHaveBeenCalled();
    },
};

/** Sent to the broker, deadlines added. */
export const Done: Story = {
    args: {
        review: review({
            deadlines_added: true,
            broker_question: 'q-1',
            findings: [],
        }),
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByRole('button', { name: 'With your broker' }),
        ).toBeDisabled();
        await expect(canvas.getByText('Nothing to flag')).toBeInTheDocument();
    },
};

/** Reviewing (screen 64). */
export const Reviewing: StoryObj<typeof ReviewProgress> = {
    render: (args) => <ReviewProgress {...args} />,
    args: { step: 'checks' },
};

/** Choose (screen 63): PDF, photos, the sample and deal documents, with the cost first. */
export const Choose: StoryObj<typeof ChooseContract> = {
    render: (args) => <ChooseContract {...args} />,
    args: {
        overview: overview(),
        onFile: fn(),
        onDealFile: fn(),
        onSample: fn(),
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('20 credits per review · 460 left'),
        ).toBeInTheDocument();
        await userEvent.click(canvas.getByRole('button', { name: 'Review' }));
        await expect(args.onDealFile).toHaveBeenCalledWith(
            '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e90',
        );
    },
};

/** Ask about this contract (screen 66): the answer with pages, and a note for the client. */
export const Ask: StoryObj<typeof ContractAsk> = {
    render: (args) => <ContractAsk {...args} />,
    args: {
        cost: 1,
        onAsk: fn(),
        questions: [
            {
                question: 'When does the option period end?',
                answer: 'Page 2 says: Buyer has the unrestricted right to terminate this contract by giving notice of termination to Seller within 7 days after the Effective Date of this contract (Option Period).',
                pages: [2],
                note: "Hi Ana Lopez, here's what the contract says: the option period is 7 days after the Effective Date. Let me know if you have any questions.",
                asked_at: '2026-11-24T15:05:00Z',
            },
        ],
    },
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.type(
            canvas.getByLabelText('Your question'),
            'Who holds the earnest money?',
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Ask' }));
        await expect(args.onAsk).toHaveBeenCalledWith(
            'Who holds the earnest money?',
        );
    },
};
