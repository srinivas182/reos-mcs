import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { loneStarSponsored, openAgents } from './recruit-fixtures';
import { OpenAgents, SponsoredHelp } from './recruit-more';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Recruiting (A17.2)', () => {
    it('shows sponsored help and saves a cap', async () => {
        const onCap = vi.fn();
        render(<SponsoredHelp board={loneStarSponsored} onCap={onCap} />);

        expect(
            screen.getByText('Pool balance: 995 credits'),
        ).toBeInTheDocument();
        expect(screen.getByText(/Passed the exam/)).toBeInTheDocument();
        const field = screen.getByLabelText('Cap for Ana R.');
        await userEvent.clear(field);
        await userEvent.type(field, '300');
        await userEvent.click(
            screen.getAllByRole('button', { name: 'Save cap' })[0]!,
        );
        expect(onCap).toHaveBeenCalledWith(
            loneStarSponsored.candidates[0]!.id,
            300,
        );
    });

    it('lists agents open to a move and invites one to talk', async () => {
        const onInvite = vi.fn();
        render(<OpenAgents agents={openAgents} onInvite={onInvite} />);

        expect(
            screen.getByText(/9 closings in 12 months · Strong producer/),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Invite to talk with Omar H.' }),
        );
        expect(onInvite).toHaveBeenCalledWith(openAgents[0]);
    });
});
