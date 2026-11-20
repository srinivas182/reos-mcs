import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, userEvent, within } from 'storybook/test';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

/**
 * Inputs are 48 px tall with 16 px text, a visible label (never placeholder
 * only) and an error message that says how to fix the problem.
 */
const meta = {
    title: 'Forms/Controls',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const TextInput: Story = {
    render: () => (
        <div className="max-w-md space-y-2">
            <Label htmlFor="licence">TREC licence number</Label>
            <Input id="licence" inputMode="numeric" placeholder="For example 0712345" />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const input = within(canvasElement).getByLabelText('TREC licence number');
        await userEvent.type(input, '0712345');
        await expect(input).toHaveValue('0712345');
        await expect(input.getBoundingClientRect().height).toBeGreaterThanOrEqual(48);
    },
};

export const WithError: Story = {
    render: () => (
        <div className="max-w-md space-y-2">
            <Label htmlFor="email">Email address</Label>
            <Input id="email" type="email" defaultValue="sofia@" aria-invalid aria-describedby="email-error" />
            <div id="email-error">
                <InputError message="Enter a full email address, like sofia@example.com." />
            </div>
        </div>
    ),
};

export const CheckboxWithLabel: Story = {
    name: 'Checkbox',
    render: () => (
        <div className="flex items-center gap-3">
            <Checkbox id="remind" defaultChecked />
            <Label htmlFor="remind">Remind me 30 days before my CE is due</Label>
        </div>
    ),
    play: async ({ canvasElement }) => {
        const checkbox = within(canvasElement).getByRole('checkbox', { name: /Remind me/ });
        await expect(checkbox).toBeChecked();
        await userEvent.click(within(canvasElement).getByText(/Remind me/));
        await expect(checkbox).not.toBeChecked();
    },
};

export const SelectField: Story = {
    name: 'Select',
    render: () => (
        <div className="max-w-md space-y-2">
            <Label htmlFor="market">Market</Label>
            <Select defaultValue="houston">
                <SelectTrigger id="market" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="houston">Houston</SelectItem>
                    <SelectItem value="dallas">Dallas–Fort Worth</SelectItem>
                    <SelectItem value="austin">Austin</SelectItem>
                    <SelectItem value="san-antonio">San Antonio</SelectItem>
                </SelectContent>
            </Select>
        </div>
    ),
};
