import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { OrderSummary } from '@/components/order-summary';
import { PlanCard } from '@/components/plan-card';
import { featureLabel, money, priceLabel } from '@/lib/billing';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('billing helpers', () => {
    it('writes prices and features in plain words', () => {
        const t = i18n.t.bind(i18n);
        expect(money(49900, 'en')).toBe('$499');
        expect(money(1999, 'en')).toBe('$19.99');
        expect(priceLabel(t, 'month', 7900, 'en')).toBe('$79 / month');
        expect(featureLabel(t, 'learn.qualifying')).toBe('Qualifying courses');
        expect(featureLabel(t, 'broker.compliance')).toBe('broker.compliance');
    });
});

describe('OrderSummary', () => {
    it('shows the sponsor discount and that tax comes at payment', () => {
        render(
            <OrderSummary
                product="Texas Pre-licence Complete"
                amount={49900}
                discount={10000}
                sponsor="Lone Star Realty"
                tax={null}
                state="Texas"
            />,
        );

        expect(
            screen.getByText('Sponsor discount · Lone Star Realty'),
        ).toBeInTheDocument();
        expect(screen.getByText('−$100')).toBeInTheDocument();
        expect(screen.getByText('Calculated at payment')).toBeInTheDocument();
        expect(
            screen.getByText('Total before tax').nextSibling,
        ).toHaveTextContent('$399');
    });

    it('adds tax to the total once it is known', () => {
        render(
            <OrderSummary
                product="Texas Exam Prep only"
                amount={12900}
                discount={0}
                sponsor={null}
                tax={1064}
                state="Texas"
            />,
        );

        expect(screen.getByText('Total today').nextSibling).toHaveTextContent(
            '$139.64',
        );
    });
});

describe('PlanCard', () => {
    it('shows what the person pays, who pays part, and opens checkout', () => {
        render(
            <PlanCard
                price={{
                    id: 4,
                    product: 'Texas Pre-licence Complete',
                    kind: 'package',
                    description: null,
                    includes: ['All 6 qualifying courses'],
                    state: 'Texas',
                    interval: 'once',
                    amount: 49900,
                }}
                charge={39900}
                sponsor="Texas Realty School"
                aiHelp={null}
            />,
        );

        expect(screen.getByText('$399')).toBeInTheDocument();
        expect(
            screen.getByText(/Texas Realty School pays part/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Choose plan' }),
        ).toHaveAttribute('href', '/checkout/4');
    });
});
