<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Developer;
use Illuminate\Contracts\View\View;

class DeveloperController extends Controller
{
    public function show(Developer $developer): View
    {
        $mods = $developer->mods()
            ->published()
            ->with(['game', 'tags', 'latestVersion.gameVersions'])
            ->latest('published_at')
            ->paginate(12);

        $totalDownloads = (int) $developer->mods()->published()->sum('downloads_count');

        return view('developers.show', [
            'developer' => $developer,
            'mods' => $mods,
            'totalDownloads' => $totalDownloads,
        ]);
    }
}

