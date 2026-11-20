import type { Meta, StoryObj } from '@storybook/react-vite';
import { ArrowRight, CalendarPlus, Trash2 } from 'lucide-react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { Button } from '@/components/ui/button';

/**
 * Buttons are 48 px by default. Use `lg` (56 px) for the one main action on a
 * screen and `sm` (40 px) only in dense admin tables. Label with a verb:
 * "Book session", not "Submit".
 */
const meta = {
    title: 'Actions/Button',
    component: Button,
    args: { children: 'Book session', onClick: fn() },
    argTypes: {
        variant: { control: 'select', options: ['default', 'secondary', 'outline', 'ghost', 'link', 'destructive'] },
        size: { control: 'select', options: ['default', 'sm', 'lg', 'icon', 'icon-sm'] },
    },
} satisfies Meta<typeof Button>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Primary: Story = {
    play: async ({ args, canvasElement }) => {
        const button = within(canvasElement).getByRole('button', { name: 'Book session' });
        await expect(button.getBoundingClientRect().height).toBeGreaterThanOrEqual(48);
        await userEvent.click(button);
        await expect(args.onClick).toHaveBeenCalledOnce();
    },
};

export const Variants: Story = {
    render: (args) => (
        <div className="flex flex-wrap gap-3">
            <Button {...args}>Primary</Button>
            <Button {...args} variant="secondary">
                Secondary
            </Button>
            <Button {...args} variant="outline">
                Outline
            </Button>
            <Button {...args} variant="ghost">
                Ghost
            </Button>
            <Button {...args} variant="link">
                Link
            </Button>
            <Button {...args} variant="destructive">
                <Trash2 /> Delete account
            </Button>
        </div>
    ),
};

export const Sizes: Story = {
    render: (args) => (
        <div className="flex flex-wrap items-center gap-3">
            <Button {...args} size="lg">
                Start lesson <ArrowRight />
            </Button>
            <Button {...args}>Book session</Button>
            <Button {...args} size="sm">
                Export
            </Button>
            <Button {...args} size="icon" aria-label="Add to calendar">
                <CalendarPlus />
            </Button>
        </div>
    ),
};

export const Disabled: Story = { args: { disabled: true } };
