@extends('layouts.app')

@section('title', 'Mod Inventory Table')

@section('content')
    <section class="section-header">
        <div>
            <span class="kicker">STORAGE MANAGEMENT // CREATOR: {{ '@' . auth()->user()->username }}</span>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">MOD INVENTORY TABLE</h1>
            <p style="margin-top: 0.4rem;">
                Complete inventory of submitted versions, automatically extracted XML/JSON metadata, validation status badges, and storage disk paths.
            </p>
        </div>
        <div>
            <a href="{{ route('upload.create') }}" class="btn">+ SUBMIT NEW FILE</a>
        </div>
    </section>

    <!-- Quick Validation Status Filters -->
    <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.5rem;">
        <a href="{{ route('inventory.index') }}"
           class="btn btn--sm {{ !$activeStatus ? '' : 'btn--white' }}">
            ALL ({{ $counts['all'] }})
        </a>
        <a href="{{ route('inventory.index', ['status' => 'validated']) }}"
           class="btn btn--sm {{ $activeStatus === 'validated' ? 'btn--green' : 'btn--white' }}">
            VALIDATED ({{ $counts['validated'] }})
        </a>
        <a href="{{ route('inventory.index', ['status' => 'warning']) }}"
           class="btn btn--sm {{ $activeStatus === 'warning' ? '' : 'btn--white' }}">
            WITH WARNINGS ({{ $counts['warning'] }})
        </a>
        <a href="{{ route('inventory.index', ['status' => 'invalid']) }}"
           class="btn btn--sm {{ $activeStatus === 'invalid' ? 'btn--danger' : 'btn--white' }}">
            XML/JSON SYNTAX ERROR ({{ $counts['invalid'] }})
        </a>
    </div>

    @if ($versions->isEmpty())
        <div class="panel" style="text-align: center; padding: 3rem 1.5rem;">
            <h2 class="heading-lg">NO FILES RECORDED UNDER THIS FILTER</h2>
            <p style="margin-top: 0.5rem; margin-bottom: 1.25rem;">
                Submit your first <code>.zip</code>, <code>.jar</code>, or <code>modDesc.xml</code> package.
            </p>
            <a href="{{ route('upload.create') }}" class="btn">OPEN DROP ZONE</a>
        </div>
    @else
        <div class="inventory-wrapper">
            <table class="inventory-table" data-sortable-table>
                <thead>
                    <tr>
                        <th data-sort="text">MOD / PACKAGE</th>
                        <th data-sort="text">GAME</th>
                        <th data-sort="text">VERSION</th>
                        <th data-sort="text">AUTHOR (XML)</th>
                        <th>COMPATIBILITY</th>
                        <th data-sort="number">SIZE</th>
                        <th data-sort="text">VALIDATION STATUS</th>
                        <th data-sort="text">DATE</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($versions as $ver)
                        <tr data-row class="{{ (int) session('highlight_version') === (int) $ver->id ? 'is-highlighted' : '' }}">
                            <td data-label="MOD / PACKAGE">
                                @if ($ver->mod && $ver->mod->published_at)
                                    <a href="{{ route('mods.show', $ver->mod) }}" style="font-weight: 800; text-decoration: underline;">
                                        {{ $ver->mod->name }}
                                    </a>
                                @else
                                    <strong style="color: var(--c-red);">{{ $ver->mod?->name ?? $ver->original_filename }}</strong>
                                @endif
                                <div style="font-size: 0.74rem; color: var(--c-muted);">
                                    {{ $ver->original_filename }} // <code>{{ $ver->file_path }}</code>
                                </div>
                            </td>

                            <td data-label="GAME">
                                <span class="badge badge--game">
                                    {{ $ver->mod?->game?->short_code ?? 'MOD' }}
                                </span>
                            </td>

                            <td data-label="VERSION">
                                <strong>{{ $ver->version ?? 'N/A (INVALID)' }}</strong>
                            </td>

                            <td data-label="AUTHOR (XML)">
                                {{ $ver->author ?? '—' }}
                            </td>

                            <td data-label="COMPATIBILITY">
                                @if ($ver->gameVersions->isNotEmpty())
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.3rem;">
                                        @foreach ($ver->gameVersions as $gv)
                                            <span class="badge badge--compat">{{ $gv->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span style="color: var(--c-muted);">—</span>
                                @endif
                            </td>

                            <td data-label="SIZE" data-sort-value="{{ $ver->file_size }}">
                                {{ $ver->formattedSize() }}
                            </td>

                            <td data-label="VALIDATION STATUS">
                                <span class="{{ $ver->status->badgeClass() }}">
                                    {{ $ver->status->label() }}
                                </span>
                                @if ($ver->issues_count > 0)
                                    <div style="font-size: 0.72rem; margin-top: 0.25rem;">
                                        {{ $ver->issues_count }} parser issue(s)
                                    </div>
                                @endif
                            </td>

                            <td data-label="DATE" data-sort-value="{{ $ver->created_at?->timestamp ?? 0 }}">
                                {{ $ver->created_at?->format('Y-m-d H:i') }}
                            </td>

                            <td data-label="ACTIONS">
                                <div class="inventory-actions">
                                    <a href="{{ route('versions.report', $ver) }}" class="btn btn--white btn--sm">
                                        XML REPORT
                                    </a>

                                    @if ($ver->mod && $ver->mod->published_at && $ver->status->isDownloadable())
                                        <a href="{{ route('versions.download', [$ver->mod, $ver]) }}" class="btn btn--green btn--sm">
                                            DOWNLOAD
                                        </a>
                                    @endif

                                    <form method="POST"
                                          action="{{ route('versions.destroy', $ver) }}"
                                          onsubmit="return confirm('Permanently delete this version and its file from storage?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm">DELETE</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($versions->hasPages())
            <div class="pagination-bar">
                <span>PAGE {{ $versions->currentPage() }} OF {{ $versions->lastPage() }}</span>
                <div style="display: flex; gap: 0.5rem;">
                    @if (! $versions->onFirstPage())
                        <a href="{{ $versions->previousPageUrl() }}" class="btn btn--white btn--sm">&laquo; PREVIOUS</a>
                    @endif
                    @if ($versions->hasMorePages())
                        <a href="{{ $versions->nextPageUrl() }}" class="btn btn--sm">NEXT &raquo;</a>
                    @endif
                </div>
            </div>
        @endif
    @endif
@endsection
