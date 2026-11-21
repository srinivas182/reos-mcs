import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { TwoStepNotice } from '@/components/two-step-notice';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('TwoStepNotice', () => {
    it('says why an admin role needs two-step and leads to setting it up', () => {
        render(
            <TwoStepNotice
                role="Broker"
                action={<a href="/settings/security">Set up two-step</a>}
            />,
        );

        expect(
            screen.getByRole('region', {
                name: 'Set up two-step to use Broker',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText(/about a minute/)).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Set up two-step' }),
        ).toBeInTheDocument();
    });

    it('explains the choices on Security settings, in Spanish too', async () => {
        await i18n.changeLanguage('es');
        render(<TwoStepNotice role="Bróker" mode="security" />);

        expect(
            screen.getByRole('heading', {
                name: 'Su rol de Bróker necesita los dos pasos',
            }),
        ).toBeInTheDocument();
        await i18n.changeLanguage('en');
    });
});
