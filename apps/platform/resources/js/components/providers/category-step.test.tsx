import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { CategoryStep } from './category-step';
import { categories, notChosen, stagerChosen } from './join-fixtures';

const api = vi.hoisted(() => ({ postJson: vi.fn(), putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    request: '/requests',
    category: '/category',
    fields: '/fields',
};

describe('What do you do? (B15.1)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows each category with its licence step and lowest price, and chooses one', async () => {
        api.putJson.mockResolvedValue({ data: stagerChosen });
        render(
            <CategoryStep
                categories={categories}
                joining={notChosen}
                routes={routes}
            />,
        );

        expect(
            screen.getByText('Business details and a photo ID'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('NMLS ID, checked by a REOS reviewer'),
        ).toBeInTheDocument();
        expect(screen.getByText('From $29 a month')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Choose Home stager' }),
        );
        expect(api.putJson).toHaveBeenCalledWith('/category', {
            category: 'home-stager',
        });
        expect(
            await screen.findByRole('heading', {
                name: 'About your Home stager work',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 questions left')).toBeInTheDocument();
    });

    it('answers text, list and yes/no fields by type', async () => {
        api.putJson.mockResolvedValue({
            data: { ...stagerChosen, missing: [] },
        });
        render(
            <CategoryStep
                categories={categories}
                joining={stagerChosen}
                routes={routes}
            />,
        );

        await userEvent.click(screen.getByLabelText('Modern'));
        await userEvent.click(screen.getByRole('button', { name: 'Yes' }));
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(api.putJson).toHaveBeenCalledWith('/fields', {
            fields: {
                rooms_per_month: '12',
                styles: ['Modern'],
                owns_furniture_inventory: true,
            },
        });
        expect(
            await screen.findByText('All questions answered.'),
        ).toBeInTheDocument();
    });

    it('asks for a category that is not listed', async () => {
        api.postJson.mockResolvedValue({
            data: {
                key: 'pool-inspector',
                name: 'Pool inspector',
                status: 'requested',
                requests: 1,
            },
        });
        render(
            <CategoryStep
                categories={[]}
                joining={notChosen}
                routes={routes}
            />,
        );

        expect(screen.getByText('No categories yet')).toBeInTheDocument();
        await userEvent.type(
            screen.getByLabelText('What you do'),
            'Pool inspector',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Ask for this category' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/requests', {
            name: 'Pool inspector',
            note: null,
        });
    });
});
