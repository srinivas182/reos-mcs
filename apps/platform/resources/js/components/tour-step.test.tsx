import { act, render, screen } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it } from 'vitest';
import { TourStep } from '@/components/tour-step';
import { i18n } from '@/lib/i18n';

const renderStep = () =>
    render(
        <I18nextProvider i18n={i18n}>
            <TourStep
                open
                title="Ask anything"
                body="Answers cite your course."
                step={2}
                total={3}
                onNext={() => undefined}
                onSkip={() => undefined}
            >
                <button type="button">Ask</button>
            </TourStep>
        </I18nextProvider>,
    );

describe('TourStep translations', () => {
    afterEach(async () => {
        await act(() => i18n.changeLanguage('en'));
    });

    it('speaks English by default', async () => {
        renderStep();
        expect(await screen.findByText('Step 2 of 3')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Next' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Skip the tour' }),
        ).toBeInTheDocument();
    });

    it('switches to Spanish without reloading', async () => {
        renderStep();
        await act(() => i18n.changeLanguage('es'));
        expect(await screen.findByText('Paso 2 de 3')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Siguiente' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Omitir el recorrido' }),
        ).toBeInTheDocument();
    });
});
