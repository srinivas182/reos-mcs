import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { mayaBuilder, mayaCard } from './join-fixtures';
import { ProfileBuilder } from './profile-builder';
import { ProviderProfileCard } from './provider-card';

const api = vi.hoisted(() => ({
    postJson: vi.fn(),
    putJson: vi.fn(),
    postForm: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    save: '/profile',
    bio: '/profile/bio',
    photo: '/profile/photo',
};

describe('The provider profile (B15.3)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows agents the licence badge, when it was checked, areas and services', () => {
        render(<ProviderProfileCard provider={mayaCard} />);

        expect(
            screen.getByRole('heading', { name: 'Chen Home Lending' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/Verified NMLS ID · License 1234567 · Checked/),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Katy · 15 miles around 77494'),
        ).toBeInTheDocument();
        expect(screen.getByText('FHA loans')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Closing cost checklist/ }),
        ).toHaveAttribute('href', 'https://chenlending.example/checklist.pdf');
    });

    it('writes the bio for me, then saves the profile with lists split', async () => {
        api.postJson.mockResolvedValue({
            data: {
                bio: 'Chen Home Lending is a loan officer.',
                bio_es: 'Chen Home Lending es oficial de préstamos.',
            },
        });
        api.putJson.mockResolvedValue({ data: mayaBuilder });
        render(
            <ProfileBuilder
                builder={{
                    ...mayaBuilder,
                    draft: { ...mayaBuilder.draft, bio: null, bio_es: null },
                }}
                routes={routes}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Write it for me' }),
        );
        expect(
            await screen.findByDisplayValue(
                'Chen Home Lending is a loan officer.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Save and check' }),
        );
        expect(api.putJson).toHaveBeenCalledWith(
            '/profile',
            expect.objectContaining({
                bio: 'Chen Home Lending is a loan officer.',
                services: ['FHA loans', 'VA loans', 'Conventional loans'],
            }),
        );
        expect(
            await screen.findByText('Published. Agents see this version.'),
        ).toBeInTheDocument();
    });

    it('shows the wording the compliance engine blocked', () => {
        render(
            <ProfileBuilder
                builder={{
                    ...mayaBuilder,
                    issues: [
                        {
                            code: 'claims',
                            severity: 'block',
                            phrase: 'guaranteed',
                            fix: '',
                            reason: null,
                            citation: '22 TAC §535.155(d)',
                            url: 'https://www.trec.texas.gov/',
                            source: 'rule',
                        },
                    ],
                }}
                routes={routes}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            '"guaranteed" · 22 TAC §535.155(d)',
        );
    });
});
