import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TenantFeatureSwitches } from '@/components/tenant-feature-switches';
import {
    loneStar,
    loneStarFeatures,
    loneStarLimits,
} from '@/components/tenant-fixtures';
import { TenantLimitsCard } from '@/components/tenant-limits-card';
import { i18n } from '@/lib/i18n';
import { planLabel } from '@/lib/tenants';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('Tenant features and limits (screen 60)', () => {
    it('switches features, but leaves beta ones to Features and releases', async () => {
        const onChange = vi.fn();
        render(
            <TenantFeatureSwitches
                features={loneStarFeatures}
                onChange={onChange}
            />,
        );

        const spanish = screen.getByRole('switch', { name: 'Spanish UI' });
        expect(spanish).toHaveAttribute('aria-checked', 'true');
        await userEvent.click(spanish);
        expect(onChange).toHaveBeenCalledWith('spanish_ui', false);

        expect(
            screen.queryByRole('switch', {
                name: 'Recruiting licensed agents',
            }),
        ).toBeNull();
        expect(
            screen.getByText('Turned on from Features and releases'),
        ).toBeInTheDocument();
        expect(screen.getByText('Plan add-on')).toBeInTheDocument();
    });

    it('reads the limits in plain words', () => {
        render(<TenantLimitsCard limits={loneStarLimits} region="us-east-2" />);

        expect(
            screen.getByText('142 · The organization pays'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('300 per seat per month · pooled'),
        ).toBeInTheDocument();
        expect(screen.getByText('5,000 credits')).toBeInTheDocument();
        expect(screen.getByText('600 requests / minute')).toBeInTheDocument();
        expect(
            screen.getByText('Logs 7 years · deleted users 30 days'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /needs the tenant owner's consent, lasts 60 minutes/,
            ),
        ).toBeInTheDocument();
    });

    it('names plans the way the journey does', async () => {
        expect(planLabel(i18n.t, loneStar)).toBe('Brokerage · 142 seats');
        expect(
            planLabel(i18n.t, {
                ...loneStar,
                status: 'trial',
                trial_days_left: 1,
            }),
        ).toBe('Trial · 1 day left');
        expect(
            planLabel(i18n.t, { ...loneStar, plan: 'inherits', seats: null }),
        ).toBe('Inherits');

        await i18n.changeLanguage('es');
        expect(planLabel(i18n.t, loneStar)).toBe('Agencia · 142 puestos');
    });
});
