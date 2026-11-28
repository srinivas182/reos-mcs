import { router } from 'expo-router';
import { ArrowRight, CircleSlash, Landmark } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { joinWithCode, moveLines, type MovePreview, previewMove } from '@/lib/moves';
import { Button, Callout, Card, Icon, Screen, SectionTitle, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Join another brokerage or team (A14.3): the invite code, then — for an
 * agent elsewhere — what moves with them, what stays with the old
 * brokerage and what ends, before they confirm.
 */
export default function MoveScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [code, setCode] = useState('');
  const [preview, setPreview] = useState<MovePreview | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const move = preview?.move ?? null;

  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await work();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const groups = move
    ? [
        { title: t('moves.movesTitle'), icon: ArrowRight, lines: moveLines(move.moves, t) },
        { title: t('moves.staysTitle', { from: move.from.name }), icon: Landmark, lines: moveLines(move.stays, t) },
        { title: t('moves.endsTitle'), icon: CircleSlash, lines: moveLines(move.ends, t) },
      ].filter((g) => g.lines.length > 0)
    : [];

  return (
    <Screen>
      <TitleBar title={t('moves.phone.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('moves.phone.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      <TextField label={t('moves.phone.code')} value={code} autoCapitalize="characters" maxLength={20} onChangeText={setCode} />
      <Button label={t('moves.phone.check')} variant={preview ? 'secondary' : 'primary'} loading={busy && !preview} disabled={code.trim() === ''} onPress={() => void run(async () => setPreview(await previewMove(code)))} />

      {preview ? (
        <Card>
          <Text weight="semibold">{move ? (move.kind === 'team' ? t('moves.teamTitle', { to: move.to.name }) : t('moves.title', { to: move.to.name })) : t('moves.phone.invited', { tenant: preview.tenant })}</Text>
          {preview.team ? <Text tone="muted">{t('moves.phone.team', { team: preview.team })}</Text> : null}
          {move ? <Text tone="muted">{move.kind === 'team' ? t('moves.teamIntro', { from: move.from.name }) : t('moves.intro', { from: move.from.name, to: move.to.name })}</Text> : null}
        </Card>
      ) : null}

      {groups.map((group) => (
        <Card key={group.title}>
          <Icon icon={group.icon} color="action" size={24} />
          <SectionTitle>{group.title}</SectionTitle>
          {group.lines.map((line) => (
            <Text key={line}>{`• ${line}`}</Text>
          ))}
        </Card>
      ))}

      {preview ? (
        <Button
          label={move ? t('moves.join', { to: move.to.name }) : t('moves.phone.joinOnly', { tenant: preview.tenant })}
          loading={busy}
          onPress={() =>
            void run(async () => {
              const name = await joinWithCode(code, move !== null);
              toast({ message: t('moves.joined', { to: name }) });
              router.replace('/');
            })
          }
        />
      ) : null}
    </Screen>
  );
}
