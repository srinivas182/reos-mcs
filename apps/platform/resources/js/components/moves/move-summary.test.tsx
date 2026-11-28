import { render, screen } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import { moveFixture } from './fixtures';
import { MoveSummary } from './move-summary';

describe('Brokerage move (A14.3)', () => {
    it('lists what moves, what stays with the old brokerage and what ends', () => {
        render(
            <I18nextProvider i18n={i18n}>
                <MoveSummary move={moveFixture()} />
            </I18nextProvider>,
        );

        expect(screen.getByText('Moves with you')).toBeInTheDocument();
        expect(screen.getByText('1 license')).toBeInTheDocument();
        expect(screen.getByText('9 deals and their files')).toBeInTheDocument();
        expect(
            screen.getByText('2 items waiting for approval go back to drafts'),
        ).toBeInTheDocument();
    });

    it('says a team change keeps everything', () => {
        render(
            <I18nextProvider i18n={i18n}>
                <MoveSummary
                    move={moveFixture({
                        kind: 'team',
                        stays: [],
                        ends: [],
                        to: { id: 4, name: 'Sugar Land Group' },
                    })}
                />
            </I18nextProvider>,
        );

        expect(
            screen.getByText('Changing team to Sugar Land Group'),
        ).toBeInTheDocument();
        expect(screen.queryByText('Ends')).not.toBeInTheDocument();
    });
});
