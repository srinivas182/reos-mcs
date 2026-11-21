import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { GrantRoleForm } from '@/components/grant-role-form';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    roleOptions: [
        { role: 'broker' as const, organisationTypes: ['brokerage' as const] },
        {
            role: 'association_admin' as const,
            organisationTypes: ['association' as const],
        },
    ],
    organisations: [
        { id: 2, name: 'Lone Star Realty', type: 'brokerage' as const },
        { id: 3, name: 'Texas Realty School', type: 'school' as const },
    ],
};

describe('GrantRoleForm', () => {
    it('offers only organisations of the right kind', async () => {
        const onSubmit = vi.fn();
        render(<GrantRoleForm {...props} onSubmit={onSubmit} />);

        const give = screen.getByRole('button', { name: 'Give role' });
        expect(give).toBeDisabled();

        await userEvent.selectOptions(screen.getByLabelText('Role'), 'broker');
        const organisation = screen.getByLabelText('Organization');
        expect(
            screen.queryByRole('option', { name: 'Texas Realty School' }),
        ).toBeNull();

        await userEvent.selectOptions(organisation, '2');
        await userEvent.click(give);
        expect(onSubmit).toHaveBeenCalledWith('broker', 2);
    });

    it('says when no organisation of that kind exists yet', async () => {
        render(<GrantRoleForm {...props} onSubmit={vi.fn()} />);

        await userEvent.selectOptions(
            screen.getByLabelText('Role'),
            'association_admin',
        );

        expect(
            screen.getByText("There's no organization of the right kind yet."),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Organization')).toBeDisabled();
    });
});
