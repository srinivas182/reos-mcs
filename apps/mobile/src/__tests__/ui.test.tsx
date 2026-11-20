import { act, fireEvent, render, screen } from '@testing-library/react-native';
import * as Haptics from 'expo-haptics';
import { useNetworkState } from 'expo-network';
import { useState, type ReactNode } from 'react';
import { I18nextProvider } from 'react-i18next';

import { i18n } from '@/lib/i18n';
import { Calculator } from 'lucide-react-native';

import { AskBar, Button, Callout, ChatBubble, Chip, CodeInput, EmptyState, ListRow, OfflineBanner, OptionGroup, PillGroup, ProgressBar, ProgressRing, Sheet, SwitchRow, TabBar, Text, TextField, ToastProvider, useUndoToast } from '@/ui';

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

describe('PillGroup', () => {
  function Harness() {
    const [value, setValue] = useState<'3' | '5'>('3');
    return <PillGroup label="Study days" value={value} onChange={setValue} options={[{ value: '3', label: '3 days' }, { value: '5', label: '5 days' }]} />;
  }

  it('is a radio group whose selection follows taps', async () => {
    await wrap(<Harness />);

    // The group container stays non-focusable so each pill is reachable; it carries the role and label.
    expect(screen.getByLabelText('Study days', { exact: true }).props.accessibilityRole).toBe('radiogroup');
    expect(screen.getByRole('radio', { name: '3 days' })).toBeChecked();
    await fireEvent.press(screen.getByRole('radio', { name: '5 days' }));
    expect(screen.getByRole('radio', { name: '5 days' })).toBeChecked();
    expect(screen.getByRole('radio', { name: '3 days' })).not.toBeChecked();
  });
});

describe('OptionGroup', () => {
  const options = [
    { value: 'a', label: 'Keep it to themselves' },
    { value: 'b', label: 'Share it with the buyer' },
  ] as const;

  it('reads lettered options and selection', async () => {
    const onChange = jest.fn();
    await wrap(<OptionGroup label="Answers" lettered options={options} value={null} onChange={onChange} />);

    await fireEvent.press(screen.getByRole('radio', { name: 'B. Share it with the buyer' }));
    expect(onChange).toHaveBeenCalledWith('b');
  });

  it('marks right and wrong after a quiz answer and locks the group', async () => {
    const onChange = jest.fn();
    await wrap(<OptionGroup label="Answers" lettered options={options} value="a" onChange={onChange} correct="b" />);

    expect(screen.getByRole('radio', { name: 'A. Keep it to themselves. Your answer, incorrect' })).toBeDisabled();
    expect(screen.getByRole('radio', { name: 'B. Share it with the buyer. Correct answer' })).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('radio', { name: /Share it/ }));
    expect(onChange).not.toHaveBeenCalled();
  });
});

describe('Chip', () => {
  it('is a label by default and a 48-pt button when pressable', async () => {
    const onPress = jest.fn();
    await wrap(
      <>
        <Chip tone="success" label="On track" />
        <Chip label="Next" onPress={onPress} />
      </>,
    );

    expect(screen.getByLabelText('On track')).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'On track' })).toBeNull();
    await fireEvent.press(screen.getByRole('button', { name: 'Next' }));
    expect(onPress).toHaveBeenCalled();
  });
});

describe('Progress', () => {
  it('reports values to screen readers, clamped to 0–100', async () => {
    await wrap(
      <>
        <ProgressBar value={140} label="Quiz progress" />
        <ProgressRing value={68} label="Exam readiness" />
      </>,
    );

    expect(screen.getByRole('progressbar', { name: 'Quiz progress' })).toHaveProp('accessibilityValue', { min: 0, max: 100, now: 100 });
    expect(screen.getByRole('progressbar', { name: 'Exam readiness' })).toHaveProp('accessibilityValue', { min: 0, max: 100, now: 68 });
  });
});

describe('TabBar', () => {
  it("shows each role's own tabs from the journeys", async () => {
    await wrap(<TabBar role="candidate" active="today" onSelect={() => undefined} />);
    expect(screen.getAllByRole('tab').map((tab) => tab.props.accessibilityLabel)).toEqual(['Today', 'Learn', 'Practice', 'Ask']);
  });

  it('marks the active tab, announces counts and reports taps', async () => {
    const onSelect = jest.fn();
    await wrap(<TabBar role="broker" active="today" onSelect={onSelect} badges={{ approvals: 3 }} />);

    expect(screen.getAllByRole('tab')).toHaveLength(5);
    expect(screen.getByRole('tab', { name: 'Today' })).toBeSelected();
    await fireEvent.press(screen.getByRole('tab', { name: 'Approvals, 3 new' }));
    expect(onSelect).toHaveBeenCalledWith('approvals');
  });
});

describe('AskBar', () => {
  it('opens typing or voice, both labelled', async () => {
    const onPress = jest.fn();
    const onVoice = jest.fn();
    await wrap(<AskBar placeholder="Ask anything about the exam" onPress={onPress} onVoice={onVoice} />);

    await fireEvent.press(screen.getByRole('button', { name: 'Ask anything about the exam' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Ask by voice' }));
    expect(onPress).toHaveBeenCalled();
    expect(onVoice).toHaveBeenCalled();
  });
});

describe('Callout, chat and rows', () => {
  it('renders feedback, messages with sources, and tappable rows', async () => {
    const onPress = jest.fn();
    await wrap(
      <>
        <Callout tone="success" title="Correct, nicely done." body="A buyer's agent owes the buyer loyalty." />
        <ChatBubble from="me">How do I prorate taxes?</ChatBubble>
        <ChatBubble from="assistant" source="Real Estate Math, lesson 7">
          <Text>Three steps.</Text>
        </ChatBubble>
        <ListRow icon={Calculator} title="Math lab" subtitle="8 min" onPress={onPress} />
      </>,
    );

    expect(screen.getByText('Correct, nicely done.')).toBeOnTheScreen();
    expect(screen.getByLabelText('You: How do I prorate taxes?')).toBeOnTheScreen();
    expect(screen.getByText('Source: Real Estate Math, lesson 7')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Math lab. 8 min' }));
    expect(onPress).toHaveBeenCalled();
  });

  it('switch rows are labelled switches', async () => {
    const onValueChange = jest.fn();
    await wrap(<SwitchRow label="Study reminders" value={false} onValueChange={onValueChange} />);

    await fireEvent(screen.getByRole('switch', { name: 'Study reminders' }), 'valueChange', true);
    expect(onValueChange).toHaveBeenCalledWith(true);
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

describe('withAlpha', () => {
  it('turns a hex colour into rgba', () => {
    const { withAlpha } = jest.requireActual<typeof import('@/ui/tones')>('@/ui/tones');
    expect(withAlpha('#15803D', 0.25)).toBe('rgba(21, 128, 61, 0.25)');
    expect(withAlpha('not-a-colour', 0.5)).toBe('not-a-colour');
  });
});

describe('CodeInput', () => {
  it('is one labelled field that keeps digits only', async () => {
    const onChange = jest.fn();
    await render(<CodeInput label="Six-digit code" value="48" onChange={onChange} />);

    const field = screen.getByLabelText('Six-digit code');
    expect(field.props.textContentType).toBe('oneTimeCode');
    expect(field.props.autoComplete).toBe('sms-otp');

    await fireEvent.changeText(field, '48-29 13 7');
    expect(onChange).toHaveBeenCalledWith('482913');
  });
});
