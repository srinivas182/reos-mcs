<?php

declare(strict_types=1);

namespace App\Leads;

use App\Models\AgentPage;
use App\Tenancy\Tenancy;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * "Ask {agent} a question" on the agent's public page (B14.3a, ADR 0066):
 * the message lands in the agent's CRM as a new contact (or the one they
 * already have), with a follow-up and a suggested reply.
 */
final readonly class PageLeads
{
    public function __construct(private Leads $leads, private Tenancy $tenancy) {}

    /** @param array{name: string, email: string|null, phone: string|null, message: string, has_agent: bool} $lead */
    public function send(string $handle, array $lead): void
    {
        $page = $this->tenancy->asPlatform(static fn (): ?AgentPage => AgentPage::query()->where('handle', $handle)->where('published', true)->with('user')->first()) ?? throw new NotFoundHttpException;
        $agent = $page->user;
        $lang = $agent->locale->value;
        $first = (preg_split('/\s+/u', trim($lead['name'])) ?: [''])[0];
        $note = __('leads.page.note', ['message' => trim($lead['message'])], $lang);
        $draft = __('leads.page.draft', ['name' => $first, 'agent' => (string) $agent->name], $lang);
        $tag = __('leads.page.tag', [], $lang);

        $this->leads->add($agent, ['name' => trim($lead['name']), 'email' => $lead['email'], 'phone' => $lead['phone']], is_string($note) ? $note : '', is_string($draft) ? $draft : '', $lead['has_agent'], [is_string($tag) ? $tag : 'Agent page'], 'page');
    }
}
