import { fireEvent, render, screen } from '@testing-library/react-native';
import * as DocumentPicker from 'expo-document-picker';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import CertificateScreen from '@/app/licences/certificate';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { POST: jest.fn() };

jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() } }));
jest.mock('expo-document-picker', () => ({ getDocumentAsync: jest.fn() }));
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

beforeEach(() => jest.clearAllMocks());

describe('Adding a CE certificate on the phone (B10.3b)', () => {
  it('reads the picked certificate, lets the person check it, and saves it', async () => {
    jest.mocked(DocumentPicker.getDocumentAsync).mockResolvedValue({
      canceled: false,
      assets: [{ uri: 'file:///cache/legal-update.pdf', name: 'legal-update.pdf', mimeType: 'application/pdf', lastModified: 0, size: 2048 }],
    } as DocumentPicker.DocumentPickerResult);
    mockApi.POST.mockResolvedValueOnce(
      ok({ data: { upload: 'u1', reader: 'simulator', name: 'Pat Jordan', course: 'Legal Update II', hours: 4, completed_on: '2026-11-21', provider: 'Texas Realty Institute (demo)', confidence: {}, name_matches: false, states: ['TX'] } }),
    );
    mockApi.POST.mockResolvedValueOnce(ok({ data: { id: 1 } }));
    await render(wrap(<CertificateScreen />));

    fireEvent.press(screen.getByText('Read certificate'));
    expect(await screen.findByDisplayValue('Legal Update II')).toBeTruthy();
    expect(screen.getByText("This name doesn't match your account. Only add certificates that are yours.")).toBeTruthy();

    await fireEvent.changeText(screen.getByLabelText('Hours'), '3');
    fireEvent.press(screen.getByText('Save certificate'));
    expect(await screen.findByText('Certificate saved')).toBeTruthy();
    expect(mockApi.POST).toHaveBeenLastCalledWith('/me/certificates', {
      body: { upload: 'u1', state: 'TX', title: 'Legal Update II', hours: 3, completed_on: '2026-11-21', provider: 'Texas Realty Institute (demo)' },
    });
    expect(router.back).toHaveBeenCalled();
  });
});
