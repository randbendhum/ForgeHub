@extends('layouts.app')

@section('title', 'XML/JSON Validation Report #' . $version->id)

@section('content')
    <section class="section-header">
        <div>
            <span class="kicker">PARSER DIAGNOSTIC // VERSION #{{ $version->id }}</span>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">
                VALIDATION REPORT: {{ $version->original_filename }}
            </h1>
            <div style="margin-top: 0.45rem; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                <span class="{{ $version->status->badgeClass() }}">{{ $version->status->label() }}</span>
                <span class="badge badge--compat">{{ $version->manifest_type?->label() ?? 'Manifest' }}</span>
                <span class="badge badge--compat">{{ $version->formattedSize() }}</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            @auth
                <a href="{{ route('inventory.index') }}" class="btn btn--white">BACK TO INVENTORY</a>
            @endauth
            @if ($mod && $mod->published_at)
                <a href="{{ route('mods.show', $mod) }}" class="btn">VIEW MOD PAGE</a>
            @endif
        </div>
    </section>

    <div class="mod-detail-grid">
        <!-- Diagnostic Terminal -->
        <div class="form-stack">
            <div class="terminal">
                <div class="terminal__bar">
                    <span>PARSER OUTPUT // {{ $version->original_filename }}</span>
                    <span>{{ $version->issues->count() }} ISSUE(S)</span>
                </div>
                <div class="terminal__body">
                    <div class="terminal__line">
                        &gt; SHA-256: <strong>{{ $version->sha256 }}</strong>
                    </div>
                    <div class="terminal__line">
                        &gt; DISK LOCATION [{{ $version->disk }}]: <strong>{{ $version->file_path }}</strong>
                    </div>

                    @if ($version->issues->isEmpty())
                        <div class="terminal__line terminal__line--ok">
                            [OK] 0 ERRORS, 0 WARNINGS. ALL MANDATORY STRUCTURAL TAGS AND ATTRIBUTES PASSED VALIDATION.
                        </div>
                    @else
                        @foreach ($version->issues as $issue)
                            <div class="terminal__line {{ $issue->severity->value === 'error' ? 'terminal__line--error' : 'terminal__line--warning' }}">
                                [{{ $issue->severity->label() }} // {{ $issue->code }}]
                                @if ($issue->line)
                                    (LINE {{ $issue->line }}{{ $issue->column ? ':' . $issue->column : '' }})
                                @endif
                                — {{ $issue->message }}
                            </div>
                            @if ($issue->context)
                                <pre class="terminal__snippet">{{ $issue->context }}</pre>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        <!-- Extracted Metadata Summary -->
        <aside class="form-stack">
            <div class="panel">
                <div class="panel__header">
                    <h2 class="heading-md">EXTRACTED DATA</h2>
                </div>
                <dl class="meta-dl">
                    <dt>MOD</dt>
                    <dd>{{ $mod?->name ?? 'N/A' }}</dd>

                    <dt>IDENTIFIER</dt>
                    <dd>{{ $mod?->identifier ?? 'N/A' }}</dd>

                    <dt>EXTRACTED VERSION</dt>
                    <dd>{{ $version->version ?? 'NOT EXTRACTED (XML ERROR)' }}</dd>

                    <dt>EXTRACTED AUTHOR</dt>
                    <dd>{{ $version->author ?? 'NOT EXTRACTED' }}</dd>

                    <dt>COMPATIBILITY</dt>
                    <dd>
                        @if ($version->gameVersions->isNotEmpty())
                            {{ $version->gameVersions->pluck('name')->join(', ') }}
                        @else
                            N/A
                        @endif
                    </dd>

                    <dt>SUBMITTED AT</dt>
                    <dd>{{ $version->created_at?->format('Y-m-d H:i:s') }}</dd>
                </dl>
            </div>
        </aside>
    </div>
@endsection
