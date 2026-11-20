import { act, fireEvent, render, screen } from '@testing-library/react-native';
import * as Haptics from 'expo-haptics';
import { useNetworkState } from 'expo-network';
import { useState, type ReactNode } from 'react';
import { I18nextProvider } from 'react-i18next';

import { i18n } from '@/lib/i18n';
import { Button, ChoiceTiles, EmptyState, OfflineBanner, Sheet, Text, TextField, ToastProvider, useUndoToast } from '@/ui';

const wrap = async (ui: ReactNode) => render(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Button', () => {
  it('is an accessible button that taps with a haptic', async () => {
    const onPress = jest.fn();
    await wrap(<Button label="Book session" onPress={onPress} />);

    await fireEvent.press(screen.getByRole('button', { name: 'Book session' }));

    expect(onPress).toHaveBeenCalledTimes(1);
    expect(Haptics.impactAsync).toHaveBeenCalledWith('light');
  });

  it('announces busy and ignores taps while loading', async () => {
    const onPress = jest.fn();
    await wrap(<Button label="Save" loading onPress={onPress} />);

    const button = screen.getByRole('button', { name: 'Save' });
    expect(button).toBeBusy();
    expect(button).toBeDisabled();
    await fireEvent.press(button);
    expect(onPress).not.toHaveBeenCalled();
  });
});

describe('Text', () => {
  it('marks titles as headings for screen readers', async () => {
    await wrap(
      <>
        <Text variant="title">Your day</Text>
        <Text>Body copy</Text>
      </>,
    );

    expect(screen.getByRole('header', { name: 'Your day' })).toBeOnTheScreen();
    expect(screen.queryByRole('header', { name: 'Body copy' })).toBeNull();
  });
});

describe('TextField', () => {
  it('has a visible label and announces errors', async () => {
    await wrap(<TextField label="Email address" error="Enter a full email address." />);

    expect(screen.getByLabelText('Email address')).toBeOnTheScreen();
    expect(screen.getByRole('alert')).toHaveTextContent('Enter a full email address.');
  });
});

describe('ChoiceTiles', () => {
  function Harness() {
    const [value, setValue] = useState<'standard' | 'large'>('standard');
    return (
      <ChoiceTiles
        label="Text size"
        value={value}
        onChange={setValue}
        options={[
          { value: 'standard', label: 'Standard' },
          { value: 'large', label: 'Large', description: '15% larger' },
        ]}
      />
    );
  }

  it('is a radio group whose selection follows taps', async () => {
    await wrap(<Harness />);

    // The group container stays non-focusable so each tile is reachable; it still carries the role and label.
    expect(screen.getByLabelText('Text size', { exact: true }).props.accessibilityRole).toBe('radiogroup');
    expect(screen.getByRole('radio', { name: 'Standard' })).toBeChecked();

    await fireEvent.press(screen.getByRole('radio', { name: 'Large. 15% larger' }));

    expect(screen.getByRole('radio', { name: 'Large. 15% larger' })).toBeChecked();
    expect(screen.getByRole('radio', { name: 'Standard' })).not.toBeChecked();
  });
});

describe('EmptyState', () => {
  it('teaches the next step with one main action', async () => {
    await wrap(<EmptyState title="Your contacts live here" description="Connect your CRM." action={<Button label="Connect your CRM" />} />);

    expect(screen.getByRole('header', { name: 'Your contacts live here' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Connect your CRM' })).toBeOnTheScreen();
  });
});

describe('Undo toast', () => {
  beforeEach(() => jest.useFakeTimers());
  afterEach(() => jest.useRealTimers());

  function Archive() {
    const undoToast = useUndoToast();
    const [archived, setArchived] = useState(false);
    return (
      <>
        <Text>{archived ? 'Archived' : 'Active'}</Text>
        <Button
          label="Archive"
          onPress={() => {
            setArchived(true);
            undoToast({ message: 'Contact archived', onUndo: () => setArchived(false) });
          }}
        />
      </>
    );
  }

  it('does the action, offers Undo, and reverses it', async () => {
    await wrap(
      <ToastProvider>
        <Archive />
      </ToastProvider>,
    );

    await fireEvent.press(screen.getByRole('button', { name: 'Archive' }));
    expect(screen.getByText('Archived')).toBeOnTheScreen();
    expect(screen.getByText('Contact archived')).toBeOnTheScreen();

    await act(async () => fireEvent.press(screen.getByRole('button', { name: 'Undo' })));

    expect(screen.getByText('Active')).toBeOnTheScreen();
    expect(screen.getByText('Undone')).toBeOnTheScreen();
  });

  it('keeps Undo available for 10 seconds', async () => {
    await wrap(
      <ToastProvider>
        <Archive />
      </ToastProvider>,
    );

    await fireEvent.press(screen.getByRole('button', { name: 'Archive' }));
    await act(async () => {
      jest.advanceTimersByTime(9_000);
    });
    expect(screen.getByRole('button', { name: 'Undo' })).toBeOnTheScreen();
    await act(async () => {
      jest.advanceTimersByTime(1_500);
    });
    expect(screen.queryByRole('button', { name: 'Undo' })).toBeNull();
  });
});

describe('Sheet', () => {
  it('shows its title and closes with the Close button', async () => {
    const onClose = jest.fn();
    await wrap(
      <Sheet open title="Connect your CRM" onClose={onClose}>
        <Text>Choose a CRM</Text>
      </Sheet>,
    );

    expect(screen.getByRole('header', { name: 'Connect your CRM' })).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Close' }));
    expect(onClose).toHaveBeenCalled();
  });
});

describe('OfflineBanner', () => {
  it('is hidden online and announced offline', async () => {
    const { rerender } = await wrap(<OfflineBanner />);
    expect(screen.queryByRole('alert')).toBeNull();

    jest.mocked(useNetworkState).mockReturnValue({ isConnected: false, isInternetReachable: false });
    await rerender(
      <I18nextProvider i18n={i18n}>
        <OfflineBanner />
      </I18nextProvider>,
    );
    expect(screen.getByRole('alert')).toHaveTextContent(/offline/);
  });
});
