import type { Meta, StoryObj } from '@storybook/react-vite';

import { PreviewNotice } from './preview-banner';

const meta = {
    title: 'Shell/Preview banner',
    component: PreviewNotice,
    args: { code: '123456' },
} satisfies Meta<typeof PreviewNotice>;

export default meta;

export const UsabilityCheck: StoryObj<typeof meta> = {};
