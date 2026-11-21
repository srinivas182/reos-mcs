import type { Schemas } from '@reos/api-client';
import { router } from 'expo-router';
import { Check } from 'lucide-react-native';
import { useEffect, useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { updateDraft, useDraft } from '@/lib/onboarding';
import { Button, Chip, Eyebrow, Icon, Screen, Text, TextField } from '@/ui';

type State = Schemas['StateResource'];

/**
 * Where do you want to be licensed? (mobile journey screen 2; "Where are
 * you licensed?" for licensed agents). Live states continue; the rest go to
 * the waitlist (screen 3). The list comes from the super admin's States.
 */
export default function StateScreen() {
  const { t } = useTranslation();
  const draft = useDraft();
  const [states, setStates] = useState<State[]>([]);
  const [query, setQuery] = useState('');
  const [selected, setSelected] = useState<string>(draft.state ?? 'TX');
  const licensed = draft.path === 'licensed_agent';

  useEffect(() => {
    let active = true;
    void api()
      .GET('/states')
      .then(({ data }) => active && data && setStates(data.data));

    return () => {
      active = false;
    };
  }, []);

  const shown = useMemo(() => {
    const search = query.trim().toLowerCase();

    return search === '' ? states : states.filter((s) => s.name.toLowerCase().includes(search) || s.code.toLowerCase() === search);
  }, [states, query]);
  const chosen = states.find((s) => s.code === selected);

  const pick = (state: State) => {
    if (state.live) {
      setSelected(state.code);
    } else {
      router.push({ pathname: '/waitlist', params: { state: state.code, name: state.name, waiting: String(state.waiting) } });
    }
  };

  const next = () => {
    if (!chosen?.live) {
      return;
    }

    updateDraft({ state: chosen.code });
    router.push(licensed ? '/sign-in' : '/welcome');
  };

  return (
    <Screen footer={<View style={styles.footer}><Button label={t('onboarding.state.continue', { state: chosen?.name ?? '' })} disabled={!chosen?.live} fullWidth onPress={next} /></View>}>
      <Eyebrow>{t('onboarding.state.step', { step: 1, total: 4 })}</Eyebrow>
      <Text variant="headline" heading>
        {licensed ? t('onboarding.state.titleLicensed') : t('onboarding.state.titleCandidate')}
      </Text>
      <Text tone="muted">{t('onboarding.state.subtitle')}</Text>
      <TextField label={t('onboarding.state.search')} value={query} onChangeText={setQuery} autoCorrect={false} />

      <View accessibilityRole="radiogroup" style={styles.list}>
        {shown.map((state) => {
          const isSelected = state.code === selected;

          return (
            <Pressable
              key={state.code}
              accessibilityRole="radio"
              accessibilityState={{ checked: isSelected }}
              accessibilityLabel={`${state.name}, ${state.live ? t('onboarding.state.live') : t('onboarding.state.comingSoon')}`}
              onPress={() => pick(state)}
              style={({ pressed }) => [styles.row, isSelected && styles.selected, pressed && styles.pressed]}
            >
              <View style={styles.code}>
                <Text weight="bold" style={styles.codeText}>
                  {state.code}
                </Text>
              </View>
              <View style={styles.name}>
                <Text variant="bodyLarge" weight="semibold">
                  {state.name}
                </Text>
                {state.live ? (
                  <Text variant="caption" tone="muted">
                    {t('onboarding.state.liveDetail')}
                  </Text>
                ) : null}
              </View>
              <Chip label={state.live ? t('onboarding.state.live') : t('onboarding.state.comingSoon')} tone={state.live ? 'success' : 'neutral'} />
              {isSelected ? <Icon icon={Check} color="action" /> : null}
            </Pressable>
          );
        })}
      </View>
      {query && shown.length === 0 ? <Text tone="muted">{t('onboarding.state.noResults', { search: query })}</Text> : null}
      <Text variant="caption" tone="muted">
        {t('onboarding.state.note')}
      </Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  list: { gap: theme.space.sm },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + theme.space.lg,
    paddingHorizontal: theme.space.md,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  selected: { borderColor: theme.colors.action, borderWidth: 2, backgroundColor: theme.colors.actionTint },
  pressed: { opacity: 0.85 },
  code: {
    width: theme.m.iconTile,
    height: theme.m.iconTile,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.heroBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  codeText: { color: theme.colors.heroText },
  name: { flex: 1 },
}));
