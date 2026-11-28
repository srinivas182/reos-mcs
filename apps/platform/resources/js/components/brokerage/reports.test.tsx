import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import {
    lindaBrief,
    loneStarClosings,
    loneStarEmpty,
    loneStarMatched,
    loneStarTraining,
    quietBrief,
} from './report-fixtures';
import { BrokerBriefCard, ClosingsReport, TrainingReport } from './reports';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const links = {
    oversight: '/organisation/oversight',
    approvals: '/organisation/approvals',
    ce: '/organisation/ce',
    reports: '/organisation/reports',
};

describe('Brokerage reports (A16.3)', () => {
    it('matches the closings to SkySlope and lists what is only in REOS', () => {
        render(
            <ClosingsReport
                report={loneStarClosings}
                periodHref={(p) => `/organisation/reports?period=${p}`}
            />,
        );

        expect(
            screen.getByText('2 closings · $689,000 volume'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('SkySlope: 1 closing · $389,000'),
        ).toBeInTheDocument();
        expect(screen.getByText('Closed only in REOS (1)')).toBeInTheDocument();
        expect(
            screen.getByText(/310 Bluebonnet Ln · Maria Garcia/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'This month' }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByRole('link', { name: 'Last year' })).toHaveAttribute(
            'href',
            '/organisation/reports?period=last_year',
        );
    });

    it('says when every closing is in the tool, and when nothing is connected', () => {
        const { unmount } = render(
            <ClosingsReport report={loneStarMatched} periodHref={() => '#'} />,
        );
        expect(
            screen.getByText('Every closing is in your transaction tool.'),
        ).toBeInTheDocument();
        unmount();

        render(
            <ClosingsReport report={loneStarEmpty} periodHref={() => '#'} />,
        );
        expect(
            screen.getByText('No transaction tool connected'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('No closings in this period'),
        ).toBeInTheDocument();
    });

    it('shows training progress with CE hours', () => {
        render(<TrainingReport report={loneStarTraining} ceHref="/ce" />);

        expect(screen.getByText('4 CE hours')).toBeInTheDocument();
        expect(screen.getByText('1 of 3 done · 1 overdue')).toBeInTheDocument();
    });

    it("gives the broker's morning brief, or says all is clear", () => {
        const { unmount } = render(
            <BrokerBriefCard brief={lindaBrief} links={links} />,
        );
        expect(screen.getByText('2 agents may need help')).toBeInTheDocument();
        expect(screen.getByText('Quiet for 14 days')).toBeInTheDocument();
        expect(
            screen.getByText(
                'James Miller · Under contract · Katy · Key date within 72 hours',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('3 approvals waiting · 1 past your limit'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open CE compliance' }),
        ).toHaveAttribute('href', '/organisation/ce');
        unmount();

        render(<BrokerBriefCard brief={quietBrief} links={links} />);
        expect(
            screen.getByText('Nothing needs you this morning.'),
        ).toBeInTheDocument();
    });
});
