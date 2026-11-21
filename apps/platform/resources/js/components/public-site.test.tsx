import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { useState } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PillGroup } from '@/components/pill-group';
import { PublicHeader } from '@/components/public-header';
import type { QuizQuestion } from '@/components/quiz-card';
import { QuizCard } from '@/components/quiz-card';
import { RolePicker } from '@/components/role-picker';
import { ScoreSummary } from '@/components/score-summary';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const question: QuizQuestion = {
    id: 7,
    topic: 'math',
    question: 'A lot is 50 by 120 feet. How many square feet is it?',
    options: ['170', '600', '12,000', '6,000'],
    correct_option: 3,
    explanation: 'Area is length × width: 50 × 120 = 6,000 square feet.',
    source: 'Licensing exam content outline · Real estate math: area',
    sample: true,
};

const texas = { single: 'Texas', list: 'Texas', count: 1 };

afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('PublicHeader', () => {
    it('shows the audiences, the state chip, Sign in and Get started to visitors', () => {
        render(
            <PublicHeader name="REOS" liveStates={texas} signedIn={false} />,
        );

        const nav = screen.getByRole('navigation', { name: 'Main' });
        expect(
            within(nav).getByRole('link', { name: 'For brokerages' }),
        ).toHaveAttribute('href', '/for/brokerages');
        expect(screen.getByText('Texas')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
            'href',
            '/login',
        );
        expect(
            screen.getByRole('link', { name: 'Get started' }),
        ).toHaveAttribute('href', '/#roles');
    });

    it('counts the states once more than one is live', () => {
        render(
            <PublicHeader
                name="REOS"
                liveStates={{
                    single: null,
                    list: 'Florida and Texas',
                    count: 2,
                }}
                signedIn={false}
            />,
        );

        expect(screen.getByText('2 states')).toHaveAttribute(
            'title',
            'REOS is live in Florida and Texas',
        );
    });

    it('offers the dashboard instead once signed in', () => {
        render(<PublicHeader name="REOS" liveStates={texas} signedIn />);

        expect(
            screen.getByRole('link', { name: 'Go to dashboard' }),
        ).toHaveAttribute('href', '/dashboard');
        expect(
            screen.queryByRole('link', { name: 'Get started' }),
        ).not.toBeInTheDocument();
    });
});

describe('RolePicker', () => {
    it('lists six roles, each to its landing page', () => {
        render(<RolePicker state="Texas" />);

        const links = within(screen.getByRole('list')).getAllByRole('link');
        expect(links).toHaveLength(6);
        expect(links[0]).toHaveAttribute('href', '/for/candidates');
        expect(screen.getByText('Reach Texas agents')).toBeInTheDocument();
    });

    it('names no state when several are live', () => {
        render(<RolePicker state={null} />);

        expect(screen.getByText('Reach local agents')).toBeInTheDocument();
    });

    it('speaks Spanish', async () => {
        await i18n.changeLanguage('es');
        render(<RolePicker state="Texas" />);

        expect(
            screen.getByRole('heading', { name: 'Soy' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Futuro agente')).toBeInTheDocument();
    });
});

describe('QuizCard', () => {
    function Harness() {
        const [choice, setChoice] = useState<number | null>(null);

        return (
            <QuizCard
                question={question}
                choice={choice}
                onChoose={setChoice}
            />
        );
    }

    it('explains a wrong answer with its source and shows the right one', async () => {
        render(<Harness />);

        await userEvent.click(screen.getByRole('radio', { name: /12,000/ }));

        expect(screen.getByRole('status')).toHaveTextContent('Not quite.');
        expect(screen.getByRole('status')).toHaveTextContent(
            'Source: Licensing exam content outline',
        );
        expect(screen.getByRole('radio', { name: /12,000/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    it("won't change an answer once given", async () => {
        const onChoose = vi.fn();
        render(<QuizCard question={question} choice={3} onChoose={onChoose} />);

        expect(screen.getByRole('status')).toHaveTextContent(
            'Correct, nicely done.',
        );
        await userEvent.click(screen.getByRole('radio', { name: /600/ }));
        expect(onChoose).not.toHaveBeenCalled();
    });
});

describe('ScoreSummary', () => {
    it('shows the percentage and at most three topics, strongest first', () => {
        render(
            <ScoreSummary
                correct={4}
                total={10}
                topics={[
                    { topic: 'math', level: 'start_here' },
                    { topic: 'agency', level: 'strong' },
                    { topic: 'contracts', level: 'learning' },
                    { topic: 'finance', level: 'learning' },
                ]}
            />,
        );

        expect(
            screen.getByRole('img', { name: '40% you already know' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'A great place to start.' }),
        ).toBeInTheDocument();
        const rows = screen.getAllByRole('listitem');
        expect(rows).toHaveLength(3);
        expect(rows[0]).toHaveTextContent('AgencyStrong');
    });
});

describe('PillGroup', () => {
    function Minutes() {
        const [value, setValue] = useState<string>('20');

        return (
            <PillGroup
                label="How much time a day?"
                value={value}
                options={[
                    { value: '10', label: '10 min' },
                    { value: '20', label: '20 min' },
                    { value: '30', label: '30 min' },
                ]}
                onChange={setValue}
            />
        );
    }

    it('is a radio group with arrow keys', async () => {
        render(<Minutes />);

        const twenty = screen.getByRole('radio', { name: '20 min' });
        expect(twenty).toHaveAttribute('aria-checked', 'true');
        expect(screen.getByRole('radio', { name: '10 min' })).toHaveAttribute(
            'tabindex',
            '-1',
        );

        twenty.focus();
        await userEvent.keyboard('{ArrowRight}');
        expect(screen.getByRole('radio', { name: '30 min' })).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(screen.getByRole('radio', { name: '30 min' })).toHaveFocus();
    });
});
