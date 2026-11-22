import type { Meta, StoryObj } from '@storybook/react-vite';
import { RuleEditorDialog } from '@/components/rule-editor-dialog';
import { texasRules } from '@/components/state-rule-fixtures';

const courses = [
    {
        code: 'legal_update_1',
        title: 'Legal Update I: Laws, Rules and Forms',
        hours: 4,
    },
    {
        code: 'legal_update_2',
        title: 'Legal Update II: Agency, Ethics and Hot Topics',
        hours: 4,
    },
    { code: 'contracts', title: 'Real estate contracts', hours: 3 },
];

/** Editing a state rule (screen 62, A5.2): a form that fits the rule, its source and the date it takes effect. */
const meta = {
    title: 'Admin/State rule editor',
    component: RuleEditorDialog,
    parameters: { layout: 'padded' },
    args: { state: 'TX', rule: texasRules[2]!, courses, onClose: () => {} },
} satisfies Meta<typeof RuleEditorDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Texas's required CE courses. */
export const RequiredCourses: Story = {};

/** Broker Responsibility: hours and who must take it. */
export const BrokerResponsibility: Story = { args: { rule: texasRules[3]! } };

/** The first renewal: SAE hours plus Legal Update I and II. */
export const FirstRenewal: Story = { args: { rule: texasRules[4]! } };

/** Hours per renewal, with a draft waiting to be published. */
export const HoursWithDraft: Story = { args: { rule: texasRules[1]! } };
