<?php

declare(strict_types=1);

namespace App\Content\Review;

/**
 * Lines that must not appear in course content without a person checking
 * them (A5.3b; screen 63: "legal statements checked against state rules;
 * flagged lines block publishing"). Based on TREC's advertising and
 * education rules (22 TAC §535.155, §535.65): no guaranteed results, no
 * pass-rate or approval claims that aren't backed by a record, no
 * unprovable superlatives. A reviewer can clear a line that's fine in
 * context.
 */
enum LegalRule: string
{
    case Guarantee = 'guarantee';
    case PassRate = 'pass_rate';
    case RiskFree = 'risk_free';
    case ApprovalClaim = 'approval_claim';
    case Superlative = 'superlative';

    /** @return list<string> Patterns in English and Spanish */
    public function patterns(): array
    {
        return match ($this) {
            self::Guarantee => ['/\bguarantee(?:d|s)?\b/iu', '/\bgarantiza(?:do|da|mos)?\b/iu', '/\bgarant[íi]a\b/iu'],
            self::PassRate => ['/\b\d{2,3}\s?%\s+(?:pass|of (?:students|candidates) pass)/iu', '/\b\d{2,3}\s?%\s+de\s+aprobaci[óo]n/iu'],
            self::RiskFree => ['/\brisk[- ]free\b/iu', '/\bsin\s+riesgo\b/iu'],
            self::ApprovalClaim => ['/\b(?:TREC|commission|state)[- ]approved\b/iu', '/\baprobado\s+por\s+(?:la\s+)?TREC\b/iu'],
            self::Superlative => ['/\b(?:the\s+best|#\s?1|number\s+one)\s+(?:real estate\s+)?(?:school|course|program|in\s+texas)\b/iu', '/\bel\s+mejor\s+(?:curso|programa)\b/iu'],
        };
    }

    /** The rule behind the check, shown beside a flagged line (A8.1, screen 44). */
    public function source(): string
    {
        return match ($this) {
            self::Guarantee, self::RiskFree, self::Superlative => '22 TAC §535.155 (advertising)',
            self::PassRate => '22 TAC §535.155; TREC pass rates are public by provider',
            self::ApprovalClaim => '22 TAC §535.65 (approved courses and approval numbers)',
        };
    }

    /**
     * Plain words that say the same thing within the rules, used to rewrite a
     * flagged line when Claude isn't set up (A8.1).
     *
     * @return array{en: string, es: string}
     */
    public function replacement(): array
    {
        return match ($this) {
            self::Guarantee => ['en' => 'help you prepare for', 'es' => 'le ayuda a prepararse para'],
            self::PassRate => ['en' => 'many of our students pass', 'es' => 'muchos de nuestros estudiantes aprueban'],
            self::RiskFree => ['en' => 'with a clear refund policy', 'es' => 'con una política de reembolso clara'],
            self::ApprovalClaim => ['en' => 'approved course number on record', 'es' => 'número de aprobación registrado'],
            self::Superlative => ['en' => 'a trusted Texas school', 'es' => 'una escuela de Texas de confianza'],
        };
    }

    /** An approval claim is fine when the course has its approval numbers recorded. */
    public function waivedByApproval(): bool
    {
        return $this === self::ApprovalClaim;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $rule): string => $rule->value, self::cases());
    }
}
