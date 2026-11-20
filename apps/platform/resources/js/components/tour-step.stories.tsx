import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, userEvent, within } from 'storybook/test';
import { TourStep } from '@/components/tour-step';
import { Button } from '@/components/ui/button';

const steps = [
    {
        title: 'Your day at a glance',
        body: 'Today shows your lessons, sessions and reminders in one list.',
    },
    {
        title: 'Ask anything',
        body: 'Ask a question about your course and get an answer with the page it came from.',
    },
];

/** A guided first-use tour: short steps that point at real parts of the screen. */
const meta = {
    title: 'Guidance/Tour step',
    component: TourStep,
    parameters: { layout: 'centered' },
} satisfies Meta<typeof TourStep>;

export default meta;
type Story = StoryObj<typeof meta>;

export const TwoStepTour: Story = {
    args: {
        open: true,
        title: '',
        body: '',
        step: 1,
        total: 2,
        onNext: () => undefined,
        onSkip: () => undefined,
        children: null,
    },
    render: function Render() {
        const [index, setIndex] = useState(0);
        const [open, setOpen] = useState(true);
        const step = steps[index];

        return (
            <div className="p-24">
                <TourStep
                    open={open && step !== undefined}
                    title={step?.title ?? ''}
                    body={step?.body ?? ''}
                    step={index + 1}
                    total={steps.length}
                    onNext={() =>
                        index + 1 < steps.length
                            ? setIndex(index + 1)
                            : setOpen(false)
                    }
                    onSkip={() => setOpen(false)}
                >
                    <Button variant="outline">Today</Button>
                </TourStep>
            </div>
        );
    },
    play: async ({ canvasElement }) => {
        const body = within(canvasElement.ownerDocument.body);
        await expect(await body.findByText('Step 1 of 2')).toBeInTheDocument();
        await expect(body.getByRole('button', { name: 'Next' })).toHaveFocus();
        await userEvent.click(body.getByRole('button', { name: 'Next' }));
        await expect(await body.findByText('Step 2 of 2')).toBeInTheDocument();
        await userEvent.click(body.getByRole('button', { name: 'Done' }));
    },
};
