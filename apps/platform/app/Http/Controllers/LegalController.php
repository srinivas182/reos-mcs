<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Privacy\ConsentPurpose;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The terms and the privacy notice (A6.3), public at /terms and /privacy in
 * the person's language, with the version people agree to (their consent
 * records keep it). The words live in resources/legal/<language>/.
 */
final class LegalController extends Controller
{
    public function terms(): Response
    {
        return $this->show('terms', ConsentPurpose::Terms);
    }

    public function privacy(): Response
    {
        return $this->show('privacy', ConsentPurpose::PrivacyNotice);
    }

    private function show(string $document, ConsentPurpose $purpose): Response
    {
        $language = Locale::current()->value;
        $markdown = (string) file_get_contents(resource_path("legal/{$language}/{$document}.md"));

        return Inertia::render('public/legal', [
            'document' => $document,
            'version' => $purpose->version(),
            ...self::parse($markdown),
        ]);
    }

    /**
     * The document's title, opening paragraphs and sections (plain text only).
     *
     * @return array{title: string, intro: list<string>, sections: list<array{heading: string, paragraphs: list<string>}>}
     */
    public static function parse(string $markdown): array
    {
        $title = '';
        $intro = [];
        $headings = [];
        $bodies = [];

        foreach (preg_split('/\n{2,}/', trim($markdown)) ?: [] as $block) {
            $block = trim($block);

            if (str_starts_with($block, '# ')) {
                $title = substr($block, 2);
            } elseif (str_starts_with($block, '## ')) {
                $headings[] = substr($block, 3);
                $bodies[] = [];
            } elseif ($headings === []) {
                $intro[] = $block;
            } else {
                $bodies[count($bodies) - 1][] = $block;
            }
        }

        $sections = array_map(static fn (string $heading, array $paragraphs): array => ['heading' => $heading, 'paragraphs' => $paragraphs], $headings, $bodies);

        return ['title' => $title, 'intro' => $intro, 'sections' => $sections];
    }
}
