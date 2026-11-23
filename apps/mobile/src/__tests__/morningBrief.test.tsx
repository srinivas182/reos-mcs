import { act, fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Linking } from 'react-native';

import BriefScreen from '@/app/agent/brief';
import { type Brief, type BriefSettings, matchCommand } from '@/lib/agent';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn() };
const voice = jest.requireMock<{ __spoken: string[]; speak: jest.Mock }>('expo-speech');
const speech = jest.requireMock<{ __emit: (name: string, event: unknown) => void; ExpoSpeechRecognitionModule: { start: jest.Mock } }>('expo-speech-recognition');

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const brief: Brief = {
  date: '2026-11-24',
  sections: [{ key: 'appointments', count: 1, text: 'One appointment: Showing at Oak Ridge at 10:00 AM.' }],
  first_call: { name: 'Ana Garcia', phone: '+1 512 555 0142', contact: 'c-ana' },
  commands: [
    { kind: 'call', label: 'Call Ana Garcia', phone: '+1 512 555 0142', contact: 'c-ana', deal: null },
    { kind: 'deal', label: 'Open the Oak Ridge deal', phone: null, contact: null, deal: 'd-oak' },
    { kind: 'today', label: 'Open Today', phone: null, contact: null, deal: null },
  ],
  script: ['Good morning, James.', 'One appointment: Showing at Oak Ridge at 10:00 AM.', 'Call Ana Garcia first.'],
};
const settings: BriefSettings = { enabled: true, at: '07:00', timezone: 'America/Chicago', sections: ['todos', 'appointments', 'deadlines', 'leads', 'ce'] };

beforeEach(() => {
  jest.clearAllMocks();
  voice.__spoken.length = 0;
  mockApi.GET.mockImplementation(async (path: string) => ok({ data: path === '/agent/brief/settings' ? settings : brief }));
});

describe('The morning brief on the phone (A11.1c: screen 184)', () => {
  it('matches what the agent says to a command', () => {
    expect(matchCommand('call Ana', brief.commands)?.kind).toBe('call');
    expect(matchCommand('open the Oak Ridge deal please', brief.commands)?.kind).toBe('deal');
    expect(matchCommand('what is the weather', brief.commands)).toBeNull();
  });

  it('reads the brief aloud, then listens for a command and acts on it', async () => {
    const open = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    await render(wrap(<BriefScreen />));

    expect(await screen.findByText('Call Ana Garcia first.')).toBeTruthy();
    await fireEvent.press(screen.getByText('Play'));
    await waitFor(() => expect(voice.__spoken).toEqual(brief.script));
    await waitFor(() => expect(speech.ExpoSpeechRecognitionModule.start).toHaveBeenCalled());

    await act(async () => speech.__emit('result', { isFinal: true, results: [{ transcript: 'call Ana' }] }));
    expect(open).toHaveBeenCalledWith('tel:+15125550142');
    expect(screen.getByText('Heard: “call Ana”')).toBeTruthy();

    await fireEvent.press(screen.getByText('Open the Oak Ridge deal'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/deals/[uuid]', params: { uuid: 'd-oak' } });
  });

  it('opens from "Hey Siri/Google, play my REOS brief"', () => {
    const plugin = jest.requireActual<{ swift: () => string; shortcutsXml: (pkg: string) => string }>('../../plugins/with-quiz-shortcut');
    expect(plugin.swift()).toContain('"Play my \\(.applicationName) brief"');
    expect(plugin.swift()).toContain('reos://agent/brief');
    expect(plugin.shortcutsXml('com.reos.app')).toContain('android:data="reos://agent/brief"');
  });

  it('changes the time and what it covers', async () => {
    mockApi.PUT.mockImplementation(async (_path: string, { body }: { body: BriefSettings }) => ok({ data: body }));
    await render(wrap(<BriefScreen />));

    await fireEvent.press(await screen.findByLabelText('6:30 AM'));
    await waitFor(() => expect(mockApi.PUT).toHaveBeenCalledWith('/agent/brief/settings', { body: expect.objectContaining({ at: '06:30' }) }));
    await fireEvent(screen.getByLabelText('CE progress'), 'valueChange', false);
    await waitFor(() =>
      expect(mockApi.PUT).toHaveBeenLastCalledWith('/agent/brief/settings', { body: expect.objectContaining({ sections: ['todos', 'appointments', 'deadlines', 'leads'] }) }),
    );
  });
});
