import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps, ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { reachUri } from '@/components/contacts/reach-dialog';
import { panel, rows } from '@/components/contacts/fixtures';
import { i18n } from '@/lib/i18n';
import ContactsPage from '@/pages/contacts/index';

const router = vi.hoisted(() => ({
    get: vi.fn(),
    post: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/contacts', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

type Props = ComponentProps<typeof ContactsPage>;

const props: Props = {
    filters: { segment: 'all', tag: null, q: null },
    contacts: rows,
    counts: { all: 2, new: 1, active: 1, past: 0, sphere: 0 },
    tags: ['katy-buyer'],
    total: 2,
    selected: null,
};

describe('Contacts, the CRM hub (screen 13)', () => {
    it('opens with the title, purpose line, tabs and the table', () => {
        render(<ContactsPage {...props} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Contacts' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'Work contacts from every connected CRM in one place.',
            ),
        ).toBeInTheDocument();
        const tabs = screen.getByRole('navigation', { name: 'Contact groups' });
        expect(
            within(tabs).getByRole('button', { name: 'All (2)' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(
            within(tabs).getByRole('button', { name: '#katy-buyer' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Text Maria two Katy listings near good schools'),
        ).toBeInTheDocument();
        expect(screen.getByText('Ask AI in the panel')).toBeInTheDocument();
    });

    it('switches tabs and opens a contact without leaving the page', async () => {
        render(<ContactsPage {...props} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'New leads (1)' }),
        );
        expect(router.get).toHaveBeenLastCalledWith(
            '/contacts',
            { segment: 'new' },
            expect.objectContaining({ preserveState: true }),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Maria Lopez' }),
        );
        expect(router.get).toHaveBeenLastCalledWith(
            '/contacts',
            { contact: rows[0].id },
            expect.anything(),
        );
    });

    it('shows the panel with the AI next step, notes and tasks', () => {
        render(<ContactsPage {...props} selected={panel} />);
        const aside = screen.getByRole('complementary', {
            name: 'Maria Lopez',
        });

        expect(
            within(aside).getByText(
                'No touch in 12 days; she asked about Katy schools.',
            ),
        ).toBeInTheDocument();
        expect(
            within(aside).getByText('Asked about Katy ISD schools.'),
        ).toBeInTheDocument();
        expect(
            within(aside).getByText('Send Maria the Katy school ratings'),
        ).toBeInTheDocument();
        expect(
            within(aside).getByRole('button', { name: 'Call' }),
        ).toBeInTheDocument();
    });

    it('asks for an empty list to be filled from Connections', () => {
        render(
            <ContactsPage
                {...props}
                contacts={[]}
                counts={{ all: 0, new: 0, active: 0, past: 0, sphere: 0 }}
                tags={[]}
                total={0}
            />,
        );

        expect(screen.getByText('No contacts yet')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Connect a tool' }),
        ).toHaveAttribute('href', '/connections');
    });

    it("texts and calls from the agent's own phone", () => {
        expect(reachUri('call', '+18325550123')).toBe('tel:+18325550123');
        expect(reachUri('text', '+18325550123', 'Hi Maria & co')).toBe(
            'sms:+18325550123?&body=Hi%20Maria%20%26%20co',
        );
    });
});
