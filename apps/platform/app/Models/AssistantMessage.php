<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A question or answer in an assistant chat (A12.3, ADR 0049).
 *
 * @property int $id
 * @property int $assistant_chat_id
 * @property string $role question or answer
 * @property string $body
 * @property string|null $kind today, prep, deal, draft, policy, general, permission or none
 * @property list<array{title: string, detail: string|null, source: string|null, at: string|null, link: string|null}>|null $items
 * @property array{channel: 'email'|'text', to: string, name: string, subject: string|null, body: string, href: string}|null $draft
 * @property string|null $broker_question
 * @property string|null $engine
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class AssistantMessage extends Model
{
    protected $fillable = ['assistant_chat_id', 'role', 'body', 'kind', 'items', 'draft', 'broker_question', 'engine'];

    protected function casts(): array
    {
        return ['items' => 'array', 'draft' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
