import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { files, hits, kinds } from '@/components/content-fixtures';
import { CourseStatusBadge } from '@/components/course-status-badge';
import { KnowledgeResults } from '@/components/knowledge-results';
import { LessonFiles } from '@/components/lesson-files';
import { clock, kindFor, marks } from '@/lib/content';
import { i18n } from '@/lib/i18n';

const reload = vi.fn();

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router: {
        reload: (...args: unknown[]) => reload(...args),
        delete: vi.fn(),
    },
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

afterEach(() => {
    vi.unstubAllGlobals();
    reload.mockReset();
});

describe('content helpers', () => {
    it('splits snippets into matches', () => {
        expect(marks('Give the [[IABS]] form at [[first]] contact')).toEqual([
            { text: 'Give the ', match: false },
            { text: 'IABS', match: true },
            { text: ' form at ', match: false },
            { text: 'first', match: true },
            { text: ' contact', match: false },
        ]);
    });

    it('writes video times and finds the kind from a name', () => {
        expect(clock(245)).toBe('4:05');
        expect(clock(3729)).toBe('1:02:09');
        expect(kindFor('lesson.VTT', kinds)).toBe('captions');
        expect(kindFor('run.exe', kinds)).toBeNull();
    });
});

describe('CourseStatusBadge', () => {
    it('says a CE course without approval is hidden', () => {
        render(<CourseStatusBadge status="draft" noApproval />);
        expect(screen.getByText('Hidden: no approval')).toBeInTheDocument();
    });
});

describe('KnowledgeResults', () => {
    it('shows where each passage opens, with the matching words marked', () => {
        render(<KnowledgeResults query="IABS" results={hits} />);

        const items = within(
            screen.getByRole('list', { name: 'Results for IABS' }),
        ).getAllByRole('listitem');
        expect(items).toHaveLength(3);
        expect(items[0]).toHaveTextContent('Page 6');
        expect(items[1]).toHaveTextContent('At 4:05');
        expect(items[2]).toHaveTextContent('Primer contacto · Spanish');
        expect(items[2]).toHaveTextContent('Found by meaning');
        expect(
            within(items[0]!).getByText('Information About Brokerage Services')
                .tagName,
        ).toBe('MARK');
        expect(
            within(items[0]!).getByRole('link', { name: 'Agency disclosure' }),
        ).toHaveAttribute('href', '/admin/content/lessons/7');
    });

    it('teaches what to try when nothing is found', () => {
        render(<KnowledgeResults query="mineral rights" results={[]} />);
        expect(
            screen.getByText('Nothing found for “mineral rights”'),
        ).toBeInTheDocument();
    });
});

describe('LessonFiles', () => {
    it('lists each file with its status and why one was refused', () => {
        render(<LessonFiles lessonId={7} files={files} kinds={kinds} />);

        expect(screen.getByText('agency-disclosure.mp4')).toBeInTheDocument();
        expect(screen.getByText(/14 pages/)).toBeInTheDocument();
        expect(screen.getByText(/12:34/)).toBeInTheDocument();
        expect(screen.getByText('Checking')).toBeInTheDocument();
        expect(
            screen.getByText(
                "The file isn't what its name says. Upload the original file.",
            ),
        ).toBeInTheDocument();
    });

    it('uploads straight to the signed link, then says it is done', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(
                    JSON.stringify({
                        id: 21,
                        upload: {
                            method: 'PUT',
                            url: 'https://s3.example/put',
                            headers: { 'Content-Type': 'text/vtt' },
                        },
                    }),
                    { status: 201 },
                ),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ id: 21, status: 'processing' }), {
                    status: 200,
                }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const sent: { url: string; headers: Record<string, string> }[] = [];
        class FakeXhr {
            upload: { onprogress: ((e: ProgressEvent) => void) | null } = {
                onprogress: null,
            };
            onload: (() => void) | null = null;
            onerror: (() => void) | null = null;
            status = 200;
            private entry = { url: '', headers: {} as Record<string, string> };
            open(_method: string, url: string) {
                this.entry.url = url;
            }
            setRequestHeader(name: string, value: string) {
                this.entry.headers[name] = value;
            }
            send() {
                sent.push(this.entry);
                this.onload?.();
            }
        }
        vi.stubGlobal('XMLHttpRequest', FakeXhr);

        render(<LessonFiles lessonId={7} files={[]} kinds={kinds} />);
        await userEvent.selectOptions(
            screen.getByLabelText('Type of file'),
            'captions',
        );
        const input = document.querySelector(
            'input[type=file]',
        ) as HTMLInputElement;
        await userEvent.upload(
            input,
            new File(['WEBVTT'], 'lesson.vtt', { type: 'text/vtt' }),
        );

        await vi.waitFor(() => expect(reload).toHaveBeenCalled());
        expect(fetchMock.mock.calls[0]![0]).toBe(
            '/admin/content/lessons/7/files',
        );
        expect(JSON.parse(fetchMock.mock.calls[0]![1].body as string)).toEqual({
            kind: 'captions',
            name: 'lesson.vtt',
            size: 6,
        });
        expect(sent).toEqual([
            {
                url: 'https://s3.example/put',
                headers: { 'Content-Type': 'text/vtt' },
            },
        ]);
        expect(fetchMock.mock.calls[1]![0]).toBe(
            '/admin/content/files/21/complete',
        );
    });

    it('shows why an upload was refused', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(
                    JSON.stringify({
                        errors: {
                            name: ['Choose a file of this type: vtt.'],
                        },
                    }),
                    { status: 422 },
                ),
            ),
        );

        render(<LessonFiles lessonId={7} files={[]} kinds={kinds} />);
        await userEvent.upload(
            document.querySelector('input[type=file]') as HTMLInputElement,
            new File(['x'], 'clip.mp4', { type: 'video/mp4' }),
        );

        expect(await screen.findByRole('alert')).toHaveTextContent(
            'Choose a file of this type: vtt.',
        );
    });
});
