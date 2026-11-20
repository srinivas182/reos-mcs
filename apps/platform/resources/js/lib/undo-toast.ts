import { toast } from 'sonner';
import { i18n } from '@/lib/i18n';

type UndoToastOptions = {
    /** What just happened, in plain words: "Contact archived". */
    message: string;
    /** Reverses the action. Called when the person presses Undo. */
    onUndo: () => void | Promise<void>;
    /** Confirmation shown after undoing. Defaults to "Undone" in the person's language. */
    undoneMessage?: string;
    /** How long Undo stays available, in ms. Long enough for slower readers. */
    duration?: number;
};

/**
 * Do the action straight away, then offer Undo, instead of asking "Are you
 * sure?". Use for anything reversible (archive, remove from list, mark done).
 * Keep confirmation dialogs only for actions that truly can't be undone,
 * such as deleting an account.
 */
export function undoToast({
    message,
    onUndo,
    undoneMessage = i18n.t('common.undone'),
    duration = 10_000,
}: UndoToastOptions): string | number {
    return toast(message, {
        duration,
        action: {
            label: i18n.t('common.undo'),
            onClick: () => {
                void Promise.resolve(onUndo()).then(() =>
                    toast.success(undoneMessage, { duration: 3_000 }),
                );
            },
        },
    });
}
