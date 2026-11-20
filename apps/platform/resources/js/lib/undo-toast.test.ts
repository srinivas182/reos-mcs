import { beforeEach, describe, expect, it, vi } from 'vitest';

const toast = Object.assign(
    vi.fn(() => 'toast-id'),
    { success: vi.fn() },
);
vi.mock('sonner', () => ({ toast }));

const { undoToast } = await import('@/lib/undo-toast');

type ToastOptions = {
    duration: number;
    action: { label: string; onClick: () => void };
};

describe('undoToast', () => {
    beforeEach(() => {
        toast.mockClear();
        toast.success.mockClear();
    });

    it('offers Undo for 10 seconds by default, long enough for slower readers', () => {
        undoToast({ message: 'Contact archived', onUndo: vi.fn() });

        const [message, options] = toast.mock.calls[0] as unknown as [
            string,
            ToastOptions,
        ];
        expect(message).toBe('Contact archived');
        expect(options.duration).toBe(10_000);
        expect(options.action.label).toBe('Undo');
    });

    it('runs the undo and confirms it', async () => {
        const onUndo = vi.fn();
        undoToast({
            message: 'Task done',
            onUndo,
            undoneMessage: 'Task restored',
        });

        const [, options] = toast.mock.calls[0] as unknown as [
            string,
            ToastOptions,
        ];
        options.action.onClick();
        await vi.waitFor(() =>
            expect(toast.success).toHaveBeenCalledWith('Task restored', {
                duration: 3_000,
            }),
        );
        expect(onUndo).toHaveBeenCalledOnce();
    });
});
