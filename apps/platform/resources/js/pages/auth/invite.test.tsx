import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Invite from '@/pages/auth/invite';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/invite/x', props: { errors: {} } }),
}));

vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const token = 'a'.repeat(48);
const invite = {
    tenant: 'Lone Star Realty',
    team: 'Okoye Team',
    role: 'agent' as const,
    inviter: 'Linda Park',
    contact: 'rosa@example.com',
    channel: 'email' as const,
    name: 'Rosa Lima',
    expires_at: '2026-12-05T12:00:00Z',
};

describe('Accepting an invite', () => {
    it('lets a new person create their account and join', () => {
        render(
            <Invite
                state="open"
                token={token}
                invite={invite}
                account="new"
                signedInAs={null}
            />,
        );

        expect(
            screen.getByText(
                'Linda Park invited you to join Lone Star Realty as Licensed agent in Okoye Team.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('This invite works until December 5, 2026.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Your name')).toHaveValue('Rosa Lima');
        expect(screen.getByLabelText('Password')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Create account and join' }),
        ).toBeInTheDocument();
    });

    it('asks only for a name on a text invite', () => {
        render(
            <Invite
                state="open"
                token={token}
                invite={{ ...invite, channel: 'text', contact: '•••• 0123' }}
                account="new"
                signedInAs={null}
            />,
        );

        expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
        expect(
            screen.getByText(
                "You'll sign in with a code we text to •••• 0123.",
            ),
        ).toBeInTheDocument();
    });

    it('joins in one tap when signed in as the person invited', () => {
        render(
            <Invite
                state="open"
                token={token}
                invite={invite}
                account="match"
                signedInAs="rosa@example.com"
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Join Lone Star Realty' }),
        ).toBeInTheDocument();
    });

    it('says when the account signed in is someone else, or an account already exists', () => {
        const { rerender } = render(
            <Invite
                state="open"
                token={token}
                invite={invite}
                account="other"
                signedInAs="pat@example.com"
            />,
        );
        expect(
            screen.getByText(/you're signed in as pat@example.com/),
        ).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <Invite
                    state="open"
                    token={token}
                    invite={invite}
                    account="existing"
                    signedInAs={null}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByRole('link', { name: 'Sign in to join' }),
        ).toHaveAttribute('href', `/invite/${token}/sign-in`);
    });

    it('explains expired and broken links', () => {
        const { rerender } = render(
            <Invite
                state="expired"
                token={token}
                invite={invite}
                account={null}
                signedInAs={null}
            />,
        );
        expect(
            screen.getByText(
                'This invite has expired. Ask the person who invited you for a new one.',
            ),
        ).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <Invite
                    state="invalid"
                    token={null}
                    invite={null}
                    account={null}
                    signedInAs={null}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByRole('link', { name: 'Enter a code' }),
        ).toHaveAttribute('href', '/join');
    });
});
