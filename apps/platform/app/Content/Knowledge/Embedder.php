<?php

declare(strict_types=1);

namespace App\Content\Knowledge;

/** Turns text into a vector of meaning (A5.3, decision 1: Titan Text Embeddings V2, 1,024 numbers). */
interface Embedder
{
    public const DIMENSIONS = 1024;

    /** @return list<float> */
    public function embed(string $text): array;
}
