@extends('layouts.app')

@section('title', 'Creator @' . $developer->username)

@section('content')
    <section class="section-header">
        <div>
            <span class="kicker">MOD CREATOR PROFILE</span>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">{{ $developer->name }} ({{ '@' . $developer->username }})</h1>
            @if ($developer->bio)
                <p style="margin-top: 0.4rem; max-width: 720px;">{{ $developer->bio }}</p>
            @endif
        </div>
        <div class="metric-box metric-box--yellow" style="min-width: 220px;">
            <span class="mono-label">CREATOR DOWNLOADS</span>
            <span class="metric-box__value">{{ number_format($totalDownloads) }}</span>
        </div>
    </section>

    @if ($mods->isEmpty())
        <div class="panel" style="text-align: center; padding: 2.5rem;">
            <h2 class="heading-md">THIS CREATOR HAS NO PUBLISHED MODS YET.</h2>
        </div>
    @else
        <div class="mods-grid">
            @foreach ($mods as $mod)
                @php($latest = $mod->latestVersion)
                <article class="mod-card">
                    <div class="mod-card__top">
                        <span>[{{ $mod->game->short_code }}] {{ $mod->game->name }}</span>
                        @if ($latest)
                            <span class="{{ $latest->status->badgeClass() }}">{{ $latest->status->shortLabel() }}</span>
                        @endif
                    </div>
                    <div class="mod-card__body">
                        <h3 class="mod-card__title">
                            <a href="{{ route('mods.show', $mod) }}">{{ $mod->name }}</a>
                        </h3>
                        <div class="mono-label" style="color: var(--c-muted);">
                            v{{ $latest?->version ?? 'N/A' }} // {{ $mod->downloads_count }} downloads
                        </div>
                        <p class="mod-card__desc">{{ $mod->description }}</p>
                    </div>
                    <div class="mod-card__footer">
                        <span>PUBLISHED {{ $mod->published_at?->format('Y-m-d') }}</span>
                        <a href="{{ route('mods.show', $mod) }}" class="btn btn--sm">VIEW DETAILS</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
