import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import {
    lindaBrief,
    loneStarClosings,
    loneStarEmpty,
    loneStarMatched,
    loneStarTraining,
    quietBrief,
} from './report-fixtures';
import { BrokerBriefCard, ClosingsReport, TrainingReport } from './reports';

const meta = {
    title: 'Brokerage/Reports',
    component: ClosingsReport,
    args: {
        report: loneStarClosings,
        periodHref: (p: string) =>
            `/storybook/organisation/reports?period=${p}`,
    },
} satisfies Meta<typeof ClosingsReport>;

export default meta;

type Story = StoryObj<typeof meta>;

const links = {
    oversight: '/storybook/organisation/oversight',
    approvals: '/storybook/organisation/approvals',
    ce: '/storybook/organisation/ce',
    reports: '/storybook/organisation/reports',
};

/** November: James's sale from SkySlope, and Maria's closing only in REOS to square. */
export const WithDifference: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByText('Closed only in REOS (1)'),
        ).toBeInTheDocument();
    },
};

/** Every closing is in SkySlope. */
export const Matched: Story = { args: { report: loneStarMatched } };

/** No tool connected and nothing closed yet. */
export const Empty: Story = { args: { report: loneStarEmpty } };

/** Training: Legal Update I with one agent overdue. */
export const Training: Story = {
    render: () => <TrainingReport report={loneStarTraining} ceHref="#" />,
};

/** Linda's Monday morning brief on the dashboard. */
export const MorningBrief: Story = {
    render: () => <BrokerBriefCard brief={lindaBrief} links={links} />,
};

/** A quiet morning. */
export const MorningBriefClear: Story = {
    render: () => <BrokerBriefCard brief={quietBrief} links={links} />,
};
