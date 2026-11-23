<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Crm\Sources;
use App\Models\Contact;
use App\Models\ContactLink;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact (B7.1): one person, with every source that knows them.
 *
 * @property Contact $resource
 */
final class ContactResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;
        $sources = app(Sources::class);

        return [
            'id' => $contact->uuid,
            'name' => $contact->name(),
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            /** @var list<string> */
            'emails' => array_column($contact->emails, 'value'),
            /** @var list<string> Phone numbers in E.164. */
            'phones' => array_column($contact->phones, 'value'),
            'stage' => $contact->stage,
            'birthday' => $contact->birthday?->toDateString(),
            /** @var list<string> */
            'tags' => $contact->tags->map(static fn (Tag $t): string => $t->name)->values()->all(),
            /**
             * Where this person comes from.
             *
             * Where this person comes from, and a source's smart campaign (kvCORE), shown but never changed.
             *
             * @var list<array{source: string, name: string, synced_at: string|null, campaign: string|null, campaign_status: string|null}>
             */
            'sources' => $contact->links->map(static fn (ContactLink $l): array => [
                'source' => $l->source, 'name' => $sources->name($l->source), 'synced_at' => $l->synced_at?->toIso8601String(),
                'campaign' => $l->details['campaign'] ?? null, 'campaign_status' => $l->details['campaign_status'] ?? null,
            ])->values()->all(),
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }
}
