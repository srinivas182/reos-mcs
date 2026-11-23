import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { modeState } from './fixtures';
import { FlagsPicker } from './flags-picker';
import { GoalPicker } from './goal-picker';
import { ModeCard } from './mode-card';

const meta = {
    title: 'Agent/Modes',
    component: GoalPicker,
    args: { mode: modeState(), value: 'launch', onChange: fn() },
} satisfies Meta<typeof GoalPicker>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Pick your goal (screen 5): Own locked until four years licensed. */
export const PickGoal: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByRole('radio', { name: /Open a brokerage/ }),
        ).toBeDisabled();
        await userEvent.click(
            canvas.getByRole('radio', { name: /Close more deals/ }),
        );
        await expect(args.onChange).toHaveBeenCalledWith('grow');
    },
};

/** How you work (screen 6): optional flags. */
export const Flags: Story = {
    render: () => <FlagsPicker value={['bilingual']} onChange={fn()} />,
};

/** On the dashboard: Launch mode with a suggested switch to Grow (screen 126). */
export const SuggestedSwitch: Story = {
    render: () => (
        <ModeCard
            mode={modeState({ suggestion: { goal: 'grow', closings: 1 } })}
            goalHref="/settings/goal"
            setupHref="/agent/setup"
            onSwitch={fn()}
            onSnooze={fn()}
        />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Ready for Grow?')).toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'Switch to Grow' }),
        ).toBeInTheDocument();
    },
};

/** Before the goal is set: "Pick your goal". */
export const NeedsSetup: Story = {
    render: () => (
        <ModeCard
            mode={modeState({ goal: null, needs_setup: true })}
            goalHref="/settings/goal"
            setupHref="/agent/setup"
            onSwitch={fn()}
            onSnooze={fn()}
        />
    ),
};
