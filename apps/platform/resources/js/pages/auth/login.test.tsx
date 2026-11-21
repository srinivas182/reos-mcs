import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Login from '@/pages/auth/login';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/login', props: { errors: {} } }),
}));

vi.mock('@/components/passkey-verify', () => ({ default: () => null }));

// The phone-code field (input-otp) measures itself; jsdom has no ResizeObserver.
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

const options = { google: true, apple: true, phone: false };

describe('Sign in (journey screen 2)', () => {
    it('offers the association’s single sign-on on a tenant address', () => {
        render(
            <Login
                canResetPassword
                signInOptions={options}
                sso={{
                    name: 'Texas REALTORS®',
                    url: 'https://learn.lonestarrealty.com/sso/texas-realtors/start',
                }}
            />,
        );

        expect(
            screen.getByRole('link', {
                name: 'Sign in with Texas REALTORS® (single sign-on)',
            }),
        ).toHaveAttribute(
            'href',
            'https://learn.lonestarrealty.com/sso/texas-realtors/start',
        );
    });

    it('has no single sign-on button elsewhere', () => {
        render(<Login canResetPassword signInOptions={options} />);

        expect(
            screen.queryByRole('link', { name: /single sign-on/ }),
        ).not.toBeInTheDocument();
    });
});
