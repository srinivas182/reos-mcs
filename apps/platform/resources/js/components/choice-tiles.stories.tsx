import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, userEvent, within } from 'storybook/test';
import AppearanceSettings from '@/components/appearance-settings';
import { ChoiceTiles } from '@/components/choice-tiles';

const meta = {
    title: 'Forms/Choice tiles',
    component: ChoiceTiles,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof ChoiceTiles>;

export default meta;
type Story = StoryObj<typeof meta>;

const roles = [
    {
        value: 'candidate',
        label: 'Getting licensed',
        description: 'Pre-licensing and exam prep',
    },
    {
        value: 'agent',
        label: 'Licensed agent',
        description: 'CE, clients and growth',
    },
    {
        value: 'broker',
        label: 'Broker or team leader',
        description: 'Agents, approvals and compliance',
    },
] as const;

export const Roles: Story = {
    args: {
        label: 'What brings you to REOS?',
        value: 'candidate',
        options: roles,
        onChange: () => undefined,
    },
    render: function Render(args) {
        const [value, setValue] = useState<string>(args.value);

        return <ChoiceTiles {...args} value={value} onChange={setValue} />;
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('radio', { name: /Licensed agent/ }),
        );
        await expect(
            canvas.getByRole('radio', { name: /Licensed agent/ }),
        ).toHaveAttribute('aria-checked', 'true');
        await userEvent.keyboard('{ArrowRight}');
        await expect(
            canvas.getByRole('radio', { name: /Broker/ }),
        ).toHaveFocus();
    },
};

/** The Settings › Appearance panel (saving needs a signed-in session; here it changes the preview only). */
export const AppearancePanel: Story = {
    args: { label: '', value: '', options: [], onChange: () => undefined },
    render: () => <AppearanceSettings />,
};
