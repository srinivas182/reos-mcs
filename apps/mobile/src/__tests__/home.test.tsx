import { render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import HomeScreen from '@/app/index';
import { i18n } from '@/lib/i18n';

jest.mock('@/lib/api', () => ({
  fetchHealth: jest.fn().mockResolvedValue({ status: 'ok', app: 'REOS', version: '0.1.0-dev', time: 'now' }),
}));

const renderHome = () =>
  render(
    <I18nextProvider i18n={i18n}>
      <HomeScreen />
    </I18nextProvider>,
  );

describe('HomeScreen', () => {
  afterEach(() => i18n.changeLanguage('en'));

  it('shows the API connection in English', async () => {
    renderHome();
    await waitFor(() => expect(screen.getByText('Connected to REOS · version 0.1.0-dev')).toBeOnTheScreen());
    expect(screen.getByRole('header', { name: 'REOS' })).toBeOnTheScreen();
  });

  it('speaks Spanish when the phone does', async () => {
    await i18n.changeLanguage('es');
    renderHome();
    await waitFor(() => expect(screen.getByText('Conectado a REOS · versión 0.1.0-dev')).toBeOnTheScreen());
  });
});
