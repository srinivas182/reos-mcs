import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { FlagRow } from '@/components/feature-flag-dialogs';
import { i18n } from '@/lib/i18n';
import FeaturesAndReleases from '@/pages/admin/features/index';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/admin/features', props: { errors: {} } }),
}));

// The checkboxes (Radix) measure themselves; jsdom has no ResizeObserver.
vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const flag = (overrides: Partial<FlagRow>): FlagRow => ({
    key: 'messages',
    name: 'Messages',
    description: 'Conversations and announcements.',
    owner: 'Team B',
    tenant_feature: false,
    stage: 'tenants',
    tenants: [{ id: 4, name: 'Summit Realty' }],
    states: [],
    percent: 0,
    roles: null,
    error_threshold: 5,
    stage_changed_at: '2026-11-22T09:00:00Z',
    killed_at: null,
    rolled_back_at: null,
    rolled_back_from: null,
    uses: 400,
    errors: 6,
    ...overrides,
});

const props = {
    flags: [
        flag({}),
        flag({
            key: 'video_rooms',
            name: 'Video rooms',
            stage: 'percent',
            states: ['TX'],
            percent: 25,
            rolled_back_at: '2026-11-22T09:30:00Z',
            rolled_back_from: 'everyone',
        }),
        flag({
            key: 'spanish_ui',
            name: 'Spanish',
            tenant_feature: true,
            stage: 'everyone',
            tenants: [],
            killed_at: '2026-11-22T10:00:00Z',
            uses: 0,
            errors: 0,
        }),
    ],
    releases: [
        {
            id: 1,
            version: '6.1.0',
            released_on: '2026-11-15',
            notes: { agent: 'Your calendar now shows on Today.' },
            flags: ['messages'],
            author: 'Ops Srini',
            dismissed: 12,
        },
    ],
    options: {
        stages: [
            'off',
            'internal',
            'tenants',
            'states',
            'percent',
            'everyone',
        ] as FlagRow['stage'][],
        roles: ['agent', 'broker'] as ('agent' | 'broker')[],
        states: [{ code: 'TX', name: 'Texas' }],
        tenants: [
            { id: 4, name: 'Summit Realty', type: 'brokerage' },
            { id: 5, name: 'Harbor Homes', type: 'brokerage' },
        ],
        window_minutes: 10,
    },
};

describe('Features and releases (screen 70)', () => {
    beforeEach(() => {
        router.post.mockClear();
        router.put.mockClear();
        router.delete.mockClear();
    });

    it('shows each flag with its rollout step, who sees it and errors since rollout', () => {
        render(<FeaturesAndReleases {...props} />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Features and releases',
            }),
        ).toBeInTheDocument();
        const messages = screen.getByRole('row', { name: /Messages/ });
        expect(within(messages).getByText('Named tenants')).toBeInTheDocument();
        expect(within(messages).getByText('Summit Realty')).toBeInTheDocument();
        expect(within(messages).getByText('Every role')).toBeInTheDocument();
        expect(
            within(messages).getByText('6 errors in 400 uses'),
        ).toBeInTheDocument();
        expect(
            within(messages).getByText('1.5 per 100 uses'),
        ).toBeInTheDocument();

        const video = screen.getByRole('row', { name: /Video rooms/ });
        expect(
            within(video).getByText('Summit Realty · TX · 25% of tenants'),
        ).toBeInTheDocument();
        expect(
            within(video).getByText(/Stepped back on its own/),
        ).toBeInTheDocument();

        const spanish = screen.getByRole('row', { name: /Spanish/ });
        expect(within(spanish).getByText('Off everywhere')).toBeInTheDocument();
        expect(
            within(spanish).getByRole('button', {
                name: 'Turn Spanish back on',
            }),
        ).toBeInTheDocument();
    });

    it('turns a flag off everywhere at once, offering Undo instead of asking first', async () => {
        render(<FeaturesAndReleases {...props} />);
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Turn Messages off everywhere',
            }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/features/messages/kill',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('changes a rollout to named tenants and saves it', async () => {
        render(<FeaturesAndReleases {...props} />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Change rollout for Messages' }),
        );
        const dialog = screen.getByRole('dialog', {
            name: 'Change rollout: Messages',
        });
        await userEvent.click(within(dialog).getByLabelText('Harbor Homes'));
        await userEvent.click(within(dialog).getByLabelText('Broker'));
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Save rollout' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/admin/features/messages/rollout',
            expect.objectContaining({
                stage: 'tenants',
                tenant_ids: [4, 5],
                roles: ['broker'],
                error_threshold: 5,
            }),
            expect.anything(),
        );
    });

    it('lists releases with their notes per role and publishes a new one', async () => {
        render(<FeaturesAndReleases {...props} />);
        expect(
            screen.getByText('Your calendar now shows on Today.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Dismissed by 12 people')).toBeInTheDocument();
        expect(screen.getByText('Rolls out: Messages')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'New release' }),
        );
        const dialog = screen.getByRole('dialog', { name: 'New release' });
        await userEvent.type(within(dialog).getByLabelText('Version'), '6.2.0');
        await userEvent.type(
            within(dialog).getByLabelText('Note for Licensed agent'),
            'Messages are here.',
        );
        await userEvent.click(within(dialog).getByLabelText('Messages'));
        await userEvent.click(
            within(dialog).getByRole('button', { name: 'Publish release' }),
        );

        expect(router.post).toHaveBeenCalledWith(
            '/admin/releases',
            expect.objectContaining({
                version: '6.2.0',
                notes: { agent: 'Messages are here.' },
                flags: ['messages'],
            }),
            expect.anything(),
        );
    });
});
