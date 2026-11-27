<?php

declare(strict_types=1);

namespace App\Feedback;

use App\Models\User;
use App\Services\Ai\Bedrock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A tip on what to improve from feedback by other agents (A13.3b): the
 * lowest-rated area, with one plain suggestion. Claude writes it from the
 * comments when it's configured (the comments are data, never
 * instructions); otherwise REOS's tip for that area. Kept for a week, or
 * until a new rating arrives.
 */
final readonly class FeedbackTips
{
    public function __construct(private Bedrock $bedrock) {}

    /**
     * @param  array{overall: float, communication: float, responsiveness: float, professionalism: float}  $average
     * @param  list<string>  $comments
     * @return array{dimension: 'communication'|'responsiveness'|'professionalism', text: string, engine: 'claude'|'template'}
     */
    public function tip(User $agent, array $average, array $comments): array
    {
        $dimension = 'communication';

        foreach (AgentFeedbacks::DIMENSIONS as $key) {
            if ($average[$key] < $average[$dimension]) {
                $dimension = $key;
            }
        }

        $fallback = ['dimension' => $dimension, 'text' => self::text($dimension, $agent->locale->value), 'engine' => 'template'];
        $bedrock = $comments === [] ? null : $this->bedrock->client(15);

        if ($bedrock === null) {
            return $fallback;
        }

        $key = 'feedback-tip:'.$agent->id.':'.md5($dimension.implode('|', $comments));

        try {
            $text = Cache::remember($key, now()->addWeek(), fn (): string => (new ClaudeFeedbackTipWriter($bedrock['client'], $bedrock['model']))->write($dimension, $comments, $agent->locale->value));

            return $text !== '' ? ['dimension' => $dimension, 'text' => $text, 'engine' => 'claude'] : $fallback;
        } catch (Throwable $e) {
            Log::warning('feedback.tip_failed', ['user' => $agent->id, 'error' => $e->getMessage()]);

            return $fallback;
        }
    }

    private static function text(string $dimension, string $locale): string
    {
        $text = __("feedback.tips.{$dimension}", [], $locale);

        return is_string($text) ? $text : '';
    }
}
