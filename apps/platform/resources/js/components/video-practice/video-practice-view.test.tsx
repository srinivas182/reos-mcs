import { act, render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { reviewFixture } from './fixtures';
import {
    ReviewView,
    VideoRecentList,
    VideoRecorder,
} from './video-practice-view';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

class FakeMediaRecorder {
    state = 'inactive';
    mimeType = 'video/webm';
    ondataavailable: ((e: { data: Blob }) => void) | null = null;
    onstop: (() => void) | null = null;
    start() {
        this.state = 'recording';
    }
    stop() {
        this.state = 'inactive';
        this.ondataavailable?.({ data: new Blob(['x']) });
        this.onstop?.();
    }
}

describe('Video practice (A14.1b)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        Reflect.deleteProperty(navigator, 'mediaDevices');
        vi.useRealTimers();
    });

    it('says when the browser has no camera', () => {
        render(
            <VideoRecorder
                kinds={['listing_pitch']}
                maxSeconds={120}
                busy={false}
                onReview={vi.fn()}
            />,
        );
        expect(
            screen.getByText(
                "The camera isn't available in this browser. Try the REOS phone app.",
            ),
        ).toBeInTheDocument();
    });

    it('records, lets the agent correct the words, and asks for the review with the length', async () => {
        const track = { stop: vi.fn() };
        vi.stubGlobal('MediaRecorder', FakeMediaRecorder);
        Object.defineProperty(navigator, 'mediaDevices', {
            configurable: true,
            value: {
                getUserMedia: vi
                    .fn()
                    .mockResolvedValue({ getTracks: () => [track] }),
            },
        });
        vi.stubGlobal('URL', {
            ...URL,
            createObjectURL: () => 'blob:rec',
            revokeObjectURL: vi.fn(),
        });
        const now = vi.spyOn(Date, 'now').mockReturnValue(1_000_000);
        const onReview = vi.fn();
        render(
            <VideoRecorder
                kinds={['listing_pitch', 'video_intro']}
                maxSeconds={120}
                busy={false}
                onReview={onReview}
            />,
        );

        await userEvent.click(
            screen.getByRole('radio', { name: 'Video intro' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Start recording' }),
        );
        now.mockReturnValue(1_045_000);
        await act(async () => {
            await userEvent.click(screen.getByRole('button', { name: 'Stop' }));
        });

        expect(track.stop).toHaveBeenCalled();
        expect(
            screen.getByText(
                'No words were recognized. Type what you said, or record again.',
            ),
        ).toBeInTheDocument();
        await userEvent.type(
            screen.getByLabelText('What you said'),
            'Hi, I am Maria Garcia with Lone Star Realty.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Get my review' }),
        );
        expect(onReview).toHaveBeenCalledWith(
            'video_intro',
            'Hi, I am Maria Garcia with Lone Star Realty.',
            45,
        );
        now.mockRestore();
    });

    it('shows the pace, fillers, three fixes and what to fix before posting', () => {
        render(
            <ReviewView
                review={reviewFixture({
                    compliance: [{ code: 'claims', phrase: 'I guarantee' }],
                })}
                lessonHref={(l) => `/learn?topic=${l}`}
            />,
        );
        expect(screen.getByText('66 out of 100')).toBeInTheDocument();
        expect(
            screen.getByText('A little fast. Aim for 130 to 160.'),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: /^Lesson:/ })).toHaveLength(
            3,
        );
        expect(
            screen.getByText('Misleading claims: “I guarantee”'),
        ).toBeInTheDocument();
    });

    it('gives an empty list a way to start', () => {
        render(<VideoRecentList recent={[]} href={(id) => id} />);
        expect(screen.getByText('No reviews yet')).toBeInTheDocument();
    });
});
