import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { PhoneSignIn, ProviderButtons } from '@/components/sign-in-options';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('sign-in options', () => {
    it('shows only the providers that are switched on, as full-page links', () => {
        render(
            <ProviderButtons
                available={{ google: true, apple: false, phone: false }}
            />,
        );

        const google = screen.getByRole('link', {
            name: 'Continue with Google',
        });
        expect(google).toHaveAttribute('href', '/auth/google/redirect');
        expect(
            screen.queryByRole('link', { name: 'Continue with Apple' }),
        ).toBeNull();
    });

    it('shows nothing when no provider is switched on', () => {
        const { container } = render(
            <ProviderButtons
                available={{ google: false, apple: false, phone: false }}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('asks for the number, then the code the server texted', async () => {
        const { unmount } = render(<PhoneSignIn />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Continue with phone' }),
        );
        expect(screen.getByLabelText('Mobile number')).toHaveFocus();
        expect(
            screen.getByRole('button', { name: 'Send code' }),
        ).toBeInTheDocument();
        unmount();

        render(<PhoneSignIn sentTo="+15125550100" />);
        const code = screen.getByLabelText('Six-digit code');
        expect(code).toHaveAttribute('autocomplete', 'one-time-code');
        expect(code).toHaveAccessibleDescription(
            'We texted a code to +15125550100. It works for 10 minutes.',
        );
    });
});
