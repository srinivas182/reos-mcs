import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { PageTabs } from '@/components/page-tabs';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    usePage: () => ({ url: '/settings/security', props: {} }),
}));

describe('PageTabs', () => {
    it('marks the tab for the current page', () => {
        render(
            <PageTabs
                label="Settings"
                tabs={[
                    { title: 'Profile', href: '/settings/profile' },
                    { title: 'Security', href: '/settings/security' },
                ]}
            />,
        );

        expect(
            screen.getByRole('navigation', { name: 'Settings' }),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Security' })).toHaveAttribute(
            'aria-current',
            'page',
        );
        expect(
            screen.getByRole('link', { name: 'Profile' }),
        ).not.toHaveAttribute('aria-current');
    });
});
