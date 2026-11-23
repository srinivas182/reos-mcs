import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import { apply, Calculator, show } from './calculator';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the test calculator (screen 41)', () => {
    it('does real estate math the way the test centre does', async () => {
        render(<Calculator />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Calculator' }),
        );
        const press = async (...keys: string[]) => {
            for (const key of keys)
                await userEvent.click(
                    screen.getByRole('button', { name: key }),
                );
        };

        // A 6% commission on $315,000, kept in memory, then halved.
        await press(
            '3',
            '1',
            '5',
            '0',
            '0',
            '0',
            '×',
            '6',
            'Percent',
            'Equals',
        );
        expect(screen.getByText('18900')).toBeInTheDocument();
        await press(
            'M+, Add to memory',
            'Clear',
            'MR, Recall memory',
            '÷',
            '2',
            'Equals',
        );
        expect(screen.getByText('9450')).toBeInTheDocument();
        await press('÷', '0', 'Equals');
        expect(screen.getByText('Error')).toBeInTheDocument();
    });

    it('keeps sensible precision', () => {
        expect(show(0.1 + 0.2)).toBe('0.3');
        expect(apply(240000, '÷', 300000)).toBe(0.8);
        expect(apply(1, '÷', 0)).toBeNull();
    });
});
