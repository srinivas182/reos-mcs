import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { dealBoard, elmStreet, oakCourt } from '@/components/deals/fixtures';
import { i18n } from '@/lib/i18n';
import TransactionsPage from '@/pages/connections/transactions';
import DealsPage from '@/pages/deals/index';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/deals', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (data: unknown, status = 200) =>
    new Response(JSON.stringify({ data }), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

describe('Deals board (screen 14, B9.1)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        router.visit.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it("shows columns by stage with each deal's next deadline, and opens a deal", async () => {
        const user = userEvent.setup();
        render(<DealsPage board={dealBoard} selected={null} />);

        const contract = screen.getByRole('region', { name: /Under contract/ });
        expect(within(contract).getByText('1418 Elm St')).toBeInTheDocument();
        expect(
            within(contract).getByText(
                /Option period ends, Nov 26 \(in 3 days\)/,
            ),
        ).toBeInTheDocument();
        expect(
            within(contract).getByText('From Lone Wolf'),
        ).toBeInTheDocument();

        await user.click(
            within(contract).getByRole('button', { name: /9 Oak Ct/ }),
        );
        expect(router.visit.mock.calls[0]?.[0]).toBe(
            `/deals?deal=${oakCourt.uuid}`,
        );
    });

    it('keeps a deal from a tool read-only, and moves a REOS deal from its panel', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(() =>
            Promise.resolve(reply({ ...elmStreet, stage: 'closed' })),
        );
        const { unmount } = render(
            <DealsPage board={dealBoard} selected={oakCourt} />,
        );
        expect(
            screen.getByText('Read only: changes are made in Lone Wolf.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open in Lone Wolf' }),
        ).toHaveAttribute('href', oakCourt.external_url);
        expect(
            screen.queryByRole('button', { name: 'Edit' }),
        ).not.toBeInTheDocument();
        unmount();

        render(<DealsPage board={dealBoard} selected={elmStreet} />);
        await user.selectOptions(screen.getByLabelText('Stage'), 'closed');
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            `/deals/${elmStreet.uuid}/stage`,
        );
        expect(JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body))).toEqual({
            stage: 'closed',
        });
    });

    it('adds a deal with a key date', async () => {
        const user = userEvent.setup();
        fetchMock.mockImplementation(() =>
            Promise.resolve(reply(elmStreet, 201)),
        );
        render(
            <DealsPage
                board={{ active: [], under_contract: [], closed: [] }}
                selected={null}
            />,
        );
        expect(screen.getByText('No deals yet')).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Add a deal' }));
        await user.type(
            screen.getByLabelText('Property address'),
            '1418 Elm St',
        );
        await user.click(
            screen.getByRole('button', { name: 'Add a key date' }),
        );
        await user.selectOptions(
            screen.getByLabelText('Date'),
            'option_period',
        );
        await user.type(screen.getByLabelText('Due on'), '2026-11-26');
        await user.click(screen.getByRole('button', { name: 'Save the deal' }));

        expect(fetchMock.mock.calls[0]?.[0]).toBe('/deals');
        expect(
            JSON.parse(String(fetchMock.mock.calls[0]?.[1]?.body)),
        ).toMatchObject({
            property: '1418 Elm St',
            side: 'buy',
            stage: 'active',
            dates: [
                { kind: 'option_period', due_on: '2026-11-26', done: false },
            ],
        });
        expect(router.visit).toHaveBeenCalledWith(
            `/deals?deal=${elmStreet.uuid}`,
            expect.anything(),
        );
    });
});

describe('Connections › Transactions (screen 17, B9.1b)', () => {
    it('lists each tool deal with its stage, documents, next deadline and commission, read-only', () => {
        render(<TransactionsPage deals={[oakCourt]} />);

        const row = screen.getByRole('row', { name: /9 Oak Ct/ });
        expect(within(row).getByText('Under contract')).toBeInTheDocument();
        expect(within(row).getByText('1 of 2 documents')).toBeInTheDocument();
        expect(within(row).getByText('$18,300')).toBeInTheDocument();
        expect(
            within(row).getByRole('link', { name: 'Open in Lone Wolf' }),
        ).toHaveAttribute('href', oakCourt.external_url);
        expect(
            screen.getByText(
                'Read only: changes are made in the transaction tool.',
            ),
        ).toBeInTheDocument();
    });
});
