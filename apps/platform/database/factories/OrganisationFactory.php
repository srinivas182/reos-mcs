<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organisation>
 */
final class OrganisationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => OrganisationType::Brokerage,
            'name' => fake()->company().' Realty',
        ];
    }

    public function ofType(OrganisationType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
