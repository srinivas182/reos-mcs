import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { CategoryStep } from './category-step';
import { categories, notChosen, stagerChosen } from './join-fixtures';

const meta = {
    title: 'Providers/What do you do',
    component: CategoryStep,
    args: {
        categories,
        joining: notChosen,
        routes: {
            request: '/storybook/provider/categories/requests',
            category: '/storybook/provider/profile/category',
            fields: '/storybook/provider/profile/fields',
        },
    },
} satisfies Meta<typeof CategoryStep>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Dana Brooks picks what she does from the admin's categories. */
export const ChooseACategory: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', {
                name: 'Choose Home stager',
            }),
        ).toBeInTheDocument();
    },
};

/** Home stager chosen: its text, list and yes/no fields. */
export const AnswerTheFields: Story = {
    args: { joining: stagerChosen },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('2 questions left'),
        ).toBeInTheDocument();
    },
};
