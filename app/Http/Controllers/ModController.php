<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModStatus;
use App\Models\Mod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ModController extends Controller
{
    public function show(Request $request, Mod $mod): View
    {
        $user = $request->user();
        $isOwner = $user !== null && (int) $user->id === (int) $mod->developer_id;

        if ($mod->published_at === null && ! $isOwner) {
            abort(404);
        }

        $mod->load([
            'developer',
            'game',
            'tags',
            'latestVersion.gameVersions',
            'latestVersion.issues',
            'versions' => function ($query) use ($isOwner) {
                if (! $isOwner) {
                    $query->where('status', '!=', ModStatus::Invalid->value);
                }
                $query->with(['gameVersions', 'issues'])->latest();
            },
        ]);

        return view('mods.show', [
            'mod' => $mod,
            'isOwner' => $isOwner,
        ]);
    }
}

