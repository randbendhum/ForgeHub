@extends('layouts.app')

@section('title', 'Validated Mod Catalogue')

@section('content')
    <section class="section-header">
        <div>
            <span class="kicker">MANIFEST VALIDATION SYSTEM // GIANTS ENGINE &amp; FABRIC/FORGE</span>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">MOD MANAGER &amp; XML VALIDATOR</h1>
            <p style="margin-top: 0.4rem; max-width: 720px;">
                Submit <code>.zip</code>, <code>.jar</code>, <code>modDesc.xml</code>, or <code>fabric.mod.json</code> files.
                Our PHP parser engine validates structural tag integrity, extracts metadata, and organizes versioned packages in the storage disk.
            </p>
        </div>
        <div>
            @auth
                <a href="{{ route('upload.create') }}" class="btn">+ SUBMIT NEW MOD</a>
            @else
                <a href="{{ route('login') }}" class="btn">LOGIN TO SUBMIT MOD</a>
            @endauth
        </div>
    </section>

    <!-- Metrics Bar in CSS Grid -->
    <section class="metrics-grid" aria-label="Platform Metrics">
        <div class="metric-box metric-box--yellow">
            <span class="mono-label">PUBLISHED MODS</span>
            <span class="metric-box__value">{{ $metrics['total_mods'] }}</span>
        </div>
        <div class="metric-box metric-box--green">
            <span class="mono-label">VALIDATED VERSIONS</span>
            <span class="metric-box__value">{{ $metrics['validated_versions'] }}</span>
        </div>
        <div class="metric-box metric-box--orange">
            <span class="mono-label">QUARANTINED XML ERRORS</span>
            <span class="metric-box__value">{{ $metrics['quarantined_versions'] }}</span>
        </div>
        <div class="metric-box">
            <span class="mono-label">TOTAL DOWNLOADS</span>
            <span class="metric-box__value">{{ number_format($metrics['total_downloads']) }}</span>
        </div>
    </section>

    <div class="catalogue-layout">
        <!-- Sidebar Filter Panel -->
        <aside class="panel">
            <div class="panel__header">
                <h2 class="heading-md">FILTER CATALOGUE</h2>
                @if ($activeGame || $activeTag || $searchQuery !== '')
                    <a href="{{ route('catalogue.index') }}" class="mono-label" style="text-decoration: underline;">RESET</a>
                @endif
            </div>

            <form method="GET" action="{{ route('catalogue.index') }}" class="form-stack">
                @if ($activeGame)
                    <input type="hidden" name="game" value="{{ $activeGame }}">
                @endif
                @if ($activeTag)
                    <input type="hidden" name="tag" value="{{ $activeTag }}">
                @endif

                <div class="field">
                    <label for="q" class="field__label">TECHNICAL SEARCH</label>
                    <input type="search" id="q" name="q" value="{{ $searchQuery }}"
                           placeholder="Name, ID, or description..." class="field__input">
                </div>

                <button type="submit" class="btn btn--sm btn--block">RUN FILTER</button>
            </form>

            <div style="margin-top: 1.5rem;">
                <div class="field__label" style="margin-bottom: 0.6rem;">SIMULATION GAME</div>
                <div style="display: grid; gap: 0.45rem;">
                    <a href="{{ route('catalogue.index', array_filter(['q' => $searchQuery, 'tag' => $activeTag])) }}"
                       class="tag-chip {{ !$activeGame ? 'is-active' : '' }}">
                        ALL GAMES
                    </a>
                    @foreach ($games as $game)
                        <a href="{{ route('catalogue.index', array_filter(['game' => $game->slug, 'tag' => $activeTag, 'q' => $searchQuery])) }}"
                           class="tag-chip {{ $activeGame === $game->slug ? 'is-active' : '' }}">
                            [{{ $game->short_code }}] {{ $game->name }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <div class="field__label" style="margin-bottom: 0.6rem;">TAGS &amp; CATEGORIES</div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.4rem;">
                    @foreach ($tags as $tag)
                        <a href="{{ route('catalogue.index', array_filter(['game' => $activeGame, 'tag' => $tag->slug, 'q' => $searchQuery])) }}"
                           class="tag-chip {{ $activeTag === $tag->slug ? 'is-active' : '' }}">
                            #{{ $tag->name }} ({{ $tag->mods_count }})
                        </a>
                    @endforeach
                </div>
            </div>
        </aside>

        <!-- Main Mod Grid -->
        <section>
            @if ($mods->isEmpty())
                <div class="panel" style="text-align: center; padding: 3rem 1.5rem;">
                    <h2 class="heading-lg">NO MODS FOUND MATCHING THIS FILTER</h2>
                    <p style="margin-top: 0.5rem;">Try clearing the filters or submitting a new XML/ZIP package.</p>
                </div>
            @else
                <div class="mods-grid">
                    @foreach ($mods as $mod)
                        @php($latest = $mod->latestVersion)
                        <article class="mod-card">
                            <div class="mod-card__top">
                                <span>[{{ $mod->game->short_code }}] {{ $mod->game->name }}</span>
                                @if ($latest)
                                    <span class="{{ $latest->status->badgeClass() }}">
                                        {{ $latest->status->shortLabel() }}
                                    </span>
                                @endif
                            </div>

                            <div class="mod-card__body">
                                <h3 class="mod-card__title">
                                    <a href="{{ route('mods.show', $mod) }}">{{ $mod->name }}</a>
                                </h3>

                                <div class="mono-label" style="color: var(--c-muted);">
                                    ID: {{ $mod->identifier }} // v{{ $latest?->version ?? 'N/A' }}
                                </div>

                                <p class="mod-card__desc">
                                    {{ $mod->description ?: 'No additional description provided in manifest.' }}
                                </p>

                                <div class="mod-card__meta">
                                    @if ($latest)
                                        @foreach ($latest->gameVersions as $gv)
                                            <span class="badge badge--compat">{{ $gv->name }}</span>
                                        @endforeach
                                    @endif
                                    @foreach ($mod->tags->take(3) as $tag)
                                        <span class="tag-chip">#{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mod-card__footer">
                                <span>
                                    BY:
                                    <a href="{{ route('developers.show', $mod->developer) }}" style="text-decoration: underline;">
                                        {{ '@' . $mod->developer->username }}
                                    </a>
                                </span>
                                <a href="{{ route('mods.show', $mod) }}" class="btn btn--sm">
                                    INSPECT ({{ $mod->downloads_count }} DL)
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($mods->hasPages())
                    <div class="pagination-bar">
                        <span>PAGE {{ $mods->currentPage() }} OF {{ $mods->lastPage() }}</span>
                        <div style="display: flex; gap: 0.5rem;">
                            @if (! $mods->onFirstPage())
                                <a href="{{ $mods->previousPageUrl() }}" class="btn btn--white btn--sm">&laquo; PREVIOUS</a>
                            @endif
                            @if ($mods->hasMorePages())
                                <a href="{{ $mods->nextPageUrl() }}" class="btn btn--sm">NEXT &raquo;</a>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </section>
    </div>
@endsection
