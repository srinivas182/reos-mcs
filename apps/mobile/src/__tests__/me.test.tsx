import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import AccessibilityScreen from '@/app/accessibility';
import MeScreen from '@/app/me';
import type { Me } from '@/lib/auth';
import { i18n } from '@/lib/i18n';
import { activeMembership, organisationsOf } from '@/lib/roles';
import { ToastProvider, usePreferences } from '@/ui';

const lonestar = { id: 2, name: 'Lone Star Realty', type: 'brokerage' as const };
const school = { id: 3, name: 'Texas Realty School', type: 'school' as const };

const james = (active: { role: 'agent' | 'instructor'; organisation_id: number } = { role: 'instructor', organisation_id: 3 }): Me =>
  ({
    id: 7,
    name: 'James Miller',
    email: null,
    phone: '+15125550142',
    roles: [
      { role: 'instructor', organisation: school, admin: false },
      { role: 'agent', organisation: lonestar, admin: false },
    ],
    active_role: active,
    permissions: [],
    two_step: { enabled: false, required: false },
  }) as unknown as Me;

const mockAuth = { user: james() as Me | null, switchRole: jest.fn().mockResolvedValue(true), signOut: jest.fn() };

jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() } }));
jest.mock('@/lib/auth', () => ({ useAuth: () => mockAuth }));

const renderScreen = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  mockAuth.user = james();
  jest.clearAllMocks();
});

describe('Me (journey screen 16)', () => {
  it('shows the person, their role and organisation, and the settings', async () => {
    await renderScreen(<MeScreen />);

    expect(screen.getByRole('header', { name: 'James Miller' })).toBeOnTheScreen();
    expect(screen.getByText('Instructor · ')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Texas Realty School' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Privacy and my data' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Language and accessibility' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Switch role · Licensed agent' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Sign out' })).toBeOnTheScreen();
  });

  it('switches organisation from the sheet, with Undo', async () => {
    await renderScreen(<MeScreen />);

    await fireEvent.press(screen.getByRole('button', { name: 'Texas Realty School' }));
    expect(screen.getByText('Each one sees only what you share with it.')).toBeOnTheScreen();
    expect(screen.getByRole('radio', { name: /Texas Realty School/ })).toBeChecked();

    await fireEvent.press(screen.getByRole('radio', { name: /Lone Star Realty/ }));
    await waitFor(() => expect(mockAuth.switchRole).toHaveBeenCalledWith('agent', 2));
    expect(await screen.findByText("You're now working with Lone Star Realty.")).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Undo' }));
    await waitFor(() => expect(mockAuth.switchRole).toHaveBeenLastCalledWith('instructor', 3));
  });

  it('switches role from the Switch role card', async () => {
    await renderScreen(<MeScreen />);

    await fireEvent.press(screen.getByRole('button', { name: 'Switch role · Licensed agent' }));
    await fireEvent.press(screen.getByRole('radio', { name: /Licensed agent/ }));
    await waitFor(() => expect(mockAuth.switchRole).toHaveBeenCalledWith('agent', 2));
  });

  it('has no Switch role card with one role, and asks admins for two-step', async () => {
    mockAuth.user = {
      ...james(),
      roles: [{ role: 'broker', organisation: lonestar, admin: true }],
      active_role: { role: 'broker', organisation_id: 2 },
      two_step: { enabled: false, required: true },
    } as unknown as Me;
    await renderScreen(<MeScreen />);

    expect(screen.queryByRole('button', { name: /Switch role/ })).toBeNull();
    expect(screen.getByText('Set up two-step to use Broker')).toBeOnTheScreen();
  });

  it('speaks Spanish', async () => {
    await i18n.changeLanguage('es');
    await renderScreen(<MeScreen />);
    expect(screen.getByRole('button', { name: 'Privacidad y mis datos' })).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    await i18n.changeLanguage('en');
  });
});

describe('roles helpers', () => {
  it('finds the role in use and lists each organisation once', () => {
    expect(activeMembership(james({ role: 'agent', organisation_id: 2 }))?.organisation.name).toBe('Lone Star Realty');
    expect(activeMembership(null)).toBeNull();
    expect(organisationsOf(james()).map((o) => o.name)).toEqual(['Texas Realty School', 'Lone Star Realty']);
  });
});

describe('Language and accessibility (journey screen 192)', () => {
  function Probe() {
    const preferences = usePreferences();

    return <AccessibilityScreen key={`${preferences.locale}-${preferences.textSize}-${preferences.appearance}`} />;
  }

  it('changes language, text size and high contrast', async () => {
    await renderScreen(<Probe />);

    await fireEvent.press(screen.getByRole('radio', { name: 'Español' }));
    await waitFor(() => expect(screen.getByText('Idioma y accesibilidad')).toBeOnTheScreen());

    const size = screen.getByRole('adjustable', { name: 'Tamaño del texto' });
    await fireEvent(size, 'accessibilityAction', { nativeEvent: { actionName: 'increment' } });
    await waitFor(() => expect(screen.getByRole('adjustable', { name: 'Tamaño del texto' })).toHaveAccessibilityValue({ text: 'Grande' }));

    await fireEvent(screen.getByRole('switch', { name: 'Alto contraste' }), 'valueChange', true);
    await waitFor(() => expect(screen.getByRole('switch', { name: 'Alto contraste' })).toHaveProp('value', true));

    await fireEvent.press(screen.getByRole('radio', { name: 'English' }));
  });
});
