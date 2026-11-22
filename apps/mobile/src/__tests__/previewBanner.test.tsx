import { render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import { PreviewBanner } from '@/features/preview/PreviewBanner';
import { i18n } from '@/lib/i18n';

const mockConfig = jest.fn();
jest.mock('@/lib/monitoring', () => ({ appConfig: () => mockConfig() }));

const wrap = () =>
  render(
    <I18nextProvider i18n={i18n}>
      <PreviewBanner />
    </I18nextProvider>,
  );

describe('Preview banner (P7.0)', () => {
  it('says it is a preview, with the one code to use', async () => {
    mockConfig.mockResolvedValue({ preview: { code: '123456' } });
    await wrap();

    expect(await screen.findByText(/Any code REOS asks for is 123456/)).toBeOnTheScreen();
  });

  it('shows nothing outside the preview', async () => {
    mockConfig.mockResolvedValue({ preview: null });
    await wrap();

    expect(screen.queryByText(/Preview for testing/)).toBeNull();
  });
});
