import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { AccessibilityInfo, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

type ToastState = { id: number; message: string; onUndo?: () => void | Promise<void>; undoneMessage?: string } | null;

type UndoToastOptions = {
  /** What just happened, in plain words: "Contact archived". */
  message: string;
  /** Reverses the action. */
  onUndo: () => void | Promise<void>;
  undoneMessage?: string;
  /** How long Undo stays available (ms). Long enough for slower readers. */
  duration?: number;
};

const ToastContext = createContext<{ show: (options: UndoToastOptions) => void } | null>(null);

/**
 * Hosts the app's toasts. Wrap the app once; then `useUndoToast()` anywhere.
 * Toasts are announced to screen readers and sit above the bottom safe area.
 */
export function ToastProvider({ children }: { children: ReactNode }) {
  const { t } = useTranslation();
  const [toast, setToast] = useState<ToastState>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const nextId = useRef(0);

  const dismissAfter = useCallback((ms: number) => {
    clearTimeout(timer.current);
    timer.current = setTimeout(() => setToast(null), ms);
  }, []);

  useEffect(() => () => clearTimeout(timer.current), []);

  const show = useCallback(
    ({ message, onUndo, undoneMessage, duration = 10_000 }: UndoToastOptions) => {
      nextId.current += 1;
      setToast({ id: nextId.current, message, onUndo, undoneMessage });
      AccessibilityInfo.announceForAccessibility(`${message}. ${t('common.undo')}`);
      dismissAfter(duration);
    },
    [dismissAfter, t],
  );

  const undo = async () => {
    if (!toast?.onUndo) return;
    await toast.onUndo();
    const done = toast.undoneMessage ?? t('common.undone');
    setToast({ id: toast.id + 1, message: done });
    AccessibilityInfo.announceForAccessibility(done);
    dismissAfter(3_000);
  };

  const value = useMemo(() => ({ show }), [show]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      {toast ? (
        <View pointerEvents="box-none" style={styles.layer}>
          <View accessibilityLiveRegion="polite" style={styles.toast}>
            <Text tone="onAction" style={styles.message}>
              {toast.message}
            </Text>
            {toast.onUndo ? (
              <Pressable accessibilityRole="button" accessibilityLabel={t('common.undo')} hitSlop={8} onPress={() => void undo()} style={styles.undo}>
                <Text weight="bold" style={styles.undoText}>
                  {t('common.undo')}
                </Text>
              </Pressable>
            ) : null}
          </View>
        </View>
      ) : null}
    </ToastContext.Provider>
  );
}

/** Do the action straight away, then offer Undo, instead of asking "Are you sure?". */
export function useUndoToast(): (options: UndoToastOptions) => void {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useUndoToast needs <ToastProvider> around the app');
  return context.show;
}

const styles = StyleSheet.create((theme, rt) => ({
  layer: { position: 'absolute', left: 0, right: 0, bottom: rt.insets.bottom + theme.space.lg, paddingHorizontal: theme.space.lg },
  toast: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + 8,
    paddingLeft: theme.space.lg,
    paddingRight: theme.space.sm,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.text,
  },
  message: { flex: 1, color: theme.colors.surface },
  undo: { minHeight: theme.touch, minWidth: theme.touch, justifyContent: 'center', paddingHorizontal: theme.space.md },
  undoText: { color: theme.colors.surface, textDecorationLine: 'underline' },
}));
