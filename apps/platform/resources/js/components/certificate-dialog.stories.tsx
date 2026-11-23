import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, fn, within } from 'storybook/test';
import { CertificateDialog, type CertificateRead } from './certificate-dialog';

const read: CertificateRead = {
    upload: '0193b1c2-0000-7000-8000-00000000c001',
    reader: 'textract',
    name: 'James Miller',
    course: 'Legal Update II: Ethics and Agency',
    hours: 4,
    completed_on: '2026-11-21',
    provider: 'Texas Realty Institute',
    confidence: {
        name: 98.5,
        course: 97.2,
        hours: 95,
        completed_on: 93.4,
        provider: 90.1,
    },
    name_matches: true,
    states: ['TX', 'LA'],
};

const meta = {
    title: 'Agent/CE certificate',
    component: CertificateDialog,
    args: { open: true, onOpenChange: fn() },
} satisfies Meta<typeof CertificateDialog>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Choose a photo or PDF of the certificate. */
export const Upload: Story = {};

/** What Textract read from James's Legal Update II certificate, to check. */
export const Review: Story = {
    args: { initial: read },
    play: async () => {
        const body = within(document.body);
        await expect(
            await body.findByDisplayValue('Legal Update II: Ethics and Agency'),
        ).toBeInTheDocument();
        await expect(body.getByText('95% sure')).toBeInTheDocument();
    },
};

/** Another name on the certificate is pointed out before saving. */
export const AnotherName: Story = {
    args: { initial: { ...read, name: 'Pat Jordan', name_matches: false } },
    play: async () => {
        await expect(
            await within(document.body).findByRole('alert'),
        ).toHaveTextContent("This name doesn't match your account.");
    },
};
