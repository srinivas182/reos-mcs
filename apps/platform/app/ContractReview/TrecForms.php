<?php

declare(strict_types=1);

namespace App\ContractReview;

/**
 * TREC promulgated forms REOS reads (A12.2, ADR 0048), starting with the
 * One to Four Family Residential Contract (Resale), form 20-18, and the
 * Third Party Financing Addendum. A form is recognized by its title and
 * "TREC NO." line; its key terms are read with the form's own wording, so
 * each comes with the page it's on. Anything else gets a general review.
 */
final class TrecForms
{
    /** Contracts REOS knows, by title. */
    public const FORMS = [
        'ONE TO FOUR FAMILY RESIDENTIAL CONTRACT (RESALE)' => '20-18',
    ];

    /** Addenda REOS knows, by title. */
    public const ADDENDA = [
        'third_party_financing' => 'THIRD PARTY FINANCING ADDENDUM',
        'hoa' => 'ADDENDUM FOR PROPERTY SUBJECT TO MANDATORY MEMBERSHIP IN A PROPERTY OWNERS ASSOCIATION',
        'sale_of_other_property' => 'ADDENDUM FOR SALE OF OTHER PROPERTY BY BUYER',
        'seller_financing' => 'SELLER FINANCING ADDENDUM',
        'lead_paint' => 'ADDENDUM FOR SELLER\'S DISCLOSURE OF INFORMATION ON LEAD-BASED PAINT',
    ];

    private const MONEY = '\$\s*([\d,]+(?:\.\d{2})?)';

    private const DATE = '([A-Z][a-z]+\.?\s+\d{1,2},?\s+\d{4})';

    /** The key terms of form 20-18 and its financing addendum: code => pattern (first group is the value). */
    private const TERMS = [
        'seller' => '/PARTIES:.*?contract are\s+(.+?)\s*\(Seller\)/is',
        'buyer' => '/\(Seller\)\s*and\s+(.+?)\s*\(Buyer\)/is',
        'property' => '/known as\s+(.+?)\s*\(address\/zip code\)/is',
        'cash_portion' => '/Cash portion of Sales Price[^$]*'.self::MONEY.'/is',
        'financing' => '/Sum of all financing[^$]*'.self::MONEY.'/is',
        'sales_price' => '/Sales Price \(Sum of A and B\)[^$]*'.self::MONEY.'/is',
        'escrow_agent' => '/deliver to\s+(.+?)\s*\(Escrow Agent\)/is',
        'earnest_money' => '/'.self::MONEY.'\s+as earnest money/is',
        'option_fee' => '/'.self::MONEY.'\s+as the Option Fee/is',
        'earnest_days' => '/Within\s+(\d+)\s+days after the Effective Date,\s*Buyer must deliver/is',
        'option_days' => '/within\s+(\d+)\s+days after the Effective Date of this contract\s*\(Option Period\)/is',
        'survey_days' => '/Within\s+(\d+)\s+days after the Effective Date of this contract,\s*Seller shall furnish/is',
        'financing_days' => '/Buyer Approval.{0,300}?within\s+(\d+)\s+days after the Effective Date/is',
        'closing_date' => '/closing of the sale will be on or before\s+'.self::DATE.'/s',
        'possession' => '/possession of the Property in its present or required condition\s+(upon closing and funding|according to a temporary residential lease[^.]*)/is',
        'special_provisions' => '/SPECIAL PROVISIONS:\s*(.+?)(?=\s*(?:\d{1,2}\.\s+[A-Z][A-Z ]{4,}:|TREC NO\.|$))/s',
        'effective_date' => '/EXECUTED the\s+(\d{1,2}(?:st|nd|rd|th)?\s+day of\s+[A-Z][a-z]+,?\s*\d{4})\s*\(Effective Date\)/is',
    ];

    /**
     * Read the form, its addenda and key terms from the pages' text.
     *
     * @param  array<int, string>  $pages  page number => text
     */
    public static function read(array $pages): ContractReading
    {
        $flat = array_map(static fn (string $t): string => trim((string) preg_replace('/\s+/u', ' ', $t)), $pages);
        $all = implode(' ', $flat);
        $upper = mb_strtoupper($all);
        $form = null;

        foreach (self::FORMS as $title => $number) {
            if (str_contains($upper, $title) && preg_match('/TREC NO\.\s*'.preg_quote($number, '/').'/', $all) === 1) {
                $form = $number;
            }
        }

        $terms = [];

        foreach (self::TERMS as $code => $pattern) {
            foreach ($flat as $page => $text) {
                if (preg_match($pattern, $text, $m) === 1 && trim($m[1]) !== '') {
                    $terms[$code] = ['code' => $code, 'value' => mb_substr(trim($m[1]), 0, 600), 'page' => $page];
                    break;
                }
            }
        }

        // An addendum is there when a page starts with its title (the contract also mentions them by name).
        $starts = array_map(static fn (string $t): string => mb_strtoupper(mb_substr($t, 0, 200)), $flat);
        $addenda = array_keys(array_filter(self::ADDENDA, static fn (string $title): bool => array_filter($starts, static fn (string $s): bool => str_starts_with($s, $title)) !== []));

        return new ContractReading($form, $terms, $addenda);
    }

    /** "$440,000.00" → 440000.0 */
    public static function amount(?string $value): ?float
    {
        if ($value === null || preg_match('/^[\d,]+(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        return (float) str_replace(',', '', $value);
    }
}
