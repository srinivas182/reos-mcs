import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { CopyField } from '@/components/copy-field';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('CopyField', () => {
    it('copies the value and says so', async () => {
        const user = userEvent.setup();
        render(
            <CopyField
                label="Redirect address"
                value="https://reos.com/integrations/stripe/callback"
            />,
        );

        expect(screen.getByLabelText('Redirect address')).toHaveValue(
            'https://reos.com/integrations/stripe/callback',
        );

        await user.click(screen.getByRole('button', { name: 'Copy' }));

        expect(await navigator.clipboard.readText()).toBe(
            'https://reos.com/integrations/stripe/callback',
        );
        expect(
            screen.getByRole('button', { name: 'Copied' }),
        ).toBeInTheDocument();
    });
});
