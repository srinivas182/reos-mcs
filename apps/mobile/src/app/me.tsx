import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ArrowLeftRight, Languages, ShieldCheck } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { webBaseUrl } from '@/lib/api';
import { useAuth, type Membership } from '@/lib/auth';
import { activeMembership, organisationsOf, roleName } from '@/lib/roles';
import { Button, Callout, ChoiceRow, MenuCard, MenuGroup, MenuRow, ProfileHeader, Screen, Sheet, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Me (mobile journey screen 16): name, role and organisation, every setting
 * in one list, Switch role, Sign out. Tapping the organisation opens Your
 * organisations (screen 150). Rows for features not built yet (My goal,
 * Connections, CE wallet, Membership, Downloads, Notifications, Help) are
 * added in their sprints.
 */
export default function MeScreen() {
  const { t } = useTranslation();
  const { user, switchRole, signOut } = useAuth();
  const showToast = useUndoToast();
  const [sheet, setSheet] = useState<'organisations' | 'roles' | null>(null);
  const current = activeMembership(user);
  const name = user?.name ?? user?.email ?? user?.phone ?? '';
  const others = (user?.roles ?? []).filter((m) => m !== current);
  const organisations = organisationsOf(user);

  // Reversible, so an Undo toast rather than a confirmation.
  const choose = async (membership: Membership, message: string) => {
    setSheet(null);
    const previous = current;

    if ((await switchRole(membership.role, membership.organisation.id)) && previous) {
      showToast({ message, onUndo: () => void switchRole(previous.role, previous.organisation.id) });
    }
  };

  // Switching organisation keeps the role when it's held there too.
  const chooseOrganisation = (organisationId: number) => {
    const held = (user?.roles ?? []).filter((m) => m.organisation.id === organisationId);
    const next = held.find((m) => m.role === current?.role) ?? held[0];
    if (next) {
      void choose(next, t('mobile.organisations.switched', { organisation: next.organisation.name }));
    }
  };

  return (
    <Screen>
      <TitleBar title="" onBack={() => router.back()} />
      <ProfileHeader
        name={name}
        detail={current ? roleName(t, current.role) : ''}
        link={current?.organisation.name}
        onLinkPress={() => setSheet('organisations')}
      />

      {user?.two_step.required && current ? (
        <Callout
          tone="warning"
          title={t('mobile.me.twoStepTitle', { role: roleName(t, current.role) })}
          body={t('mobile.me.twoStepBody')}
          action={<Button label={t('mobile.me.openWeb')} variant="secondary" onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/settings/security`)} />}
        />
      ) : null}

      <MenuGroup>
        <MenuRow icon={ShieldCheck} title={t('mobile.me.privacy')} onPress={() => router.push('/privacy')} />
        <MenuRow icon={Languages} title={t('mobile.me.accessibility')} onPress={() => router.push('/accessibility')} />
      </MenuGroup>

      {others.length > 0 ? (
        <MenuCard
          icon={ArrowLeftRight}
          title={others.length === 1 && others[0] ? t('mobile.me.switchRoleTo', { role: roleName(t, others[0].role) }) : t('mobile.me.switchRole')}
          onPress={() => setSheet('roles')}
        />
      ) : null}

      <Pressable accessibilityRole="button" onPress={() => void signOut()} style={styles.signOut}>
        <Text weight="semibold" tone="danger">
          {t('mobile.me.signOut')}
        </Text>
      </Pressable>

      <Sheet open={sheet === 'organisations'} onClose={() => setSheet(null)} title={t('mobile.organisations.title')}>
        <Text tone="muted">{t('mobile.organisations.intro')}</Text>
        <View accessibilityRole="radiogroup">
          {organisations.map((organisation) => (
            <ChoiceRow
              key={organisation.id}
              title={organisation.name}
              detail={(user?.roles ?? [])
                .filter((m) => m.organisation.id === organisation.id)
                .map((m) => roleName(t, m.role))
                .join(', ')}
              selected={organisation.id === current?.organisation.id}
              onPress={() => chooseOrganisation(organisation.id)}
            />
          ))}
        </View>
        <Text variant="caption" tone="muted">
          {t('mobile.organisations.note')}
        </Text>
      </Sheet>

      <Sheet open={sheet === 'roles'} onClose={() => setSheet(null)} title={t('mobile.roles.title')}>
        <Text tone="muted">{t('mobile.roles.intro')}</Text>
        <View accessibilityRole="radiogroup">
          {(user?.roles ?? []).map((membership) => (
            <ChoiceRow
              key={`${membership.role}:${membership.organisation.id}`}
              title={roleName(t, membership.role)}
              detail={membership.organisation.name}
              tile={membership.organisation.name}
              selected={membership === current}
              onPress={() => void choose(membership, t('mobile.roles.switched', { role: roleName(t, membership.role) }))}
            />
          ))}
        </View>
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  signOut: { alignSelf: 'center', minHeight: theme.touch, justifyContent: 'center', paddingHorizontal: theme.space.lg },
}));
