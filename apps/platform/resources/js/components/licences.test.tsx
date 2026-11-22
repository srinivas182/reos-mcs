import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    licenceReviews,
    licenceStates,
    louisianaLicence,
    texasLicence,
} from '@/components/licence-fixtures';
import { LicenceList } from '@/components/licence-list';
import {
    LicenceReviewQueue,
    PersonLicences,
} from '@/components/licence-reviews';
import { StateSwitcher } from '@/components/state-switcher';
import { i18n } from '@/lib/i18n';

const router = vi.hoisted(() => ({
    delete: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

beforeEach(() => vi.clearAllMocks());

describe('State switcher (top bar, A5.1)', () => {
    it('is a chip when there is one state', () => {
        render(<StateSwitcher region={{ code: 'TX', name: 'Texas' }} />);

        expect(screen.getByLabelText('State: Texas')).toHaveTextContent(
            'Texas',
        );
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('switches between the states you hold', async () => {
        const onSwitch = vi.fn();
        render(
            <StateSwitcher
                region={{
                    code: 'TX',
                    name: 'Texas',
                    options: [
                        { code: 'FL', name: 'Florida' },
                        { code: 'TX', name: 'Texas' },
                    ],
                }}
                onSwitch={onSwitch}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'State: Texas. Switch state' }),
        );
        expect(
            await screen.findByRole('menuitemradio', { name: 'Texas' }),
        ).toHaveAttribute('aria-checked', 'true');
        await userEvent.click(
            screen.getByRole('menuitemradio', { name: 'Florida' }),
        );

        expect(onSwitch).toHaveBeenCalledWith('FL');
    });
});

describe('Licences (web screen 23, A5.1)', () => {
    it('shows each licence with status, sponsor, renewal and how it was checked', () => {
        render(
            <LicenceList
                licences={[texasLicence, louisianaLicence]}
                states={licenceStates}
            />,
        );

        const [texas, louisiana] = screen.getAllByRole('listitem');
        expect(texas).toHaveTextContent('Texas · Sales agent');
        expect(texas).toHaveTextContent('Active');
        expect(texas).toHaveTextContent('Lone Star Realty');
        expect(texas).toHaveTextContent('Mar 31, 2027');
        expect(texas).toHaveTextContent('checked with the regulator');
        expect(louisiana).toHaveTextContent('Coming soon');
        expect(louisiana).toHaveTextContent("REOS isn't live in Louisiana yet");
        expect(louisiana).toHaveTextContent('A reviewer is checking');
    });

    it('removes a licence with Undo and sets who you supervise', async () => {
        render(
            <LicenceList licences={[texasLicence]} states={licenceStates} />,
        );

        await userEvent.selectOptions(
            screen.getByLabelText('Who you supervise'),
            'delegated_supervisor',
        );
        expect(router.patch).toHaveBeenCalledWith(
            '/settings/licences/1',
            { broker_role: 'delegated_supervisor' },
            { preserveScroll: true },
        );
        // A sales agent can't sponsor agents.
        expect(
            within(screen.getByLabelText('Who you supervise')).queryByRole(
                'option',
                { name: 'I sponsor sales agents' },
            ),
        ).toBeNull();

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove Texas license' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/settings/licences/1',
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('offers an empty state that adds a licence', async () => {
        render(<LicenceList licences={[]} states={licenceStates} />);

        expect(screen.getByText('Your licenses live here')).toBeVisible();
        await userEvent.click(
            screen.getByRole('button', { name: 'Add a state license' }),
        );

        const dialog = await screen.findByRole('dialog', {
            name: 'Add a state license',
        });
        expect(within(dialog).getByLabelText('State')).toHaveValue('TX');
        expect(
            within(dialog).getByRole('group', { name: 'Live on REOS' }),
        ).toBeInTheDocument();
        expect(within(dialog).getByLabelText('License number')).toBeRequired();
    });
});

describe('Licence reviews (People, A5.1)', () => {
    it('lists licences waiting, oldest first', () => {
        render(<LicenceReviewQueue items={licenceReviews} />);

        expect(
            screen.getByRole('heading', { name: '2 licenses to review' }),
        ).toBeVisible();
        expect(
            screen.getByRole('link', {
                name: /Maria Lopez · Texas #0798888/,
            }),
        ).toHaveAttribute('href', '/admin/people/42');
    });

    it('approves, or rejects with a reason the person sees', async () => {
        render(<PersonLicences licences={[louisianaLicence]} />);

        await userEvent.click(screen.getByRole('button', { name: 'Approve' }));
        expect(router.post).toHaveBeenCalledWith(
            '/admin/people/licences/2/approve',
            {},
            { preserveScroll: true },
        );

        await userEvent.click(
            screen.getByRole('button', { name: "Don't approve" }),
        );
        await userEvent.type(
            screen.getByLabelText(/Why isn't it approved/),
            'No such licence.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: "Don't approve" }),
        );
        expect(router.post).toHaveBeenLastCalledWith(
            '/admin/people/licences/2/reject',
            { note: 'No such licence.' },
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
