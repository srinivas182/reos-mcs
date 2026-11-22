import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CeHoursTile, CeRequirementList } from '@/components/ce-requirement';
import { texasCe } from '@/components/licence-fixtures';
import { RuleEditorDialog } from '@/components/rule-editor-dialog';
import { texasRules } from '@/components/state-rule-fixtures';
import { i18n } from '@/lib/i18n';
import StateRules from '@/pages/admin/states/rules';

const router = vi.hoisted(() => ({
    put: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

beforeEach(() => vi.clearAllMocks());

describe('State rules (screen 62, A5.2)', () => {
    const page = () =>
        render(
            <StateRules
                state={{ code: 'TX', name: 'Texas', regulator: 'TREC' }}
                topics={[
                    'licensing',
                    'exam',
                    'ce',
                    'advertising',
                    'brokerage',
                    'forms',
                ]}
                liveFeatures={['ce_card']}
                rules={texasRules}
            />,
        );

    it('shows CE and renewal rules, a draft, where they are used and their sources', () => {
        page();

        expect(
            screen.getByRole('heading', { name: 'Texas · rules' }),
        ).toBeVisible();
        expect(
            screen.getByRole('tab', { name: 'CE and renewal' }),
        ).toHaveAttribute('aria-selected', 'true');
        expect(screen.getByText('2 years')).toBeVisible();
        expect(screen.getByText('20 hours')).toBeVisible();
        expect(
            screen.getByText('Draft: takes effect Sep 1, 2027 once published'),
        ).toBeVisible();
        expect(
            screen.getByText(/Legal Update I: Laws, Rules and Forms \(4\)/),
        ).toBeVisible();
        expect(
            screen.getByText(
                '6 hours for: I sponsor sales agents; Designated broker of a sponsoring brokerage; Delegated supervisor',
            ),
        ).toBeVisible();
        expect(screen.getByText('$200 fee for 60 more days')).toBeVisible();

        const used = screen.getByRole('table');
        expect(
            within(used).getByRole('row', { name: /Agent CE card/ }),
        ).toHaveTextContent('Live');
        expect(
            within(used).getByRole('row', { name: /Reminders/ }),
        ).toHaveTextContent('Planned');
        expect(
            screen.getByRole('link', { name: '22 TAC §535.92(a)' }),
        ).toHaveAttribute('href', expect.stringContaining('535-92'));
    });

    it('publishes all drafts together, and switches tabs', async () => {
        page();

        await userEvent.click(
            screen.getByRole('button', { name: 'Publish changes (1)' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/admin/states/TX/rules/publish',
            {},
            { preserveScroll: true },
        );

        await userEvent.click(screen.getByRole('tab', { name: 'Exam' }));
        expect(screen.getByText('Pearson VUE')).toBeVisible();

        await userEvent.click(screen.getByRole('tab', { name: 'Forms' }));
        expect(screen.getByText('No Forms rules yet')).toBeVisible();
    });

    it('lists every version in the history', async () => {
        page();

        await userEvent.click(
            screen.getByRole('button', { name: 'Version history' }),
        );
        const dialog = await screen.findByRole('dialog', {
            name: 'Version history',
        });
        expect(
            within(dialog).getAllByText('Version 1 · effective Aug 29, 2023')
                .length,
        ).toBeGreaterThan(0);
    });

    it('edits a rule as a draft with its source and effective date', async () => {
        const onClose = vi.fn();
        render(
            <RuleEditorDialog
                state="TX"
                rule={texasRules[5]!}
                courses={[]}
                onClose={onClose}
            />,
        );

        const fee = screen.getByLabelText('Fee (USD)');
        await userEvent.clear(fee);
        await userEvent.type(fee, '250');
        await userEvent.clear(screen.getByLabelText('Takes effect on'));
        await userEvent.type(
            screen.getByLabelText('Takes effect on'),
            '2027-02-01',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save draft' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/admin/states/TX/rules/ce.deferral',
            {
                value: { fee_usd: 250, days: 60 },
                citation: 'TREC renewal requirements: CE deferral fee',
                source_url: null,
                effective_from: '2027-02-01',
                note: null,
            },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('discards a draft', async () => {
        render(
            <RuleEditorDialog
                state="TX"
                rule={texasRules[1]!}
                courses={[]}
                onClose={vi.fn()}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Discard draft' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/admin/states/TX/rules/drafts/20',
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});

describe('CE (A5.2)', () => {
    it('shows the dashboard tile: hours, renewal and courses to go', () => {
        render(<CeHoursTile ce={texasCe} />);

        const tile = screen.getByRole('region', {
            name: 'Continuing education hours',
        });
        expect(tile).toHaveTextContent('9/18');
        expect(tile).toHaveTextContent(
            'renews Mar 31, 2027 · 1 required course to go',
        );
    });

    it('lists what is still needed, with the deferral option and sources', () => {
        render(<CeRequirementList ce={texasCe} />);

        expect(
            screen.getByText('9 hours left, due Mar 31, 2027'),
        ).toBeVisible();
        expect(
            screen.getByRole('progressbar', { name: 'CE hours done' }),
        ).toHaveAttribute('aria-valuenow', '9');
        expect(
            screen.getByText(/Legal Update II/).closest('li'),
        ).toHaveTextContent('0 of 4 h');
        expect(screen.getByText(/\$200 deferral fee/)).toBeVisible();
        expect(screen.getByText(/From 22 TAC §535.92\(a\)/)).toBeVisible();
    });
});
