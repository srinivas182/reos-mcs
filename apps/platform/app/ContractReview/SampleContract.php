<?php

declare(strict_types=1);

namespace App\ContractReview;

use App\Support\SimplePdf;

/**
 * A sample, filled-in TREC One to Four Family Residential Contract
 * (Resale), form 20-18, with its Third Party Financing Addendum (A12.2):
 * for demos and tests, so a review can be tried without a real contract.
 * Only the lines a review reads are written out; the names are fictional.
 */
final class SampleContract
{
    /**
     * @param  array{price?: string, cash?: string, financing?: string, closing?: string, effective?: string, special?: string}  $over
     * @return list<string>
     */
    public static function pages(array $over = []): array
    {
        $price = $over['price'] ?? '440,000.00';
        $cash = $over['cash'] ?? '88,000.00';
        $financing = $over['financing'] ?? '352,000.00';
        $closing = $over['closing'] ?? 'January 9, 2027';
        $effective = $over['effective'] ?? '24th day of November, 2026';
        $special = $over['special'] ?? 'Seller shall indemnify and hold harmless Buyer from any claim arising from the pool equipment.';

        return [
            "PROMULGATED BY THE TEXAS REAL ESTATE COMMISSION (TREC)\nONE TO FOUR FAMILY RESIDENTIAL CONTRACT (RESALE)\n1. PARTIES: The parties to this contract are Maria Ortega and Luis Ortega (Seller) and Ana Lopez (Buyer).\n2. PROPERTY: Lot 14, Block 3, Pecan Grove Addition, City of Austin, County of Travis, Texas, known as 1420 Pecan St, Austin, TX 78704 (address/zip code).\n3. SALES PRICE:\nA. Cash portion of Sales Price payable by Buyer at closing: $ {$cash}\nB. Sum of all financing described in the attached Third Party Financing Addendum: $ {$financing}\nC. Sales Price (Sum of A and B): $ {$price}\nTREC NO. 20-18",
            "5. EARNEST MONEY AND TERMINATION OPTION:\nA. DELIVERY OF EARNEST MONEY AND OPTION FEE: Within 3 days after the Effective Date, Buyer must deliver to Heritage Title of Austin (Escrow Agent) at 401 Congress Ave, Austin, $ 5,000.00 as earnest money and $ 300.00 as the Option Fee.\nB. TERMINATION OPTION: Buyer has the unrestricted right to terminate this contract by giving notice of termination to Seller within 7 days after the Effective Date of this contract (Option Period). Notices under this paragraph must be given by 5:00 p.m. (local time where the Property is located) by the date specified.\n6. TITLE POLICY AND SURVEY:\nC. SURVEY: Within 10 days after the Effective Date of this contract, Seller shall furnish to Buyer and Title Company Seller's existing survey of the Property.\nTREC NO. 20-18",
            "9. CLOSING:\nA. The closing of the sale will be on or before {$closing}, or within 7 days after objections made under Paragraph 6D have been cured or waived, whichever date is later.\n10. POSSESSION: Seller shall deliver to Buyer possession of the Property in its present or required condition upon closing and funding.\n11. SPECIAL PROVISIONS: {$special}\nTREC NO. 20-18",
            "THIRD PARTY FINANCING ADDENDUM\n2. FINANCING APPROVAL: Buyer Approval: This contract is subject to Buyer obtaining Buyer Approval. If Buyer cannot obtain Buyer Approval, Buyer may give written notice to Seller within 21 days after the Effective Date of this contract and this contract will terminate.\nTREC NO. 40-11",
            "EXECUTED the {$effective} (Effective Date).\nBuyer: Ana Lopez    Seller: Maria Ortega, Luis Ortega\nTREC NO. 20-18",
        ];
    }

    /** @param  array{price?: string, cash?: string, financing?: string, closing?: string, effective?: string, special?: string}  $over */
    public static function pdf(array $over = []): string
    {
        return SimplePdf::pages(self::pages($over));
    }
}
