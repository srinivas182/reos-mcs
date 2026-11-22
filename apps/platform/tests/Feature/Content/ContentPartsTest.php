<?php

declare(strict_types=1);

use App\Content\Extraction\Captions;
use App\Content\Extraction\TextExtractor;
use App\Content\Knowledge\Chunker;
use App\Content\Knowledge\KnowledgeSearch;
use App\Content\MediaKind;
use App\Content\Scanning\ClamAvScanner;
use App\Content\Scanning\ScanResult;
use App\Content\Video\MuxWebhooks;
use App\Models\KbPassage;

/** A minimal Office file: the given parts in a zip. */
function officeFile(array $parts): string
{
    $path = tempnam(sys_get_temp_dir(), 'office-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($parts as $name => $xml) {
        $zip->addFromString($name, $xml);
    }

    $zip->close();

    return $path;
}

function slideXml(string ...$paragraphs): string
{
    $body = implode('', array_map(static fn (string $p): string => '<a:p><a:r><a:t>'.htmlspecialchars($p).'</a:t></a:r></a:p>', $paragraphs));

    return '<?xml version="1.0"?><p:sld xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:sp><p:txBody>'.$body.'</p:txBody></p:sp></p:spTree></p:cSld></p:sld>';
}

describe('reading files (A5.3)', function () {
    it('reads a PDF page by page', function () {
        $sections = app(TextExtractor::class)->extract(base_path('tests/Fixtures/content/agency-slides.pdf'), 'pdf');

        expect($sections)->toHaveCount(3)
            ->and($sections[1]->page)->toBe(2)
            ->and($sections[1]->text)->toContain('Information About Brokerage Services')
            ->and($sections[2]->text)->toContain('intermediary');
    });

    it('reads PowerPoint slides in order, with speaker notes', function () {
        $path = officeFile([
            'ppt/slides/slide10.xml' => slideXml('Last slide'),
            'ppt/slides/slide2.xml' => slideXml('Intermediary', 'Written consent of both parties'),
            'ppt/slides/slide1.xml' => slideXml('Agency disclosure'),
            'ppt/notesSlides/notesSlide2.xml' => slideXml('Remind them about the IABS.'),
        ]);

        $sections = app(TextExtractor::class)->extract($path, 'pptx');

        expect(array_map(fn ($s) => $s->page, $sections))->toBe([1, 2, 3])
            ->and($sections[0]->text)->toBe('Agency disclosure')
            ->and($sections[1]->text)->toBe("Intermediary\nWritten consent of both parties\nRemind them about the IABS.")
            ->and($sections[2]->text)->toBe('Last slide');
    });

    it('reads Word with its headings', function () {
        $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $path = officeFile(['word/document.xml' => '<?xml version="1.0"?><w:document xmlns:w="'.$w.'"><w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>First contact</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Give the IABS </w:t></w:r><w:r><w:t>at the first meeting.</w:t></w:r></w:p>'
            .'</w:body></w:document>']);

        $sections = app(TextExtractor::class)->extract($path, 'docx');

        expect($sections)->toHaveCount(1)
            ->and($sections[0]->page)->toBeNull()
            ->and($sections[0]->text)->toBe("# First contact\nGive the IABS at the first meeting.");
    });

    it('refuses an XML entity attack', function () {
        $path = officeFile(['word/document.xml' => '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>&x;</w:t></w:r></w:p></w:body></w:document>']);

        $sections = rescue(fn () => app(TextExtractor::class)->extract($path, 'docx'), [], false);

        expect(json_encode($sections))->not->toContain('root:');
    });

    it('reads captions with the second each line starts', function () {
        $cues = Captions::cues((string) file_get_contents(base_path('tests/Fixtures/content/lesson.vtt')));

        expect($cues)->toHaveCount(4)
            ->and($cues[2])->toBe(['seconds' => 245, 'text' => 'So before you talk about price or terms, hand them the IABS form.']);
    });
});

describe('passages (A5.3)', function () {
    it('splits lesson text by heading, keeping each heading', function () {
        $drafts = app(Chunker::class)->text("Intro paragraph.\n\n# First contact\nGive the IABS.\n\nExplain who you represent.\n\n## Intermediary\nWritten consent.");

        expect(array_map(fn ($d) => [$d->heading, $d->content], $drafts))->toBe([
            [null, 'Intro paragraph.'],
            ['First contact', "Give the IABS.\n\nExplain who you represent."],
            ['Intermediary', 'Written consent.'],
        ]);
    });

    it('keeps passages short', function () {
        $long = str_repeat('Agency disclosure protects buyers and sellers. ', 120);
        $drafts = app(Chunker::class)->text($long);

        expect(count($drafts))->toBeGreaterThan(3);
        foreach ($drafts as $draft) {
            expect(mb_strlen($draft->content))->toBeLessThanOrEqual(Chunker::MAX);
        }
    });

    it('groups captions into windows that start at a cue', function () {
        $drafts = app(Chunker::class)->cues(Captions::cues((string) file_get_contents(base_path('tests/Fixtures/content/lesson.vtt'))), 5);

        expect(array_map(fn ($d) => $d->seconds, $drafts))->toBe([1, 245])
            ->and($drafts[1]->kind)->toBe(KbPassage::TRANSCRIPT)
            ->and($drafts[1]->content)->toContain('Information About Brokerage Services');
    });

    it('marks matching words in a snippet', function () {
        expect(KnowledgeSearch::snippet('Give the IABS form at first contact.', 'iabs contact'))
            ->toBe('Give the [[IABS]] form at first [[contact]].');
    });
});

describe('file checks (A5.3)', function () {
    it('knows a file by its first bytes, not its name', function () {
        expect(MediaKind::looksLike('pdf', '%PDF-1.7'))->toBeTrue()
            ->and(MediaKind::looksLike('pdf', "MZ\x90\x00"))->toBeFalse()
            ->and(MediaKind::looksLike('mp4', "\x00\x00\x00\x18ftypmp42"))->toBeTrue()
            ->and(MediaKind::looksLike('pptx', "PK\x03\x04rest"))->toBeTrue()
            ->and(MediaKind::looksLike('vtt', "\xEF\xBB\xBFWEBVTT\n"))->toBeTrue()
            ->and(MediaKind::looksLike('exe', 'MZ'))->toBeFalse();
    });

    it('reads ClamAV answers', function () {
        expect(ClamAvScanner::verdict('stream: OK'))->toBe(ScanResult::Clean)
            ->and(ClamAvScanner::verdict('stream: Eicar-Test-Signature FOUND'))->toBe(ScanResult::Infected)
            ->and(ClamAvScanner::verdict('INSTREAM size limit exceeded. ERROR'))->toBe(ScanResult::Unavailable);
    });

    it('checks Mux webhook signatures and their age', function () {
        $body = '{"type":"video.asset.ready"}';
        $signature = 't=1000,v1='.hash_hmac('sha256', '1000.'.$body, 'whsec');

        expect(MuxWebhooks::verify($body, $signature, 'whsec', 1100))->toBeTrue()
            ->and(MuxWebhooks::verify($body.' ', $signature, 'whsec', 1100))->toBeFalse()
            ->and(MuxWebhooks::verify($body, $signature, 'other', 1100))->toBeFalse()
            ->and(MuxWebhooks::verify($body, $signature, 'whsec', 1400))->toBeFalse();
    });
});
