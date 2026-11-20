import { UnistylesRuntime } from 'react-native-unistyles';

import { i18n } from '@/lib/i18n';
import { initPreferences, load, setPreferences } from '@/ui/preferences';

describe('preferences', () => {
  afterEach(() => {
    setPreferences({ appearance: 'system', textSize: 'standard', locale: 'device' });
    jest.restoreAllMocks();
  });

  it('starts by following the phone: system theme, standard text, phone language', () => {
    initPreferences('en');
    expect(load()).toEqual({ appearance: 'system', textSize: 'standard', locale: 'device' });
  });

  it('applies a chosen theme through Unistyles and stops following the phone', () => {
    const adaptive = jest.spyOn(UnistylesRuntime, 'setAdaptiveThemes');
    const setTheme = jest.spyOn(UnistylesRuntime, 'setTheme');

    setPreferences({ appearance: 'contrast' });

    expect(adaptive).toHaveBeenLastCalledWith(false);
    expect(setTheme).toHaveBeenLastCalledWith('contrast');
  });

  it('scales every theme for the chosen text size', () => {
    const update = jest.spyOn(UnistylesRuntime, 'updateTheme');

    setPreferences({ textSize: 'largest' });

    expect(update.mock.calls.map(([name]) => name)).toEqual(['light', 'dark', 'contrast']);
  });

  it('switches language and remembers every choice on the device', async () => {
    setPreferences({ locale: 'es' });
    expect(i18n.language).toBe('es');

    initPreferences('en');
    expect(load().locale).toBe('es');
    expect(i18n.language).toBe('es');

    setPreferences({ locale: 'device' });
    expect(i18n.language).toBe('en');
  });
});
