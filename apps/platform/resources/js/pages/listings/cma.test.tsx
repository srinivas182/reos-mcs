import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { cma, publicPage } from '@/components/listings/fixtures';
import { i18n } from '@/lib/i18n';
import CmaPage from '@/pages/listings/cma';
import AgentPublicPage from '@/pages/public/agent';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    router,
    usePage: () => ({ url: '/deals/listings', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('CMA (B10.2b)', () => {
    it('shows the range, the comparables with their attribution, and prints', () => {
        const print = vi.spyOn(window, 'print').mockImplementation(() => {});
        render(
            <CmaPage cma={cma()} radii={[0.5, 1, 2, 5]} ranges={[3, 6, 12]} />,
        );

        expect(screen.getByText('$468,000 – $488,000')).toBeInTheDocument();
        expect(
            screen.getByText(
                "From 4 sales: the middle half of their prices adjusted to this home's size.",
            ),
        ).toBeInTheDocument();
        expect(
            screen.getAllByText(
                'Listing courtesy of Bayou City Homes. Source: HAR MLS.',
            ),
        ).toHaveLength(2);
        expect(screen.getByText('Sample data')).toBeInTheDocument();
        screen.getByRole('button', { name: 'Print or save as PDF' }).click();
        expect(print).toHaveBeenCalled();
    });

    it('says to widen the search when nothing compares', () => {
        render(
            <CmaPage
                cma={cma({
                    comparables: [],
                    sold: 0,
                    active: 0,
                    low: null,
                    high: null,
                    suggested: null,
                })}
                radii={[1]}
                ranges={[6]}
            />,
        );

        expect(
            screen.getByText('No comparables in this area'),
        ).toBeInTheDocument();
    });
});

describe('the public agent page (B10.2b)', () => {
    it("shows the agent, their listings with attribution and TREC's notices", () => {
        render(<AgentPublicPage page={publicPage()} />);

        expect(
            screen.getByRole('heading', { level: 1, name: 'James Miller' }),
        ).toBeInTheDocument();
        expect(screen.getByText('TX license #0798765')).toBeInTheDocument();
        expect(
            screen.getByText(
                'Listing courtesy of Lone Star Realty. Source: HAR MLS.',
            ),
        ).toBeInTheDocument();
        expect(screen.queryByText(/Listing agent/)).not.toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'TREC Consumer Protection Notice',
            }),
        ).toHaveAttribute(
            'href',
            'https://www.trec.texas.gov/forms/consumer-protection-notice',
        );
        expect(
            screen.getByRole('link', {
                name: 'Texas Real Estate Commission Information About Brokerage Services',
            }),
        ).toBeInTheDocument();
    });

    it('says when there is nothing to show', () => {
        render(
            <AgentPublicPage
                page={publicPage({ listings: [], sample: false })}
            />,
        );

        expect(
            screen.getByText('No listings to show right now.'),
        ).toBeInTheDocument();
    });
});
