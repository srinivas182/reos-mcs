import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import { Calculator } from './calculator';
import { simulationResults } from './fixtures';
import { PortionScores } from './portion-scores';
import { TestIntroDialog } from './test-intro-dialog';

const meta = {
    title: 'Candidate/Exam simulations',
    component: PortionScores,
    args: {
        portions: simulationResults.portions,
        timing: simulationResults.timing,
    },
} satisfies Meta<typeof PortionScores>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 53: National passed, Texas 4 short, and the pace against the real exam's. */
export const SimulationResult: Story = {};

/** Both portions passed, within pace. */
export const BothPassed: Story = {
    args: {
        portions: simulationResults.portions.map((p) => ({
            ...p,
            correct: p.pass + 4,
            passed: true,
        })),
        timing: { seconds_per_question: 88, pace: 107 },
    },
};

/** Screen 41: the test centre's calculator. 12.5% of $240,000 = 30,000. */
export const CalculatorOpen: StoryObj<typeof Calculator> = {
    render: () => <Calculator />,
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement.ownerDocument.body);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Calculator' }),
        );
        for (const key of [
            '2',
            '4',
            '0',
            '0',
            '0',
            '0',
            '×',
            '1',
            '2',
            'Decimal point',
            '5',
            'Percent',
            'Equals',
        ]) {
            await userEvent.click(canvas.getByRole('button', { name: key }));
        }
        await expect(canvas.getByText('30000')).toBeInTheDocument();
    },
};

/** Screen 40: the full simulation's intro, Exam or Practice mode. */
export const TestIntro: StoryObj<typeof TestIntroDialog> = {
    render: () => (
        <TestIntroDialog
            kind="simulation"
            label="Start"
            test={{
                kind: 'simulation',
                topic: null,
                questions: 135,
                minutes: 240,
                best: 71,
                last: 71,
                open: null,
                last_attempt: 12,
            }}
        />
    ),
};
