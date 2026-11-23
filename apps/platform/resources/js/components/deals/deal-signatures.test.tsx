import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { elmStreet, oakCourt } from '@/components/deals/fixtures';
import { i18n } from '@/lib/i18n';
import { DealSignatures } from './deal-signatures';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const reply = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

describe('Signatures on a deal (screen 14, B9.3)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    const assign = vi.fn();

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('location', { href: window.location.href, assign });
        assign.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it("shows each envelope's signers and the files kept in REOS", () => {
        render(<DealSignatures deal={elmStreet} onChanged={() => undefined} />);

        expect(
            screen.getByText('Amendment to contract, 1418 Elm St'),
        ).toBeInTheDocument();
        expect(screen.getByText('Signed by everyone')).toBeInTheDocument();
        expect(
            screen.getByText('· Waiting for their turn'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: "Open Seller's Disclosure (signed).pdf",
            }),
        ).toHaveAttribute(
            'href',
            '/deals/files/0b8e6d1c-4f2a-4e5b-9c3d-7a1f2e3d4c5b',
        );
    });

    it('offers to connect DocuSign when it is not connected', () => {
        render(<DealSignatures deal={oakCourt} onChanged={() => undefined} />);

        expect(
            screen.getByRole('link', { name: 'Connect DocuSign' }),
        ).toHaveAttribute('href', '/connections/docusign/connect');
        expect(
            screen.getByText('Nothing sent for signature from this deal yet.'),
        ).toBeInTheDocument();
    });

    it('adds a PDF, picks the signers and opens DocuSign to place fields and send', async () => {
        const user = userEvent.setup();
        const changed = vi.fn();
        fetchMock
            .mockImplementationOnce(async () =>
                reply(
                    {
                        data: {
                            uuid: '3d5e7f9a-1b2c-4d3e-8f4a-5b6c7d8e9f0a',
                            kind: 'upload',
                            name: 'Addendum.pdf',
                            size: 1200,
                            added_at: null,
                            scan: 'clean',
                            url: '/deals/files/3d5e',
                        },
                    },
                    201,
                ),
            )
            .mockImplementationOnce(async () =>
                reply(
                    { data: {}, sender_url: 'https://docusign.example/send' },
                    201,
                ),
            );
        render(<DealSignatures deal={elmStreet} onChanged={changed} />);

        await user.click(
            screen.getByRole('button', { name: 'Send for signature' }),
        );
        const dialog = within(screen.getByRole('dialog'));
        expect(dialog.getByLabelText('Email subject')).toHaveValue(
            'Please sign: 1418 Elm St',
        );
        const prepare = dialog.getByRole('button', {
            name: 'Continue in DocuSign',
        });
        expect(prepare).toBeDisabled();

        await user.upload(
            dialog.getByLabelText('Add a PDF', { selector: 'input' }),
            new File(['%PDF-1.4'], 'Addendum.pdf', {
                type: 'application/pdf',
            }),
        );
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/deals/9f0c1e7a-2d4b-4c7e-9a51-3b6f0e8d2c11/files',
        );
        expect(changed).toHaveBeenCalled();
        expect(
            dialog.getByRole('checkbox', { name: 'Addendum.pdf' }),
        ).toBeChecked();
        expect(
            dialog.getByRole('checkbox', { name: 'Ana Ruiz signs' }),
        ).toBeChecked();

        await user.click(prepare);
        const [url, init] = fetchMock.mock.calls[1] as [string, RequestInit];
        expect(url).toBe(
            '/deals/9f0c1e7a-2d4b-4c7e-9a51-3b6f0e8d2c11/signatures',
        );
        expect(JSON.parse(init.body as string)).toEqual({
            subject: 'Please sign: 1418 Elm St',
            files: ['3d5e7f9a-1b2c-4d3e-8f4a-5b6c7d8e9f0a'],
            signers: [
                {
                    name: 'Ana Ruiz',
                    email: 'ana@example.com',
                    routing_order: 1,
                },
            ],
        });
        expect(assign).toHaveBeenCalledWith('https://docusign.example/send');
    });
});
