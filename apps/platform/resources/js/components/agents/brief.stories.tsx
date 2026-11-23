import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { BriefPlayer } from './brief-player';
import { BriefSettingsForm } from './brief-settings-form';
import { brief, briefSettings } from './fixtures';

const meta = {
    title: 'Agent/Morning brief',
    component: BriefPlayer,
    args: { script: brief().script, lang: 'en' },
} satisfies Meta<typeof BriefPlayer>;

export default meta;

type Story = StoryObj<typeof meta>;

/** The brief read aloud on the web (screen 184): Play, Skip and the transcript. */
export const Player: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Call Ana Garcia first.'),
        ).toBeInTheDocument();
    },
};

/** When it goes out and what it covers; the lock screen never shows what's in it. */
export const Settings: Story = {
    render: () => (
        <BriefSettingsForm
            settings={briefSettings()}
            sections={['todos', 'appointments', 'deadlines', 'leads', 'ce']}
            onSave={fn()}
        />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('checkbox', { name: 'CE progress' }),
        );
        await expect(
            canvas.getByRole('checkbox', { name: 'CE progress' }),
        ).not.toBeChecked();
    },
};
