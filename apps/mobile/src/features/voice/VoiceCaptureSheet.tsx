import { Mic, Square } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { dayLabel } from '@/lib/calendar';
import { type ContactMatches, matchContacts } from '@/lib/contacts';
import { scheduleTodoReminder } from '@/lib/todoReminders';
import { saveTodo, type TodoDraft } from '@/lib/todos';
import { type CardItem, cardItems, type Engine, moveDay, moveMinutes, setRemind, speechLanguage, toggleDate, understand, useSpeech } from '@/lib/voice';
import { Button, Callout, Card, Icon, PillGroup, Sheet, Stepper, SwitchRow, Text, TextField, useUndoToast } from '@/ui';

export type CaptureMode = 'voice' | 'type';

type Props = {
  /** How it opens: listening at once (the mic) or with the keyboard; null when closed. */
  mode: CaptureMode | null;
  onClose: () => void;
  /** After saving, so lists reload. */
  onSaved: () => void;
};

type Step = { step: 'listen' } | { step: 'type' } | { step: 'reading'; text: string } | { step: 'confirm'; text: string; engine: Engine; items: CardItem[]; contacts: ContactMatches };

/**
 * Voice capture (agent app journey, Today; B4.3b): hold the mic and speak,
 * or type. REOS reads it (Claude, or the phone with no signal) and shows a
 * confirm card; nothing is saved until the person taps Save. Reminders are
 * scheduled on this phone.
 */
export function VoiceCaptureSheet({ mode, onClose, onSaved }: Props) {
  const { t } = useTranslation();

  if (mode === null) {
    return null;
  }

  return (
    <Sheet open onClose={onClose} title={t('voice.screenTitle')}>
      <Capture mode={mode} onClose={onClose} onSaved={onSaved} />
    </Sheet>
  );
}

function Capture({ mode, onClose, onSaved }: { mode: CaptureMode; onClose: () => void; onSaved: () => void }) {
  const { t, i18n } = useTranslation();
  const language = speechLanguage(i18n.language);
  const [step, setStep] = useState<Step>(mode === 'voice' ? { step: 'listen' } : { step: 'type' });
  const [typed, setTyped] = useState('');
  const [nothing, setNothing] = useState<string | null>(null);
  const read = async (text: string, source: TodoDraft['source']) => {
    if (text.trim() === '') {
      setNothing(t('voice.nothingHeard'));

      return;
    }

    setNothing(null);
    setStep({ step: 'reading', text });
    const understood = await understand(text, language);

    if (understood.items.length === 0) {
      setNothing(t('voice.nothingUnderstood'));
      setStep(source === 'voice' ? { step: 'listen' } : { step: 'type' });

      return;
    }

    const items = cardItems(understood.items, source);
    const contacts = await matchContacts([...new Set(items.flatMap((item) => item.people.slice(0, 1)))]);
    setStep({ step: 'confirm', text, engine: understood.engine, items: preselect(items, contacts), contacts });
  };
  const speech = useSpeech(language, (text) => void read(text, 'voice'));
  const started = useRef(false);

  useEffect(() => {
    if (mode === 'voice' && !started.current) {
      started.current = true;
      void speech.start();
    }
  }, [mode, speech]);

  if (step.step === 'reading') {
    return (
      <View accessibilityLiveRegion="polite" style={styles.center}>
        <ActivityIndicator />
        <Text>{t('voice.reading')}</Text>
        <Text tone="muted">{t('voice.heard', { text: step.text })}</Text>
      </View>
    );
  }

  if (step.step === 'confirm') {
    return <Confirm key={step.text} {...step} onClose={onClose} onSaved={onSaved} />;
  }

  if (step.step === 'type') {
    return (
      <>
        <TextField label={t('voice.typeLabel')} hint={t('voice.screenHint')} value={typed} onChangeText={setTyped} autoFocus multiline returnKeyType="done" />
        {nothing ? <Callout tone="warning" title={nothing} live /> : null}
        <Button label={t('voice.understand')} disabled={typed.trim() === ''} onPress={() => void read(typed, 'typed')} fullWidth />
      </>
    );
  }

  const listening = speech.state === 'listening';

  return (
    <>
      <Text tone="muted">{t('voice.screenHint')}</Text>
      <MicButton listening={listening} onStart={() => void speech.start()} onStop={speech.stop} />
      <View accessibilityLiveRegion="polite">
        {speech.transcript ? <Text variant="bodyLarge">{t('voice.heard', { text: speech.transcript })}</Text> : null}
      </View>
      {speech.state === 'denied' ? <Callout tone="warning" title={t('voice.noPermission')} live /> : null}
      {speech.state === 'unavailable' ? <Callout tone="warning" title={t('voice.unavailable')} live /> : null}
      {nothing && !listening ? <Callout tone="warning" title={nothing} live /> : null}
      <Button
        label={t('voice.typeInstead')}
        variant="secondary"
        onPress={() => {
          if (listening) speech.stop();
          setStep({ step: 'type' });
        }}
      />
    </>
  );
}

