<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ModStatus;
use App\Http\Requests\StoreModUploadRequest;
use App\Http\Requests\ValidateModUploadRequest;
use App\Models\Developer;
use App\Models\Mod;
use App\Services\ModParsing\ModValidator;
use App\Services\ModPublisher;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function create(Request $request): View
    {
        /** @var Developer $developer */
        $developer = $request->user();

        $developerMods = Mod::query()
            ->where('developer_id', $developer->id)
            ->whereNotNull('published_at')
            ->with('game')
            ->orderBy('name')
            ->get();

        return view('upload.create', [
            'developerMods' => $developerMods,
            'selectedModId' => $request->query('mod_id'),
        ]);
    }

    public function validateFile(
        ValidateModUploadRequest $request,
        ModValidator $validator,
    ): JsonResponse {
        $file = $request->file('file');
        $report = $validator->validateUploadedFile($file);

        return response()->json([
            'filename' => $file->getClientOriginalName(),
            'size_bytes' => $file->getSize(),
            'report' => $report->toArray(),
        ]);
    }

    public function store(
        StoreModUploadRequest $request,
        ModPublisher $publisher,
    ): JsonResponse|RedirectResponse {
        /** @var Developer $developer */
        $developer = $request->user();
        $targetMod = $request->targetMod();

        /** @var list<string> $tags */
        $tags = array_values((array) $request->validated('tags', []));
        $changelog = $request->validated('changelog');

        $modVersion = $publisher->publishUploadedFile(
            developer: $developer,
            file: $request->file('file'),
            targetMod: $targetMod,
            tagNames: $tags,
            changelog: is_string($changelog) ? $changelog : null,
        );

        $flashMessage = match ($modVersion->status) {
            ModStatus::Validated => sprintf(
                'MOD "%s" (v%s) VALIDATED AND PUBLISHED SUCCESSFULLY!',
                $modVersion->mod->name,
                $modVersion->version
            ),
            ModStatus::Warning => sprintf(
                'MOD "%s" (v%s) PUBLISHED WITH %d STRUCTURAL WARNING(S).',
                $modVersion->mod->name,
                $modVersion->version,
                $modVersion->issues->count()
            ),
            ModStatus::Invalid => sprintf(
                'SYNTAX/STRUCTURAL ERROR DETECTED IN "%s": FILE QUARANTINED FOR DIAGNOSTICS.',
                $modVersion->original_filename
            ),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'version_id' => $modVersion->id,
                'status' => $modVersion->status->value,
                'status_label' => $modVersion->status->label(),
                'message' => $flashMessage,
                'redirect' => route('inventory.index'),
                'report_url' => route('versions.report', $modVersion),
            ], 201);
        }

        return redirect()
            ->route('inventory.index')
            ->with('status', $flashMessage)
            ->with('highlight_version', $modVersion->id);
    }
}
