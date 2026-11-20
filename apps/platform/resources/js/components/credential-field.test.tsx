import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { CredentialField } from '@/components/credential-field';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('CredentialField', () => {
    it('never shows a saved secret, only its last four characters', () => {
        render(
            <CredentialField
                name="values[secret_key]"
                label="Secret key"
                secret
                required
                saved
                last4="9876"
            />,
        );

        const input = screen.getByLabelText('Secret key');
        expect(input).toHaveValue('');
        expect(input).toHaveAttribute('type', 'password');
        // Blank keeps the saved value, so it is not required any more.
        expect(input).not.toBeRequired();
        expect(input).toHaveAccessibleDescription('Saved, ends in 9876');
    });

    it('lets people check what they pasted', async () => {
        render(
            <CredentialField name="values[api_key]" label="API key" secret />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Show key' }));

        expect(screen.getByLabelText(/API key/)).toHaveAttribute(
            'type',
            'text',
        );
        expect(
            screen.getByRole('button', { name: 'Hide key' }),
        ).toHaveAttribute('aria-pressed', 'true');
    });

    it('shows plain values, marks optional fields and announces errors', () => {
        render(
            <CredentialField
                name="values[host]"
                label="Host address"
                kind="url"
                defaultValue="https://us.i.posthog.com"
                error="The Host address field must be a valid URL."
            />,
        );

        const input = screen.getByLabelText(/Host address/);
        expect(input).toHaveValue('https://us.i.posthog.com');
        expect(input).toHaveAttribute('type', 'url');
        expect(input).toHaveAttribute('aria-invalid', 'true');
        expect(input).toHaveAccessibleDescription(
            'The Host address field must be a valid URL.',
        );
        expect(screen.getByText('(optional)')).toBeInTheDocument();
    });
});
