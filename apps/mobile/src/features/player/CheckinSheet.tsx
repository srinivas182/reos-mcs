import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import type { CheckinResult, PlayerState, ReportReason } from '@/lib/player';
import { reportQuestion } from '@/lib/player';
import { Button, Callout, OptionGroup, PillGroup, Sheet, Text } from '@/ui';
import { SaveFlashcard } from '@/features/practice/SaveFlashcard';

type Checkpoint = NonNullable<PlayerState['checkin']>;

const REASONS: ReportReason[] = ['wrong_answer', 'unclear', 'out_of_date', 'other'];

/**
 * A check-in (A8.2): the video stops and seat time waits until it's
 * answered; the sheet can't be closed any other way. A question shows
 * whether the answer was right and why, with its source (a wrong answer
 * still counts as present), and can be reported. A presence check only asks
 * "Are you still here?".
 */
export function CheckinSheet({
  checkpoint,
  online,
  onAnswer,
}: {
  checkpoint: Checkpoint | null;
  online: boolean;
  onAnswer: (key: string, choice: number | null) => Promise<CheckinResult | null>;
}) {
  const { t } = useTranslation();
  // An answered question stays open with its feedback until Continue.
  const [held, setHeld] = useState<{ checkpoint: Checkpoint; result: CheckinResult } | null>(null);
  const showing = held?.checkpoint ?? checkpoint;

  return (
    <Sheet
      open={showing !== null}
      onClose={() => undefined}
      dismissible={false}
      title={showing?.question ? t('player.checkin.title') : t('player.checkin.presenceTitle')}
    >
      {showing ? (
        <Body
          key={showing.key}
          checkpoint={showing}
          online={online}
          result={held?.result ?? null}
          onAnswer={async (key, choice) => {
            const result = await onAnswer(key, choice);
            if (result && showing.question) setHeld({ checkpoint: showing, result });

            return result;
          }}
          onContinue={() => setHeld(null)}
        />
      ) : null}
    </Sheet>
  );
}

function Body({
  checkpoint,
  online,
  result,
  onAnswer,
  onContinue,
}: {
  checkpoint: Checkpoint;
  online: boolean;
  result: CheckinResult | null;
  onAnswer: (key: string, choice: number | null) => Promise<CheckinResult | null>;
  onContinue: () => void;
}) {
  const { t } = useTranslation();
  const [choice, setChoice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);
  const [reporting, setReporting] = useState(false);
  const [reason, setReason] = useState<ReportReason>('wrong_answer');
  const [reported, setReported] = useState(false);
  const question = checkpoint.question;

  const send = async () => {
    setBusy(true);
    setFailed(false);
    const answer = await onAnswer(checkpoint.key, choice === null ? null : Number(choice)).catch(() => null);
    setBusy(false);
    if (!answer) setFailed(true);
  };

  if (!question) {
    return (
      <>
        <Text>{t('player.checkin.presenceBody')}</Text>
        {failed ? <Callout tone="danger" title={t('player.errors.checkin')} live /> : null}
        <Button label={t('player.checkin.here')} loading={busy} onPress={() => void send()} fullWidth />
      </>
    );
  }

  return (
    <>
      <Text variant="bodyLarge" weight="semibold">
        {question.prompt}
      </Text>
      {result ? (
        <>
          <Callout
            tone={result.correct ? 'success' : 'warning'}
            title={result.correct ? t('player.checkin.right') : t('player.checkin.wrong')}
            body={[result.explanation, result.source ? t('player.checkin.source', { source: result.source }) : null].filter(Boolean).join('\n')}
            live
          />
          {result.explanation ? <SaveFlashcard source="checkin" front={question.prompt} back={result.explanation} /> : null}
          {online && !reported ? (
            reporting ? (
              <>
                <PillGroup
                  label={t('player.report.title')}
                  value={reason}
                  options={REASONS.map((r) => ({ value: r, label: t(`player.report.reasons.${r}`) }))}
                  onChange={setReason}
                />
                <Button
                  label={t('player.report.send')}
                  variant="secondary"
                  onPress={() => void reportQuestion(question.id, reason).then((ok) => setReported(ok))}
                />
              </>
            ) : (
              <Button label={t('player.report.title')} variant="ghost" onPress={() => setReporting(true)} />
            )
          ) : null}
          {reported ? <Text tone="muted">{t('player.report.sent')}</Text> : null}
          <Button label={t('player.checkin.continue')} onPress={onContinue} fullWidth />
        </>
      ) : (
        <>
          <OptionGroup
            label={question.prompt}
            value={choice}
            options={question.options.map((option, i) => ({ value: String(i), label: option }))}
            onChange={setChoice}
          />
          {failed ? <Callout tone="danger" title={t('player.errors.checkin')} live /> : null}
          <Button label={t('player.checkin.answer')} loading={busy} disabled={choice === null} onPress={() => void send()} fullWidth />
        </>
      )}
    </>
  );
}
