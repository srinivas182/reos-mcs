import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { AddContactDialog } from '@/components/contacts/add-contact-dialog';
import { i18n } from '@/lib/i18n';
import ContactImportPage from '@/pages/contacts/import';

const router = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/contacts/import', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

type Props = ComponentProps<typeof ContactImportPage>;

const base: Props = {
    fields: [
        'full_name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'stage',
        'tags',
        'birthday',
    ],
    max_rows: 5000,
    current: null,
    preview: null,
    imports: [],
};

const current: Props['current'] = {
    id: '0193f0a2-7c1e-7000-8000-00000000c5v1',
    file_name: 'open-house.csv',
    rows: 4,
    headers: ['Name', 'E-mail', 'Notes'],
    mapping: { Name: 'full_name', 'E-mail': 'email' },
};

describe('CSV import (B8.3b)', () => {
    it('starts with the upload, and an empty list of past imports', () => {
        render(<ContactImportPage {...base} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Import contacts from a CSV',
            }),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('CSV file')).toBeInTheDocument();
        expect(screen.getByText('No imports yet')).toBeInTheDocument();
    });

    it('maps the columns and previews the matches', async () => {
        render(
            <ContactImportPage
                {...base}
                current={current}
                preview={{
                    new: 1,
                    merged: 2,
                    skipped: 1,
                    samples: [
                        {
                            row: 1,
                            name: 'Maria Lopez',
                            email: 'maria.lopez@example.com',
                            phone: null,
                            outcome: 'merged',
                            joins: 'Maria Lopez',
                        },
                    ],
                }}
            />,
        );

        expect(screen.getByLabelText('Name')).toHaveValue('full_name');
        await userEvent.selectOptions(screen.getByLabelText('Notes'), 'tags');
        await userEvent.click(
            screen.getByRole('button', { name: 'Check the matches' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            `/contacts/import/${current.id}`,
            {
                mapping: {
                    Name: 'full_name',
                    'E-mail': 'email',
                    Notes: 'tags',
                },
            },
            expect.anything(),
        );

        expect(screen.getByText('Joins Maria Lopez')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Import 3 rows' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            `/contacts/import/${current.id}`,
        );
    });

    it('undoes a past import', async () => {
        render(
            <ContactImportPage
                {...base}
                imports={[
                    {
                        id: 'imp-1',
                        file_name: 'open-house.csv',
                        name: 'CSV import, Nov 23, 2026',
                        status: 'imported',
                        counts: { new: 1, merged: 2, skipped: 1 },
                        imported_at: new Date().toISOString(),
                        undone_at: null,
                    },
                ]}
            />,
        );

        const item = screen
            .getByText('CSV import, Nov 23, 2026')
            .closest('li')!;
        expect(
            within(item).getByText(/1 new, 2 joined, 1 skipped/),
        ).toBeInTheDocument();
        await userEvent.click(
            within(item).getByRole('button', {
                name: 'Undo CSV import, Nov 23, 2026',
            }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/contacts/import/imp-1',
            expect.anything(),
        );
    });

    it('adds a contact and says where new contacts go', async () => {
        render(
            <AddContactDialog
                crms={[
                    {
                        connection: 1,
                        integration: 'follow-up-boss',
                        name: 'Follow Up Boss',
                        account: null,
                        takes_new_people: true,
                        is_default: true,
                    },
                    {
                        connection: 3,
                        integration: 'boldtrail',
                        name: 'BoldTrail (kvCORE)',
                        account: null,
                        takes_new_people: false,
                        is_default: false,
                    },
                ]}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Add contact' }),
        );
        const goesTo = screen.getByLabelText('New contacts go to');
        expect(goesTo).toHaveValue('1');
        expect(
            within(goesTo).queryByText('BoldTrail (kvCORE)'),
        ).not.toBeInTheDocument();
        await userEvent.selectOptions(goesTo, '');
        expect(router.put).toHaveBeenCalledWith(
            '/contacts/default-crm',
            { connection: null },
            expect.anything(),
        );
    });
});