/**
 * Hold to talk (let go to finish), or tap to start and tap again to stop:
 * tapping works with screen readers and for anyone who can't hold.
 */
function MicButton({ listening, onStart, onStop }: { listening: boolean; onStart: () => void; onStop: () => void }) {
  const { t } = useTranslation();
  const heldFrom = useRef<number | null>(null);
  styles.useVariants({ listening });

  return (
    <View style={styles.micWrap}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={listening ? t('voice.tapToStop') : t('voice.start')}
        accessibilityState={{ busy: listening }}
        onPressIn={() => {
          heldFrom.current = listening ? null : Date.now();
          if (!listening) onStart();
        }}
        onPressOut={() => {
          // Held: letting go finishes. A quick tap keeps listening until the next tap.
          if (heldFrom.current !== null && Date.now() - heldFrom.current > 400) onStop();
        }}
        onPress={() => {
          if (heldFrom.current === null) onStop();
        }}
        style={styles.mic}
      >
        <Icon icon={listening ? Square : Mic} color={listening ? 'onDanger' : 'onAction'} size={32} />
      </Pressable>
      <Text weight="semibold" tone={listening ? 'action' : undefined}>
        {listening ? t('voice.tapToStop') : t('voice.hold')}
      </Text>
    </View>
  );
}

/** One contact found for the name: linked unless the person says otherwise. */
function preselect(items: CardItem[], contacts: ContactMatches): CardItem[] {
  if (!contacts.allowed) return items;

  return items.map((item) => {
    const name = item.people[0];
    const found = name === undefined ? [] : (contacts.byName[name] ?? []);

    return found.length === 1 && found[0] ? { ...item, draft: { ...item.draft, contact: found[0] } } : item;
  });
}

function Confirm({ engine, items: first, contacts, onClose, onSaved }: Extract<Step, { step: 'confirm' }> & { onClose: () => void; onSaved: () => void }) {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [items, setItems] = useState(first);
  const [busy, setBusy] = useState(false);
  const [problems, setProblems] = useState<string[]>([]);
  const change = (index: number, update: (draft: TodoDraft) => TodoDraft) =>
    setItems((current) => current.map((item, i) => (i === index ? { ...item, draft: update(item.draft) } : item)));
  const ready = items.every((item) => item.draft.title.trim() !== '');

  const save = async () => {
    setBusy(true);
    const failed: string[] = [];
    let offline = false;
    let remindersOff = false;

    for (const { draft } of items) {
      const { todo, outcome } = await saveTodo(draft);

      if (outcome === 'refused') {
        failed.push(draft.title);
        continue;
      }

      offline ||= outcome === 'offline';

      if (draft.remind_at !== null) {
        const set = await scheduleTodoReminder({ clientId: draft.clientId, id: todo?.id ?? null, title: draft.title.trim(), remindAt: draft.remind_at });
        remindersOff ||= !set && Date.parse(draft.remind_at) > Date.now();
      }
    }

    setBusy(false);

    if (failed.length > 0) {
      setProblems(failed);
      setItems((current) => current.filter((item) => failed.includes(item.draft.title)));

      return;
    }

    onSaved();
    onClose();
    toast({ message: remindersOff ? t('voice.reminderOff') : offline ? t('voice.savedOffline') : t('voice.saved', { count: items.length }) });
  };

  return (
    <>
      <Text variant="title" heading>
        {t('voice.confirmTitle', { count: items.length })}
      </Text>
      {engine === 'phone' ? <Callout tone="info" title={t('voice.offline')} /> : null}
      {engine === 'parser' ? <Text tone="muted">{t('voice.byParser')}</Text> : null}
      {problems.map((title) => (
        <Callout key={title} tone="danger" title={t('voice.saveFailed', { title })} live />
      ))}
      {items.map((item, index) => (
        <ItemCard key={item.draft.clientId} item={item} index={items.length > 1 ? index + 1 : null} contacts={contacts} onChange={(update) => change(index, update)} />
      ))}
      <Button label={t('voice.save', { count: items.length })} loading={busy} disabled={!ready || busy} onPress={() => void save()} fullWidth />
      <Button label={t('voice.discard')} variant="ghost" onPress={onClose} />
    </>
  );
}

