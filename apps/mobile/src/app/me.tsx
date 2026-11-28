import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ArrowLeftRight, Bell, Camera, Building2, Compass, DoorOpen, Download, Eye, Gift, GraduationCap, IdCard, Inbox, Languages, Map, MapPin, Handshake, Megaphone, Plug, ShieldCheck, Users, Wallet, Wrench } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { webBaseUrl } from '@/lib/api';
import { useAuth, type Membership } from '@/lib/auth';
import { switchState } from '@/lib/licences';
import { activeMembership, organisationsOf, roleName } from '@/lib/roles';
import { Button, Callout, ChoiceRow, MenuCard, MenuGroup, MenuRow, ProfileHeader, Screen, Sheet, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Me (mobile journey screen 16): name, role and organisation, every setting
 * in one list, Switch role, Sign out. Tapping the organisation opens Your
 * organisations (screen 150). Rows for features not built yet (My goal,
 * CE wallet, Help) are
 * added in their sprints.
 */
export default function MeScreen() {
  const { t } = useTranslation();
  const { user, switchRole, signOut, refresh } = useAuth();
  const showToast = useUndoToast();
  const [sheet, setSheet] = useState<'organisations' | 'roles' | 'states' | null>(null);
  const current = activeMembership(user);
  const name = user?.name ?? user?.email ?? user?.phone ?? '';
  const others = (user?.roles ?? []).filter((m) => m !== current);
  const organisations = organisationsOf(user);
  const states = user?.states;
  const licensed = current !== null && current.role !== 'candidate';

  // Switching state is reversible too: Undo switches back.
  const chooseState = async (code: string, name: string) => {
    setSheet(null);
    const previous = states?.current.code;

    if (code !== previous && (await switchState(code))) {
      await refresh();
      showToast({
        message: t('mobile.states.switched', { state: name }),
        onUndo: async () => {
          if (previous && (await switchState(previous))) await refresh();
        },
      });
    }
  };

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
      <ProfileHeader name={name} detail={current ? roleName(t, current.role) : ''} link={current?.organisation.name} onLinkPress={() => setSheet('organisations')} />

      {user?.two_step.required && current ? (
        <Callout
          tone="warning"
          title={t('mobile.me.twoStepTitle', { role: roleName(t, current.role) })}
          body={t('mobile.me.twoStepBody')}
          action={<Button label={t('mobile.me.openWeb')} variant="secondary" onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/settings/security`)} />}
        />
      ) : null}

      <MenuGroup>
        {licensed ? <MenuRow icon={IdCard} title={t('mobile.me.licences')} onPress={() => router.push('/licences')} /> : null}
        {/* Join another brokerage or team (A14.3): with an invite code. */}
        {current?.role === 'agent' ? <MenuRow icon={ArrowLeftRight} title={t('moves.phone.title')} onPress={() => router.push('/me/move')} /> : null}
        {/* My goal (A11.1a, screen 146): the agent's mode and flags. */}
        {current?.role === 'agent' ? <MenuRow icon={Compass} title={t('agents.modes.title')} onPress={() => router.push('/agent/goal')} /> : null}
        {/* Open houses (B14.3a): QR and phone sign-in, each visitor a contact. */}
        {current?.role === 'agent' ? <MenuRow icon={DoorOpen} title={t('leads.openHouse.pageTitle')} onPress={() => router.push('/open-houses')} /> : null}
        {/* Referrals and invites (B14.3b): referrals between brokers; invite and earn for everyone. */}
        <MenuRow icon={Gift} title={t('leads.referrals.title')} onPress={() => router.push('/referrals')} />
        {/* Farming (B14.3c): monthly market updates for an area, checked for compliance. */}
        {current?.role === 'agent' ? <MenuRow icon={MapPin} title={t('leads.farm.title')} onPress={() => router.push('/farming')} /> : null}
        {/* Your brokerage (A15.1, broker journey screens 1–4): setup, agents, seats. Anyone else can set one up. */}
        <MenuRow icon={Building2} title={current?.role === 'broker' ? t('brokerage.setup.title') : t('brokerage.verify.title')} onPress={() => router.push(current?.role === 'broker' ? '/brokerage' : '/brokerage/verify')} />
        {/* Join the provider network (B15.1–B15.2, provider screens 1–6): the steps and the profile's status. */}
        <MenuRow icon={Wrench} title={t('providers.onboarding.title')} onPress={() => router.push('/provider/join')} />
        {/* Build your profile (B15.3, provider screen 7), for providers. */}
        {current?.role === 'provider' ? <MenuRow icon={IdCard} title={t('providers.profile.title')} onPress={() => router.push('/provider/profile')} /> : null}
        {/* Agents in my areas and requests to connect (B16.1), for providers. */}
        {current?.role === 'provider' ? <MenuRow icon={Users} title={t('providers.agents.title')} onPress={() => router.push('/provider/agents')} /> : null}
        {/* Verified providers (B15.3), for agents and brokers. */}
        {current?.role === 'agent' || current?.role === 'broker' ? <MenuRow icon={Handshake} title={t('providers.directory.title')} onPress={() => router.push('/providers')} /> : null}
        {/* Requests from providers, connections and my choices (B16.2), for agents. */}
        {current?.role === 'agent' ? <MenuRow icon={Inbox} title={t('providers.network.title')} onPress={() => router.push('/providers/requests')} /> : null}
        {/* What my broker sees (A16.1): the agent's own summary, as their broker sees it. */}
        {current?.role === 'agent' ? <MenuRow icon={Eye} title={t('brokerage.oversight.mine.title')} onPress={() => router.push('/me/broker-view')} /> : null}
        {/* My posts and results (B11, screen 169). */}
        {current?.role === 'agent' ? <MenuRow icon={Megaphone} title={t('social.posts.title')} onPress={() => router.push('/social')} /> : null}
        {current?.role === 'agent' ? <MenuRow icon={Camera} title={t('studio.brand.title')} onPress={() => router.push('/social/brand')} /> : null}
        {/* CE wallet (A11.2): hours against the renewal, certificates, renewing. */}
        {licensed ? <MenuRow icon={GraduationCap} title={t('ce.wallet.title')} onPress={() => router.push('/ce')} /> : null}
        {current?.role === 'instructor' || current?.role === 'mentor' ? <MenuRow icon={Wallet} title={t('mobile.me.earnings')} onPress={() => router.push('/earnings')} /> : null}
        {states && states.options.length > 1 ? <MenuRow icon={Map} title={t('mobile.me.state')} value={states.current.name} onPress={() => setSheet('states')} /> : null}
        {current?.role === 'candidate' ? <MenuRow icon={Users} title={t('practice.groups.profileTitle')} onPress={() => router.push('/practice/groups')} /> : null}
        <MenuRow icon={Plug} title={t('mobile.me.connections')} onPress={() => router.push('/connections')} />
        <MenuRow icon={Download} title={t('player.downloads.title')} onPress={() => router.push('/downloads')} />
        <MenuRow icon={Bell} title={t('notifications.settingsTitle')} onPress={() => router.push('/notification-settings')} />
        <MenuRow icon={ShieldCheck} title={t('mobile.me.privacy')} onPress={() => router.push('/privacy')} />
        <MenuRow icon={Languages} title={t('mobile.me.accessibility')} onPress={() => router.push('/accessibility')} />
      </MenuGroup>

      {others.length > 0 ? <MenuCard icon={ArrowLeftRight} title={others.length === 1 && others[0] ? t('mobile.me.switchRoleTo', { role: roleName(t, others[0].role) }) : t('mobile.me.switchRole')} onPress={() => setSheet('roles')} /> : null}

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

      <Sheet open={sheet === 'states'} onClose={() => setSheet(null)} title={t('mobile.states.title')}>
        <Text tone="muted">{t('mobile.states.intro')}</Text>
        <View accessibilityRole="radiogroup">
          {(states?.options ?? []).map((option) => (
            <ChoiceRow key={option.code} title={option.name} tile={option.code} selected={option.code === states?.current.code} onPress={() => void chooseState(option.code, option.name)} />
          ))}
        </View>
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
