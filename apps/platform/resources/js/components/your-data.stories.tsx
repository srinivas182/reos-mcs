import type { Meta, StoryObj } from '@storybook/react-vite';
import { YourData } from '@/components/your-data';

/** Your data on Settings › Profile (A4.3b): the Texas privacy rights, in plain words. */
const meta = {
    title: 'Settings/Your data',
    component: YourData,
    args: { requests: [] },
} satisfies Meta<typeof YourData>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NoRequestsYet: Story = {};

/** James Miller asked for his data and a correction. */
export const WithRequests: Story = {
    args: {
        requests: [
            {
                id: 2,
                kind: 'correct',
                status: 'in_progress',
                created_at: '2026-11-20T10:00:00Z',
                due_at: '2027-01-04T10:00:00Z',
                response: null,
                download: null,
            },
            {
                id: 1,
                kind: 'access',
                status: 'done',
                created_at: '2026-11-19T10:00:00Z',
                due_at: '2027-01-03T10:00:00Z',
                response: null,
                download: '#',
            },
        ],
    },
};
