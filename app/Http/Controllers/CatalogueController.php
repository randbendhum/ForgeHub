<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModStatus;
use App\Models\Game;
use App\Models\Mod;
use App\Models\ModVersion;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function index(Request $request): View
    {
        $gameSlug = $request->query('game');
        $tagSlug = $request->query('tag');
        $search = $request->query('q');

        $mods = Mod::query()
            ->published()
            ->with([
                'developer',
                'game',
                'tags',
                'latestVersion.gameVersions',
            ])
            ->forGame(is_string($gameSlug) ? $gameSlug : null)
            ->withTag(is_string($tagSlug) ? $tagSlug : null)
            ->search(is_string($search) ? $search : null)
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        $games = Game::query()->orderBy('name')->get();

        $tags = Tag::query()
            ->withCount(['mods' => fn ($q) => $q->published()])
            ->orderByDesc('mods_count')
            ->orderBy('name')
            ->limit(14)
            ->get();

        $metrics = [
            'total_mods' => Mod::query()->published()->count(),
            'validated_versions' => ModVersion::query()
                ->whereIn('status', [ModStatus::Validated->value, ModStatus::Warning->value])
                ->count(),
            'quarantined_versions' => ModVersion::query()
                ->where('status', ModStatus::Invalid->value)
                ->count(),
            'total_downloads' => (int) Mod::query()->published()->sum('downloads_count'),
        ];

        return view('catalogue.index', [
            'mods' => $mods,
            'games' => $games,
            'tags' => $tags,
            'metrics' => $metrics,
            'activeGame' => is_string($gameSlug) ? $gameSlug : null,
            'activeTag' => is_string($tagSlug) ? $tagSlug : null,
            'searchQuery' => is_string($search) ? $search : '',
        ]);
    }
}

