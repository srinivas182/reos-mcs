import { render, screen } from '@testing-library/react';
import { Users } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { EmptyState } from '@/components/empty-state';

describe('EmptyState', () => {
    it('names the region, teaches the next step and shows one main action', () => {
        render(
            <EmptyState
                icon={Users}
                title="Your contacts live here"
                description="Add your first contact or connect your CRM."
                action={<button type="button">Add contact</button>}
            />,
        );

        expect(
            screen.getByRole('region', { name: 'Your contacts live here' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', { name: 'Your contacts live here' }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Add your first contact or connect your CRM.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Add contact' }),
        ).toBeInTheDocument();
    });
});
