import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { mayaBuilder } from './join-fixtures';
import { ProfileBuilder } from './profile-builder';

const meta = {
    title: 'Providers/Build your profile',
    component: ProfileBuilder,
    args: {
        builder: mayaBuilder,
        routes: {
            save: '/storybook/provider/profile',
            bio: '/storybook/provider/profile/bio',
            photo: '/storybook/provider/profile/photo',
        },
    },
} satisfies Meta<typeof ProfileBuilder>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Maya Chen's published profile with the preview agents see. */
export const Published: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText(/Verified NMLS ID/),
        ).toBeInTheDocument();
    },
};

/** Wording the compliance engine held back. */
export const HeldForWording: Story = {
    args: {
        builder: {
            ...mayaBuilder,
            draft: {
                ...mayaBuilder.draft,
                bio: 'Approval guaranteed for every buyer in Katy.',
            },
            issues: [
                {
                    code: 'claims',
                    severity: 'block',
                    phrase: 'guaranteed',
                    fix: '',
                    reason: null,
                    citation: '22 TAC §535.155(d)',
                    url: 'https://www.trec.texas.gov/',
                    source: 'rule',
                },
            ],
        },
    },
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('alert'),
        ).toBeInTheDocument();
    },
};
