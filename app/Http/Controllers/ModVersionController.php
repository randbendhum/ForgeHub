<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModStatus;
use App\Models\Mod;
use App\Models\ModVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModVersionController extends Controller
{
    public function download(Mod $mod, ModVersion $version): StreamedResponse
    {
        if (
            (int) $version->mod_id !== (int) $mod->id
            || $mod->published_at === null
            || ! $version->status->isDownloadable()
        ) {
            abort(404, 'This version is not available for public download.');
        }

        $disk = Storage::disk($version->disk);
        if (! $disk->exists($version->file_path)) {
            abort(404, 'Package file was not found in the storage disk.');
        }

        $version->increment('downloads_count');
        $mod->increment('downloads_count');

        return $disk->download($version->file_path, $version->original_filename);
    }

    public function report(ModVersion $version): View
    {
        $version->load(['mod.game', 'mod.developer', 'issues', 'gameVersions']);

        Gate::authorize('viewReport', $version);

        return view('versions.report', [
            'version' => $version,
            'mod' => $version->mod,
        ]);
    }

    public function destroy(ModVersion $version): RedirectResponse
    {
        $version->load('mod');
        Gate::authorize('delete', $version);

        $mod = $version->mod;
        $disk = Storage::disk($version->disk);

        if ($disk->exists($version->file_path)) {
            $disk->delete($version->file_path);
        }

        $wasLatest = $mod !== null && (int) $mod->latest_version_id === (int) $version->id;
        $version->delete();

        if ($mod !== null) {
            if ($wasLatest) {
                $nextValid = ModVersion::query()
                    ->where('mod_id', $mod->id)
                    ->where('status', '!=', ModStatus::Invalid->value)
                    ->latest()
                    ->first();

                $mod->update([
                    'latest_version_id' => $nextValid?->id,
                    'published_at' => $nextValid !== null ? $mod->published_at : null,
                ]);
            }

            if ($mod->versions()->count() === 0) {
                $mod->delete();
            }
        }

        return redirect()
            ->route('inventory.index')
            ->with('status', 'VERSION AND PACKAGE FILE SUCCESSFULLY REMOVED FROM STORAGE.');
    }
}
