import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { inviteOptions } from '@/components/invite-fixtures';
import { InviteSheet } from '@/components/invite-sheet';
import { i18n } from '@/lib/i18n';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/agents', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('InviteSheet', () => {
    it('invites one person with role, team, language and the seats left', () => {
        render(
            <InviteSheet
                open
                onOpenChange={() => {}}
                options={inviteOptions()}
                tenant="Lone Star Realty"
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'Invite people' }),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Email or mobile number'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: /Licensed agent/ }),
        ).toBeChecked();
        expect(screen.getByText('Takes a seat')).toBeInTheDocument();
        expect(screen.getByLabelText('Team')).toHaveDisplayValue('No team');
        expect(screen.getByRole('radio', { name: 'English' })).toBeChecked();
        expect(
            screen.getByText('21 of 142 seats left · Lone Star Realty pays'),
        ).toBeInTheDocument();
    });

    it('takes many at once from lines or a CSV', async () => {
        render(
            <InviteSheet
                open
                onOpenChange={() => {}}
                options={inviteOptions()}
                tenant="Lone Star Realty"
            />,
        );

        await userEvent.click(
            screen.getByRole('radio', { name: 'Several at once' }),
        );

        expect(
            screen.getByLabelText('Emails or mobile numbers, one per line'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('Or upload a CSV (name, email, phone)'),
        ).toHaveAttribute('type', 'file');
    });

    it('hides teams for mentors, starts on the role asked for, and says when texts are off', async () => {
        render(
            <InviteSheet
                open
                onOpenChange={() => {}}
                options={inviteOptions({ can_text: false })}
                tenant="Lone Star Realty"
                defaultRole="broker"
            />,
        );

        expect(screen.getByRole('radio', { name: 'Broker' })).toBeChecked();
        expect(
            screen.getByText(
                /Invites by text start when REOS turns on text messages/,
            ),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('radio', { name: 'Mentor' }));
        expect(screen.queryByLabelText('Team')).not.toBeInTheDocument();
    });
});
