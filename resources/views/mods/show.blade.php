@extends('layouts.app')

@section('title', $mod->name)

@section('content')
    @php($latest = $mod->latestVersion)

    <section class="section-header">
        <div>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <span class="kicker">[{{ $mod->game->short_code }}] {{ $mod->game->name }}</span>
                @if ($latest)
                    <span class="{{ $latest->status->badgeClass() }}">{{ $latest->status->label() }}</span>
                @endif
            </div>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">{{ $mod->name }}</h1>
            <div class="mono-label" style="margin-top: 0.35rem; color: var(--c-muted);">
                TECHNICAL IDENTIFIER: <code>{{ $mod->identifier }}</code> //
                CREATOR: <a href="{{ route('developers.show', $mod->developer) }}" style="text-decoration: underline;">@{{ $mod->developer->username }}</a>
            </div>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 0.65rem;">
            @if ($latest && $latest->status->isDownloadable())
                <a href="{{ route('versions.download', [$mod, $latest]) }}" class="btn btn--green">
                    DOWNLOAD v{{ $latest->version }} ({{ $latest->formattedSize() }})
                </a>
            @endif
            @if ($isOwner)
                <a href="{{ route('upload.create', ['mod_id' => $mod->id]) }}" class="btn btn--white">
                    + UPLOAD NEW VERSION
                </a>
            @endif
        </div>
    </section>

    <div class="mod-detail-grid">
        <!-- Main Column: Description + Version History -->
        <div class="form-stack">
            <article class="panel">
                <div class="panel__header">
                    <h2 class="heading-md">EXTRACTED MANIFEST DESCRIPTION</h2>
                    <span class="mono-label">{{ $mod->game->manifest_filename }}</span>
                </div>
                <p style="white-space: pre-line;">{{ $mod->description ?: 'No description declared in the manifest.' }}</p>

                @if ($mod->tags->isNotEmpty())
                    <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 3px solid #000000; display: flex; flex-wrap: wrap; gap: 0.45rem;">
                        @foreach ($mod->tags as $tag)
                            <a href="{{ route('catalogue.index', ['tag' => $tag->slug]) }}" class="tag-chip">
                                #{{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </article>

            <!-- Mod Version History Table -->
            <section class="panel">
                <div class="panel__header">
                    <h2 class="heading-md">VERSION HISTORY ({{ $mod->versions->count() }})</h2>
                    <span class="mono-label">STORAGE DISK: MODS</span>
                </div>

                <div class="inventory-wrapper">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>VERSION</th>
                                <th>COMPATIBILITY</th>
                                <th>SIZE</th>
                                <th>STATUS</th>
                                <th>DOWNLOADS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mod->versions as $ver)
                                <tr>
                                    <td data-label="VERSION">
                                        <strong>v{{ $ver->version ?? 'INVALID' }}</strong>
                                        @if ($ver->changelog)
                                            <div style="font-size: 0.75rem; color: var(--c-muted); margin-top: 0.2rem;">
                                                {{ $ver->changelog }}
                                            </div>
                                        @endif
                                    </td>
                                    <td data-label="COMPATIBILITY">
                                        @foreach ($ver->gameVersions as $gv)
                                            <span class="badge badge--compat">{{ $gv->name }}</span>
                                        @endforeach
                                    </td>
                                    <td data-label="SIZE">{{ $ver->formattedSize() }}</td>
                                    <td data-label="STATUS">
                                        <span class="{{ $ver->status->badgeClass() }}">{{ $ver->status->shortLabel() }}</span>
                                    </td>
                                    <td data-label="DOWNLOADS">{{ $ver->downloads_count }}</td>
                                    <td data-label="ACTIONS">
                                        <div class="inventory-actions">
                                            <a href="{{ route('versions.report', $ver) }}" class="btn btn--white btn--sm">
                                                REPORT
                                            </a>
                                            @if ($ver->status->isDownloadable())
                                                <a href="{{ route('versions.download', [$mod, $ver]) }}" class="btn btn--green btn--sm">
                                                    DOWNLOAD
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Sidebar Column: Technical Manifest Metadata -->
        <aside class="form-stack">
            <div class="panel panel--yellow">
                <div class="panel__header">
                    <h2 class="heading-md">TECHNICAL METADATA</h2>
                </div>
                <dl class="meta-dl">
                    <dt>GAME</dt>
                    <dd>{{ $mod->game->name }}</dd>

                    <dt>FORMAT</dt>
                    <dd>{{ $latest?->manifest_type?->label() ?? $mod->game->manifest_filename }}</dd>

                    <dt>LATEST VERSION</dt>
                    <dd>v{{ $latest?->version ?? 'N/A' }}</dd>

                    <dt>AUTHOR (XML/JSON)</dt>
                    <dd>{{ $latest?->author ?? $mod->developer->name }}</dd>

                    <dt>TOTAL DOWNLOADS</dt>
                    <dd>{{ number_format($mod->downloads_count) }}</dd>

                    @if (isset($latest->metadata['desc_version']))
                        <dt>DESCVERSION (GIANTS)</dt>
                        <dd>{{ $latest->metadata['desc_version'] }}</dd>
                    @endif

                    @if (isset($latest->metadata['multiplayer']))
                        <dt>MULTIPLAYER</dt>
                        <dd>{{ $latest->metadata['multiplayer'] ? 'SUPPORTED' : 'SINGLEPLAYER' }}</dd>
                    @endif

                    @if (isset($latest->metadata['store_items_count']))
                        <dt>STORE ITEMS</dt>
                        <dd>{{ $latest->metadata['store_items_count'] }}</dd>
                    @endif

                    @if (isset($latest->metadata['license']))
                        <dt>LICENSE</dt>
                        <dd>{{ $latest->metadata['license'] }}</dd>
                    @endif

                    <dt>PUBLISHED AT</dt>
                    <dd>{{ $mod->published_at?->format('Y-m-d') ?? 'DRAFT' }}</dd>
                </dl>
            </div>

            @if ($latest)
                <div class="panel">
                    <div class="panel__header">
                        <h2 class="heading-md">SHA-256 INTEGRITY</h2>
                    </div>
                    <div style="font-family: var(--font-mono); font-size: 0.74rem; word-break: break-all;">
                        <div><strong>FILENAME:</strong> {{ $latest->original_filename }}</div>
                        <div style="margin-top: 0.4rem;"><strong>STORAGE PATH:</strong> <code>{{ $latest->file_path }}</code></div>
                        <div style="margin-top: 0.4rem;"><strong>SHA-256:</strong> <code>{{ $latest->sha256 }}</code></div>
                    </div>
                </div>
            @endif
        </aside>
    </div>
@endsection
