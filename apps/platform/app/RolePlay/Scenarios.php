<?php

declare(strict_types=1);

namespace App\RolePlay;

/**
 * What an agent can practise (A14.1a, ADR 0060): five types, each with
 * personas who push back. The persona's words on screen come from the
 * language files (`roleplay.personas.*`); the briefs below tell Claude who
 * to play and are never shown.
 *
 * @phpstan-type SkillType 'listing'|'objection'|'prospecting'|'appraisal_gap'|'follow_up'
 * @phpstan-type PersonaCode 'interviewing_seller'|'downsizing_couple'|'rate_worried_buyer'|'commission_pushback'|'fsbo'|'expired'|'low_appraisal'|'cold_lead'
 */
final class Scenarios
{
    public const TYPES = ['listing', 'objection', 'prospecting', 'appraisal_gap', 'follow_up'];

    public const PERSONAS = [
        'listing' => ['interviewing_seller', 'downsizing_couple'],
        'objection' => ['rate_worried_buyer', 'commission_pushback'],
        'prospecting' => ['fsbo', 'expired'],
        'appraisal_gap' => ['low_appraisal'],
        'follow_up' => ['cold_lead'],
    ];

    /** Scripted lines per persona: an opener and three pushbacks, then won or lost. */
    public const LINES = 4;

    /** Agent turns in a practice, and in an assessment round. */
    public const PRACTICE_TURNS = 4;

    public const ROUND_TURNS = 2;

    /** Who Claude plays (never shown; the agent's words are data). */
    public const BRIEFS = [
        'interviewing_seller' => 'a homeowner in Katy, Texas, interviewing three agents to sell a four-bedroom house; you want to know why this agent, what price, and how they will market it',
        'downsizing_couple' => 'a retired couple in Sugar Land, Texas, selling the family home of 30 years to downsize; emotional about the house and worried about where to go next and the timing',
        'rate_worried_buyer' => 'a first-time buyer in Houston, Texas, worried that interest rates make buying a bad idea and thinking of waiting a year',
        'commission_pushback' => 'a seller who likes the agent but wants them to cut their commission because another agent offered less',
        'fsbo' => 'a homeowner selling their own house without an agent to save the commission; polite but guarded on the phone',
        'expired' => 'a seller whose listing just expired after 120 days with another agent; frustrated and skeptical of agents',
        'low_appraisal' => 'a seller whose buyer\'s appraisal came in 15,000 dollars under the contract price; upset and asking what happens now',
        'cold_lead' => 'a buyer lead who asked about homes three months ago and stopped answering; busy, not sure they are still looking',
    ];

    /**
     * Words that show the agent spoke to this persona's concern, per language.
     *
     * @var array<string, array{en: list<string>, es: list<string>}>
     */
    public const TOPICS = [
        'interviewing_seller' => ['en' => ['price', 'market', 'sold', 'marketing', 'photos', 'days', 'plan'], 'es' => ['precio', 'mercado', 'vend', 'mercadeo', 'fotos', 'días', 'plan']],
        'downsizing_couple' => ['en' => ['timing', 'move', 'next home', 'memories', 'plan', 'step', 'help'], 'es' => ['tiempo', 'mudanza', 'próxima casa', 'recuerdos', 'plan', 'paso', 'ayud']],
        'rate_worried_buyer' => ['en' => ['rate', 'payment', 'refinanc', 'budget', 'lender', 'buydown', 'price'], 'es' => ['tasa', 'pago', 'refinanci', 'presupuesto', 'prestamista', 'precio']],
        'commission_pushback' => ['en' => ['value', 'net', 'service', 'marketing', 'negotiat', 'results'], 'es' => ['valor', 'neto', 'servicio', 'mercadeo', 'negoci', 'resultados']],
        'fsbo' => ['en' => ['buyer', 'net', 'exposure', 'showing', 'safety', 'paperwork', 'help'], 'es' => ['comprador', 'neto', 'exposición', 'visitas', 'seguridad', 'papeleo', 'ayud']],
        'expired' => ['en' => ['why', 'price', 'photos', 'marketing', 'different', 'feedback'], 'es' => ['por qué', 'precio', 'fotos', 'mercadeo', 'diferente', 'comentarios']],
        'low_appraisal' => ['en' => ['appraisal', 'comparable', 'comps', 'reconsideration', 'gap', 'renegotiat', 'options'], 'es' => ['avalúo', 'comparable', 'reconsideración', 'diferencia', 'renegoci', 'opciones']],
        'cold_lead' => ['en' => ['still', 'timeline', 'help', 'update', 'market', 'new listings'], 'es' => ['todavía', 'plazo', 'ayud', 'novedades', 'mercado', 'nuevas propiedades']],
    ];

    /** @return list<PersonaCode> */
    public static function personas(string $type): array
    {
        return self::PERSONAS[$type] ?? [];
    }

    public static function isPersona(string $type, string $persona): bool
    {
        return in_array($persona, self::personas($type), true);
    }

    /** A scripted line: 0 the opener, 1–3 pushbacks, or `won` / `lost`. */
    public static function line(string $persona, int|string $line, string $language): string
    {
        $text = __("roleplay.personas.{$persona}.lines.{$line}", [], $language);

        return is_string($text) ? $text : '';
    }
}
