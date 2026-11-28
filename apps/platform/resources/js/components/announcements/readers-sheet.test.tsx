import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { officeClosed, officeClosedReminded } from './readers-fixtures';
import { ReadersSheet } from './readers-sheet';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe("Who's read it (A16.3)", () => {
    it('lists unread people first and reminds them, then waits a day', async () => {
        const load = vi
            .fn()
            .mockResolvedValueOnce(officeClosed)
            .mockResolvedValueOnce(officeClosedReminded);
        const onRemind = vi.fn().mockResolvedValue(undefined);
        render(
            <ReadersSheet
                open
                onOpenChange={() => {}}
                load={load}
                onRemind={onRemind}
            />,
        );

        expect(await screen.findByText('Maria Garcia')).toBeInTheDocument();
        expect(screen.getAllByText('Not read yet')).toHaveLength(1);
        await userEvent.click(
            screen.getByRole('button', {
                name: "Remind people who haven't read it",
            }),
        );
        expect(onRemind).toHaveBeenCalled();
        expect(
            await screen.findByText(/You can remind again after a day/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: "Remind people who haven't read it",
            }),
        ).toBeDisabled();
    });
});
