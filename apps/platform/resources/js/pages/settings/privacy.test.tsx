import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import PrivacySettings from '@/pages/settings/privacy';

const router = vi.hoisted(() => ({ put: vi.fn(), on: vi.fn(() => () => {}) }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/settings/privacy', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const props = {
    switches: [
        {
            purpose: 'provider_network' as const,
            on: true,
            chosen: false,
            at: null,
        },
        { purpose: 'recruiting' as const, on: false, chosen: false, at: null },
    ],
    records: [
        {
            id: 3,
            purpose: 'sms' as const,
            subject: null,
            granted: true,
            version: '2026-11-22',
            method: 'app' as const,
            by_admin: false,
            at: '2026-11-21T10:00:00Z',
            current: true,
            withdrawable: true,
        },
        {
            id: 1,
            purpose: 'terms' as const,
            subject: null,
            granted: true,
            version: '2026-11-22',
            method: 'web' as const,
            by_admin: false,
            at: '2026-11-18T10:00:00Z',
            current: true,
            withdrawable: false,
        },
    ],
    versions: { terms: '2026-11-22', privacy_notice: '2026-11-22' },
};

describe('Settings › Privacy (A6.3)', () => {
    beforeEach(() => router.put.mockClear());

    it('shows the switches, your consents and the documents in force', () => {
        render(<PrivacySettings {...props} />);
        expect(
            screen.getByRole('switch', { name: 'Provider network' }),
        ).toHaveAttribute('aria-checked', 'true');
        expect(
            screen.getByRole('switch', { name: 'Open to recruiting' }),
        ).toHaveAttribute('aria-checked', 'false');
        expect(screen.getAllByText(/Default for your role/)).toHaveLength(2);
        expect(
            screen.getByRole('link', { name: 'Terms of use' }),
        ).toHaveAttribute('href', '/terms');
        // The terms go only with the account.
        expect(
            screen.getAllByRole('button', { name: /Withdraw consent/ }),
        ).toHaveLength(1);
    });

    it('turns a switch on', async () => {
        render(<PrivacySettings {...props} />);
        await userEvent.click(
            screen.getByRole('switch', { name: 'Open to recruiting' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/privacy/consents/recruiting',
            { granted: true, subject: null },
            expect.anything(),
        );
    });

    it('withdraws a consent', async () => {
        render(<PrivacySettings {...props} />);
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Withdraw consent: Text messages',
            }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/privacy/consents/sms',
            { granted: false, subject: null },
            expect.anything(),
        );
    });
});
