import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { wallet } from './fixtures';
import { RenewalChecklist } from './renewal-checklist';
import { WalletCard } from './wallet-card';

const meta = {
    title: 'CE/Wallet',
    component: WalletCard,
    args: {
        wallet: wallet(),
        learnUrl: '/learn',
        buyUrl: '/checkout/7',
        onAddCertificate: fn(),
    },
} satisfies Meta<typeof WalletCard>;

export default meta;

type Story = StoryObj<typeof meta>;

/** 7 of 18 hours, an outside course self-reported until TREC confirms it, and Get CE. */
export const InProgress: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('Self-reported')).toBeInTheDocument();
        await expect(canvas.getByText('Reported by REOS')).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Add a certificate' }),
        );
        await expect(args.onAddCertificate).toHaveBeenCalled();
    },
};

/** CE unlocked by the plan: Take CE in Learn; TREC shows the requirement met. */
export const OnRecord: Story = {
    args: {
        wallet: wallet({
            unlocked: true,
            buy: null,
            trec: {
                mce: 'met',
                education: 'none',
                checked_at: '2026-11-24T11:30:00Z',
            },
            completions: wallet().completions.map((c) => ({
                ...c,
                status: 'on_record' as const,
            })),
        }),
    },
};

/** Renew your licence (screen 106): CE done, renew with TREC, then REOS confirms. */
export const Renewal: Story = {
    render: () => (
        <RenewalChecklist
            wallet={wallet({ renewal: { ...wallet().renewal, ready: true } })}
            onRenewed={fn()}
        />
    ),
};
