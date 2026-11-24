import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, userEvent, within } from 'storybook/test';
import { ReadyTable } from './ready-table';
import { reporting } from './reporting-fixtures';
import { ReportsList } from './reports-list';
import { SubmitReport } from './submit-report';

const meta = {
    title: 'CE/Reporting to TREC',
    component: SubmitReport,
    args: { reos: 'simulated', today: '2026-11-24', onSubmit: fn() },
} satisfies Meta<typeof SubmitReport>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Submit (screen 51): REOS submits only with the school's authority. */
export const Submit: Story = {
    play: async ({ canvasElement, args }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('radio', { name: /Let REOS submit/ }),
        );
        await expect(
            canvas.getByRole('button', { name: 'Submit' }),
        ).toBeDisabled();
        await userEvent.click(
            canvas.getByRole('checkbox', { name: /I authorize REOS/ }),
        );
        await userEvent.click(canvas.getByRole('button', { name: 'Submit' }));
        await expect(args.onSubmit).toHaveBeenCalledWith(
            expect.objectContaining({ method: 'reos', authorised: true }),
        );
    },
};

/** Ready, with what stops a completion going. */
export const Ready: Story = {
    render: () => <ReadyTable rows={reporting().ready} kind="ready" />,
};

/** Reports: pending upload with the reference to enter, and one reported with a rejection. */
export const Reports: Story = {
    render: () => (
        <ReportsList
            reports={reporting().reports}
            fileUrl={() => '#'}
            onReference={fn()}
            onReject={fn()}
        />
    ),
};
