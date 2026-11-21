<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Finding people for the People screen (super admin). */
final readonly class PeopleDirectory
{
    public const PER_PAGE = 20;

    /**
     * People whose name, email or phone contains the search (any case),
     * newest first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    public function search(?string $term): LengthAwarePaginator
    {
        $term = trim((string) $term);

        return User::query()
            ->when($term !== '', static function (Builder $query) use ($term): void {
                $like = '%'.mb_strtolower(addcslashes($term, '%_\\')).'%';
                $digits = preg_replace('/\D+/', '', $term) ?? '';

                $query->where(static function (Builder $query) use ($like, $digits): void {
                    $query->whereRaw('lower(name) like ?', [$like])
                        ->orWhereRaw('lower(email) like ?', [$like]);

                    if (strlen($digits) >= 3) {
                        $query->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
