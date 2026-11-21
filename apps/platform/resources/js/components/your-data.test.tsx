import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { YourData } from '@/components/your-data';
import { i18n } from '@/lib/i18n';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/settings/profile', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('YourData', () => {
    it('offers the download and a correction, with the 45-day promise', async () => {
        render(<YourData requests={[]} />);

        expect(
            screen.getByRole('heading', { name: 'Your data' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Download my data' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/REOS answers within 45 days/),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Ask for a correction' }),
        );
        expect(screen.getByLabelText('What should we correct?')).toBeRequired();
    });

    it('lists requests with their answer-by date, status and the file when ready', () => {
        render(
            <YourData
                requests={[
                    {
                        id: 2,
                        kind: 'correct',
                        status: 'in_progress',
                        created_at: '2026-11-20T10:00:00Z',
                        due_at: '2027-01-04T10:00:00Z',
                        response: null,
                        download: null,
                    },
                    {
                        id: 1,
                        kind: 'access',
                        status: 'done',
                        created_at: '2026-11-19T10:00:00Z',
                        due_at: '2027-01-03T10:00:00Z',
                        response: null,
                        download: '/settings/privacy/1/download?signature=x',
                    },
                ]}
            />,
        );

        expect(
            screen.getByText("We'll answer by Jan 4, 2027"),
        ).toBeInTheDocument();
        expect(screen.getByText('In progress')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Download file' }),
        ).toHaveAttribute('href', '/settings/privacy/1/download?signature=x');
    });
});
