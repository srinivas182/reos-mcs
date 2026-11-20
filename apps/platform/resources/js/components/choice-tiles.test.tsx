import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { ChoiceTiles } from '@/components/choice-tiles';

const options = [
    { value: 'standard', label: 'Standard' },
    { value: 'large', label: 'Large' },
    { value: 'largest', label: 'Largest' },
] as const;

function Harness({
    choices = options,
}: {
    choices?: readonly {
        value: 'standard' | 'large' | 'largest';
        label: string;
        disabled?: boolean;
    }[];
}) {
    const [value, setValue] = useState<'standard' | 'large' | 'largest'>(
        'standard',
    );

    return (
        <ChoiceTiles
            label="Text size"
            value={value}
            options={choices}
            onChange={setValue}
        />
    );
}

describe('ChoiceTiles', () => {
    it('is announced as a labelled radio group with one checked option', () => {
        render(<Harness />);

        expect(
            screen.getByRole('radiogroup', { name: 'Text size' }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('radio')).toHaveLength(3);
        expect(screen.getByRole('radio', { name: /Standard/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(screen.getByRole('radio', { name: /Large$/ })).toHaveAttribute(
            'aria-checked',
            'false',
        );
    });

    it('selects a tile on click', async () => {
        render(<Harness />);

        await userEvent.click(screen.getByRole('radio', { name: /Largest/ }));

        expect(screen.getByRole('radio', { name: /Largest/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    it('moves and selects with the arrow keys, wrapping round, like a native radio group', async () => {
        render(<Harness />);

        await userEvent.tab();
        expect(screen.getByRole('radio', { name: /Standard/ })).toHaveFocus();

        await userEvent.keyboard('{ArrowRight}');
        expect(screen.getByRole('radio', { name: /Large$/ })).toHaveFocus();
        expect(screen.getByRole('radio', { name: /Large$/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );

        await userEvent.keyboard('{ArrowLeft}{ArrowLeft}');
        expect(screen.getByRole('radio', { name: /Largest/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );
    });

    it('keeps only the selected tile in the Tab order', () => {
        render(<Harness />);

        const tabbable = screen
            .getAllByRole('radio')
            .filter((radio) => radio.getAttribute('tabindex') === '0');
        expect(tabbable).toHaveLength(1);
    });

    it('skips tiles that cannot be chosen yet', async () => {
        render(
            <Harness
                choices={[
                    { value: 'standard', label: 'Standard' },
                    { value: 'large', label: 'Large', disabled: true },
                    { value: 'largest', label: 'Largest' },
                ]}
            />,
        );

        expect(screen.getByRole('radio', { name: /Large$/ })).toBeDisabled();

        await userEvent.tab();
        await userEvent.keyboard('{ArrowRight}');

        expect(screen.getByRole('radio', { name: /Largest/ })).toHaveAttribute(
            'aria-checked',
            'true',
        );
        expect(screen.getByRole('radio', { name: /Largest/ })).toHaveFocus();
    });
});
