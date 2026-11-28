<?php

declare(strict_types=1);

namespace App\Mls;

use App\Compliance\TexasAdRules;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The agent's digital card and what search and answer engines read from
 * the public page (B14.1, ADR 0064): a vCard to save the contact, a QR code
 * of the page link, plain-language FAQs, and schema.org data
 * (RealEstateAgent, its brokerage, FAQPage) — all from the published page,
 * so the card says nothing the page doesn't.
 *
 * @phpstan-import-type PublicPage from AgentPages
 *
 * @phpstan-type Faq array{question: string, answer: string}
 */
final class AgentCards
{
    /**
     * A vCard 3.0 (the version every phone reads) of what the page shows.
     *
     * @param  PublicPage  $page
     */
    public function vcard(array $page): string
    {
        [$first, $last] = self::split($page['name']);
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:'.self::escape($last).';'.self::escape($first).';;;',
            'FN:'.self::escape($page['name']),
            'TITLE:'.self::escape(self::tr('cards.vcardTitle', [], $page['language'])),
        ];

        if ($page['brokerage'] !== null) {
            $lines[] = 'ORG:'.self::escape($page['brokerage']);
        }

        if ($page['phone'] !== null) {
            $lines[] = 'TEL;TYPE=CELL,VOICE:'.self::escape($page['phone']);
        }

        if ($page['email'] !== null) {
            $lines[] = 'EMAIL;TYPE=INTERNET:'.self::escape($page['email']);
        }

        foreach ($page['licences'] as $licence) {
            $lines[] = 'NOTE:'.self::escape(self::tr('listings.public.license', ['state' => $licence['state'], 'number' => $licence['number']], $page['language']));
        }

        $lines[] = 'URL:'.self::escape($page['url']);
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines)."\r\n";
    }

    /** The page link as a QR code, in SVG. */
    public function qr(string $url, int $size = 320): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd)))->writeString($url);
    }

    /**
     * Plain-language questions and answers about the agent, from what the
     * page shows — what people ask search and AI assistants.
     *
     * @param  PublicPage  $page
     * @return list<Faq>
     */
    public function faqs(array $page): array
    {
        $lang = $page['language'];
        $name = $page['name'];
        $licence = $page['licences'][0] ?? null;
        $faqs = [];

        $faqs[] = [
            'question' => self::tr('cards.faq.whoQ', ['name' => $name], $lang),
            'answer' => $licence === null
                ? self::tr('cards.faq.whoA', ['name' => $name, 'brokerage' => $page['brokerage'] ?? ''], $lang)
                : self::tr('cards.faq.whoLicensedA', ['name' => $name, 'state' => $licence['state'], 'number' => $licence['number'], 'brokerage' => $page['brokerage'] ?? ''], $lang),
        ];

        if ($page['areas'] !== []) {
            $faqs[] = ['question' => self::tr('cards.faq.areasQ', ['name' => $name], $lang), 'answer' => self::tr('cards.faq.areasA', ['name' => $name, 'areas' => implode(', ', $page['areas'])], $lang)];
        }

        $contact = array_values(array_filter([$page['phone'], $page['email']]));
        $faqs[] = [
            'question' => self::tr('cards.faq.contactQ', ['name' => $name], $lang),
            'answer' => $contact === [] ? self::tr('cards.faq.contactPageA', ['name' => $name], $lang) : self::tr('cards.faq.contactA', ['name' => $name, 'contact' => implode(' · ', $contact)], $lang),
        ];

        if ($page['listings'] !== []) {
            $faqs[] = ['question' => self::tr('cards.faq.listingsQ', ['name' => $name], $lang), 'answer' => self::tr('cards.faq.listingsA', ['name' => $name, 'count' => count($page['listings'])], $lang)];
        }

        $faqs[] = ['question' => self::tr('cards.faq.noticesQ', [], $lang), 'answer' => self::tr('cards.faq.noticesA', ['iabs' => TexasAdRules::IABS, 'cpn' => TexasAdRules::CPN], $lang)];

        return $faqs;
    }

    /**
     * schema.org data for search and answer engines: the agent, their
     * brokerage, and the FAQs.
     *
     * @param  PublicPage  $page
     * @param  list<Faq>  $faqs
     * @return list<array<string, mixed>>
     */
    public function schema(array $page, array $faqs): array
    {
        $agent = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateAgent',
            '@id' => $page['url'].'#agent',
            'name' => $page['name'],
            'url' => $page['url'],
            'description' => $page['bio'],
            'telephone' => $page['phone'],
            'email' => $page['email'],
            'areaServed' => $page['areas'] === [] ? null : array_map(static fn (string $a): array => ['@type' => 'Place', 'name' => $a], $page['areas']),
            'knowsLanguage' => $page['language'] === 'es' ? ['es', 'en'] : ['en'],
            'parentOrganization' => $page['brokerage'] === null ? null : ['@type' => 'RealEstateAgent', 'name' => $page['brokerage']],
            'hasCredential' => $page['licences'] === [] ? null : array_map(static fn (array $l): array => ['@type' => 'EducationalOccupationalCredential', 'credentialCategory' => 'license', 'name' => $l['state'].' real estate license '.$l['number'], 'recognizedBy' => ['@type' => 'GovernmentOrganization', 'name' => $l['state'] === 'TX' ? 'Texas Real Estate Commission' : $l['state']]], $page['licences']),
            'aggregateRating' => $page['peer_rating'] === null ? null : ['@type' => 'AggregateRating', 'ratingValue' => $page['peer_rating']['average'], 'ratingCount' => $page['peer_rating']['count'], 'bestRating' => 5],
        ], static fn (mixed $v): bool => $v !== null);

        return [$agent, [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $f): array => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]], $faqs),
        ]];
    }

    /** @return array{0: string, 1: string} */
    private static function split(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /** vCard text escaping (RFC 2426). */
    private static function escape(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ',', ';'], ['\\\\', '\\n', '', '\\,', '\;'], $value);
    }

    /** @param array<string, string|int> $replace */
    private static function tr(string $key, array $replace, string $language): string
    {
        $value = __($key, $replace, $language);

        return is_string($value) ? $value : $key;
    }
}
