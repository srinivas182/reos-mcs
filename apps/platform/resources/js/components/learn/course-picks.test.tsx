import { render, screen } from '@testing-library/react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import { CoursePicks } from './course-picks';
import { picksFixture } from './course-picks.fixtures';

describe('Course picks (A14.2)', () => {
    it('says why each course is picked and whether it can be listened to', () => {
        render(
            <I18nextProvider i18n={i18n}>
                <CoursePicks
                    picks={picksFixture()}
                    lessonHref={(id) => `/learn/lessons/${id}`}
                />
            </I18nextProvider>,
        );

        expect(
            screen.getByText(
                'Your license renews soon and you still need CE hours.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByText('Suits your stage.')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Continue (25%)' }),
        ).toHaveAttribute('href', '/learn/lessons/101');
        expect(screen.getAllByRole('link', { name: 'Start' })).toHaveLength(2);
    });

    it('shows nothing without picks', () => {
        const { container } = render(
            <I18nextProvider i18n={i18n}>
                <CoursePicks picks={[]} lessonHref={(id) => String(id)} />
            </I18nextProvider>,
        );
        expect(container).toBeEmptyDOMElement();
    });
});