function ItemCard({ item, index, contacts, onChange }: { item: CardItem; index: number | null; contacts: ContactMatches; onChange: (update: (draft: TodoDraft) => TodoDraft) => void }) {
  const { t, i18n } = useTranslation();
  const { draft } = item;
  const dated = draft.due_at !== null || draft.due_date !== null;
  const day = draft.due_at !== null ? new Date(draft.due_at) : draft.due_date !== null ? new Date(`${draft.due_date}T12:00:00`) : null;
  const time = draft.due_at === null ? null : new Intl.DateTimeFormat(i18n.language, { hour: 'numeric', minute: '2-digit' }).format(new Date(draft.due_at));
  const label = index === null ? undefined : t('voice.item', { n: index });

  return (
    <Card>
      {label ? (
        <Text variant="caption" weight="bold" tone="muted">
          {label}
        </Text>
      ) : null}
      <TextField label={t('voice.typeLabel')} value={draft.title} onChangeText={(title) => onChange((d) => ({ ...d, title }))} />
      {day ? (
        <>
          <Stepper
            label={t('voice.date')}
            value={dayLabel(day, i18n.language)}
            earlier={t('calendar.step.earlierDay')}
            later={t('calendar.step.laterDay')}
            onEarlier={() => onChange((d) => moveDay(d, -1))}
            onLater={() => onChange((d) => moveDay(d, 1))}
          />
          {time ? (
            <Stepper
              label={t('voice.time')}
              value={time}
              earlier={t('calendar.step.earlierTime')}
              later={t('calendar.step.laterTime')}
              onEarlier={() => onChange((d) => moveMinutes(d, -15))}
              onLater={() => onChange((d) => moveMinutes(d, 15))}
            />
          ) : null}
        </>
      ) : (
        <Text tone="muted">{t('voice.noDue')}</Text>
      )}
      <Button label={dated ? t('voice.removeDate') : t('voice.addDate')} variant="secondary" onPress={() => onChange((d) => toggleDate(d))} />
      {draft.due_at !== null ? (
        <SwitchRow label={t('voice.remind')} description={t('voice.remindHint')} value={draft.remind_at !== null} onValueChange={(on) => onChange((d) => setRemind(d, on))} />
      ) : null}
      <ContactChoice name={item.people[0]} contacts={contacts} chosen={draft.contact} onChoose={(contact) => onChange((d) => ({ ...d, contact }))} />
      <TextField label={t('voice.notes')} value={draft.notes ?? ''} onChangeText={(notes) => onChange((d) => ({ ...d, notes: notes === '' ? null : notes }))} multiline />
    </Card>
  );
}

const NONE = '__none';

function ContactChoice({ name, contacts, chosen, onChoose }: { name: string | undefined; contacts: ContactMatches; chosen: TodoDraft['contact']; onChoose: (contact: TodoDraft['contact']) => void }) {
  const { t } = useTranslation();

  if (name === undefined) return null;

  if (!contacts.allowed) {
    return <Text tone="muted">{t('voice.contactsOff', { name })}</Text>;
  }

  const found = contacts.byName[name] ?? [];

  if (found.length === 0) {
    return <Text tone="muted">{t('voice.noContactMatch', { name })}</Text>;
  }

  const label = found.length === 1 && found[0] ? t('voice.linkTo', { name: found[0].name }) : t('voice.who', { name });

  return (
    <PillGroup
      label={label}
      value={chosen?.id ?? NONE}
      options={[...found.map((contact) => ({ value: contact.id, label: found.length === 1 ? t('voice.linkTo', { name: contact.name }) : contact.name })), { value: NONE, label: t('voice.dontLink') }]}
      onChange={(id) => onChoose(found.find((contact) => contact.id === id) ?? null)}
    />
  );
}

const styles = StyleSheet.create((theme) => ({
  center: { alignItems: 'center', gap: theme.space.sm, paddingVertical: theme.space.lg },
  micWrap: { alignItems: 'center', gap: theme.space.sm, paddingVertical: theme.space.md },
  mic: {
    width: theme.touch * 2,
    height: theme.touch * 2,
    borderRadius: theme.m.radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.action,
    variants: {
      listening: {
        true: { backgroundColor: theme.colors.danger },
        false: {},
      },
    },
  },
}));
