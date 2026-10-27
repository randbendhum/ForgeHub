<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModStatus;
use App\Models\Developer;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Developer $developer */
        $developer = $request->user();
        $statusFilter = $request->query('status');

        $baseQuery = ModVersion::query()
            ->whereHas('mod', fn ($q) => $q->where('developer_id', $developer->id));

        $counts = [
            'all' => (clone $baseQuery)->count(),
            'validated' => (clone $baseQuery)->where('status', ModStatus::Validated->value)->count(),
            'warning' => (clone $baseQuery)->where('status', ModStatus::Warning->value)->count(),
            'invalid' => (clone $baseQuery)->where('status', ModStatus::Invalid->value)->count(),
        ];

        $versionsQuery = (clone $baseQuery)
            ->with(['mod.game', 'mod.tags', 'gameVersions'])
            ->withCount('issues')
            ->latest();

        if (is_string($statusFilter) && in_array($statusFilter, ['validated', 'warning', 'invalid'], true)) {
            $versionsQuery->where('status', $statusFilter);
        }

        $versions = $versionsQuery->paginate(15)->withQueryString();

        return view('inventory.index', [
            'versions' => $versions,
            'counts' => $counts,
            'activeStatus' => is_string($statusFilter) ? $statusFilter : null,
        ]);
    }
}

