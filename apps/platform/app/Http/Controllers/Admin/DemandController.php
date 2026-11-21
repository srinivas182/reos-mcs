<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Catalogue;
use App\Models\IntegrationInterest;
use App\Models\ToolRequest;
use Inertia\Inertia;
use Inertia\Response;

/** "Notify-me demand" (web journey screen 64): which tools people want most, and tools they asked for. */
final class DemandController extends Controller
{
    public function __invoke(Catalogue $catalogue): Response
    {
        $rows = IntegrationInterest::query()
            ->selectRaw('integration_id, kind, count(*) as total')
            ->groupBy('integration_id', 'kind')
            ->get();
        $tools = [];

        foreach ($rows as $row) {
            $id = $row->integration_id;
            $tools[$id] ??= ['id' => $id, 'name' => $catalogue->find($id)->name ?? $id, 'notify' => 0, 'beta' => 0];
            $total = $row->getAttribute('total');
            $tools[$id][$row->kind === IntegrationInterest::BETA ? 'beta' : 'notify'] = is_numeric($total) ? intval($total) : 0;
        }

        usort($tools, static fn (array $a, array $b): int => ($b['notify'] + $b['beta']) <=> ($a['notify'] + $a['beta']));

        return Inertia::render('admin/integrations/demand', [
            'tools' => $tools,
            'requests' => ToolRequest::query()->latest('id')->limit(100)->get()->map(static fn (ToolRequest $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'note' => $r->note,
                'at' => $r->created_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
