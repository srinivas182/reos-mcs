import type { Meta, StoryObj } from '@storybook/react-vite';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const meta = {
    title: 'Display/Feedback and status',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const Badges: Story = {
    render: () => (
        <div className="flex flex-wrap gap-2">
            <Badge>On track</Badge>
            <Badge variant="secondary">Draft</Badge>
            <Badge variant="outline">Coming soon</Badge>
            <Badge variant="destructive">Overdue</Badge>
        </div>
    ),
};

export const Alerts: Story = {
    render: () => (
        <div className="max-w-xl space-y-3">
            <Alert>
                <CircleCheck />
                <AlertTitle>CE complete for this cycle</AlertTitle>
                <AlertDescription>All 18 hours are reported to TREC. Next renewal: 31 March 2028.</AlertDescription>
            </Alert>
            <Alert variant="destructive">
                <CircleAlert />
                <AlertTitle>Legal update hours missing</AlertTitle>
                <AlertDescription>Finish Legal Update II by 30 June to keep your licence active.</AlertDescription>
            </Alert>
        </div>
    ),
};

export const CardExample: Story = {
    name: 'Card',
    render: () => (
        <Card className="max-w-sm">
            <CardHeader>
                <CardTitle>Exam readiness</CardTitle>
                <CardDescription>Based on your last three practice tests</CardDescription>
            </CardHeader>
            <CardContent>
                <p className="text-display font-semibold text-foreground">82%</p>
                <p className="text-muted-foreground">Ready for the national portion. Practise Texas contracts next.</p>
            </CardContent>
        </Card>
    ),
};
