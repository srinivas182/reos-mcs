import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { expect, fn, userEvent, within } from 'storybook/test';
import { MessageCheck } from './message-check';
import type { ComplianceResult } from './types';

const found: ComplianceResult = {
    ok: false,
    issues: [
        {
            code: 'fair_housing',
            severity: 'block',
            phrase: 'great for kids',
            fix: 'big backyard',
            reason: null,
            citation: '42 U.S.C. §3604(c)',
            url: 'https://www.law.cornell.edu/uscode/text/42/3604',
            source: 'rule',
        },
        {
            code: 'claims',
            severity: 'block',
            phrase: 'guaranteed',
            fix: '',
            reason: null,
            citation: '22 TAC §535.155(d)',
            url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-155',
            source: 'rule',
        },
    ],
};

function Demo({
    onCheck,
    onFix,
}: {
    onCheck: () => Promise<ComplianceResult>;
    onFix: () => Promise<ComplianceResult & { text: string }>;
}) {
    const [text, setText] = useState(
        'Hi Ana! Guaranteed to sell, and great for kids.',
    );

    return (
        <div className="space-y-3">
            <p className="text-base">{text}</p>
            <MessageCheck
                channel="text"
                text={text}
                onCheck={onCheck}
                onFix={onFix}
                onFixed={setText}
            />
        </div>
    );
}

const meta = {
    title: 'Compliance/Message check',
    component: Demo,
    args: {
        onCheck: fn(async () => found),
        onFix: fn(async () => ({
            ok: true,
            issues: [],
            text: 'Hi Ana! big backyard.',
        })),
    },
} satisfies Meta<typeof Demo>;

export default meta;

type Story = StoryObj<typeof meta>;

/** A text to a contact checked before it goes, then fixed automatically (B13.1). */
export const FixAText: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Check before sending' }),
        );
        await expect(
            await canvas.findByText(
                '"great for kids" is about who should live there, not the home (42 U.S.C. §3604(c)).',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            canvas.getByRole('button', { name: 'Fix automatically' }),
        );
        await expect(args.onFix).toHaveBeenCalled();
        await expect(
            await canvas.findByText('Hi Ana! big backyard.'),
        ).toBeInTheDocument();
    },
};

/** Nothing to fix. */
export const Clean: Story = {
    args: { onCheck: fn(async () => ({ ok: true, issues: [] })) },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Check before sending' }),
        );
        await expect(
            await canvas.findByText('No problems found.'),
        ).toBeInTheDocument();
    },
};
