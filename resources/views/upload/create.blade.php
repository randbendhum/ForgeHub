@extends('layouts.app')

@section('title', 'Submit & Validate Mod (XML / ZIP / JAR)')

@section('content')
    <section class="section-header">
        <div>
            <span class="kicker">UPLOAD &amp; STRUCTURAL PARSER // LIBXML + JSON SCHEMA</span>
            <h1 class="heading-xl" style="margin-top: 0.55rem;">MOD SUBMISSION &amp; VALIDATION ZONE</h1>
            <p style="margin-top: 0.4rem;">
                Drag and drop your <code>.zip</code>, <code>.jar</code>, <code>modDesc.xml</code>, or <code>fabric.mod.json</code> file.
                Our backend engine runs an instant diagnostic before persisting to the structured storage disk.
            </p>
        </div>
        <div>
            <a href="{{ route('inventory.index') }}" class="btn btn--white">VIEW INVENTORY TABLE</a>
        </div>
    </section>

    <div class="upload-grid">
        <!-- Primary Column: Drop Zone + Live Terminal + Metadata -->
        <div class="form-stack">
            <form method="POST" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="form-stack">
                @csrf

                <!-- FILE DROP ZONE (DRAG & DROP) — 3px Dashed Black Border -->
                <div class="dropzone"
                     data-dropzone
                     data-validate-url="{{ route('upload.validate') }}"
                     tabindex="0"
                     role="button"
                     aria-label="Mod File Drop Zone. Click or drag a .zip, .jar, .xml, or .json file">
                    <input type="file"
                           name="file"
                           id="mod-file-input"
                           accept=".zip,.jar,.xml,.json,.info"
                           required
                           style="position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none;">

                    <div class="dropzone__inner">
                        <div class="dropzone__icon">[UP]</div>
                        <div class="dropzone__title">
                            DRAG &amp; DROP YOUR .ZIP, .JAR, .XML OR .JSON FILE HERE
                        </div>
                        <div class="dropzone__subtitle">
                            OR CLICK THIS BOX TO BROWSE LOCAL FILES // MAX 50 MB
                        </div>
                        <div class="dropzone__file-pill" data-dropzone-filename hidden></div>
                    </div>
                </div>

                <!-- Brutalist XHR Progress Bar -->
                <div class="upload-progress" data-upload-progress>
                    <div class="upload-progress__bar" data-upload-progress-bar></div>
                    <div class="upload-progress__label" data-upload-progress-label>PREPARING UPLOAD...</div>
                </div>

                <!-- Live Diagnostic Terminal -->
                <div class="terminal" aria-live="polite">
                    <div class="terminal__bar">
                        <span>TERMINAL // MANIFEST PARSER (DOMXPATH &amp; LIBXML)</span>
                        <span data-terminal-status>AWAITING FILE</span>
                    </div>
                    <div class="terminal__body" data-terminal-output>
                        <div class="terminal__line">&gt; SYSTEM READY. DROP A PACKAGE TO START METADATA EXTRACTION.</div>
                        <div class="terminal__line">&gt; ACTIVE CHECKS: ANTI-XXE PROTECTION, ANTI ZIP-BOMB, PATH TRAVERSAL &amp; MANDATORY TAGS.</div>
                    </div>
                </div>

                <!-- Versioning & Tag Parameters -->
                <div class="panel form-stack">
                    <div class="panel__header">
                        <h2 class="heading-md">VERSIONING &amp; TAG PARAMETERS</h2>
                        <span class="mono-label">OPTIONAL</span>
                    </div>

                    <div class="field">
                        <label for="mod_id" class="field__label">PUBLISH AS NEW VERSION OF AN EXISTING MOD</label>
                        <select name="mod_id" id="mod_id" class="field__select">
                            <option value="">— AUTO-CREATE OR MATCH BY XML/JSON IDENTIFIER —</option>
                            @foreach ($developerMods as $devMod)
                                <option value="{{ $devMod->id }}" @selected((string) old('mod_id', $selectedModId) === (string) $devMod->id)>
                                    [{{ $devMod->game->short_code }}] {{ $devMod->name }} ({{ $devMod->identifier }})
                                </option>
                            @endforeach
                        </select>
                        <span class="field__hint">
                            If left blank, versions are automatically grouped by the identifier extracted from the manifest.
                        </span>
                    </div>

                    <div class="field">
                        <label for="tags" class="field__label">ADDITIONAL TAGS (COMMA-SEPARATED, MAX 8)</label>
                        <input type="text"
                               name="tags"
                               id="tags"
                               value="{{ is_array(old('tags')) ? implode(', ', old('tags')) : old('tags') }}"
                               placeholder="e.g. Heavy Tractors, Realism, Telemetry"
                               class="field__input">
                        <span class="field__hint">
                            Tags such as "Multiplayer", "Vehicles &amp; Equipment", "Fabric", or "Forge" are also inferred from the manifest.
                        </span>
                    </div>

                    <div class="field">
                        <label for="changelog" class="field__label">RELEASE NOTES (CHANGELOG)</label>
                        <textarea name="changelog"
                                  id="changelog"
                                  rows="3"
                                  placeholder="Describe the changes in this version..."
                                  class="field__textarea">{{ old('changelog') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn--block" data-upload-submit>
                        PARSE, VALIDATE &amp; SAVE TO STORAGE
                    </button>
                </div>
            </form>
        </div>

        <!-- Sidebar Column: Parser Engine Specifications -->
        <aside class="form-stack">
            <div class="panel panel--yellow">
                <div class="panel__header">
                    <h2 class="heading-md">VALIDATION RULES</h2>
                </div>
                <div class="form-stack" style="font-family: var(--font-mono); font-size: 0.8rem;">
                    <div>
                        <strong>[01] FARMING SIMULATOR (modDesc.xml)</strong>
                        <p style="margin-top: 0.25rem;">
                            Requires root <code>&lt;modDesc descVersion="..."&gt;</code>.
                            Automatically maps <code>40–59 &rarr; FS19</code>, <code>60–89 &rarr; FS22</code>, and <code>&ge;90 &rarr; FS25</code>.
                            Requires <code>&lt;author&gt;</code>, <code>&lt;version&gt;</code> (<code>X.Y.Z.W</code>), and <code>&lt;title&gt;</code>.
                        </p>
                    </div>
                    <div>
                        <strong>[02] MINECRAFT FABRIC (fabric.mod.json)</strong>
                        <p style="margin-top: 0.25rem;">
                            Requires <code>"schemaVersion": 1</code>, lowercase <code>"id"</code> (<code>^[a-z][a-z0-9_-]{1,63}$</code>),
                            and <code>"version"</code>. Extracts compatibility from <code>depends.minecraft</code>.
                        </p>
                    </div>
                    <div>
                        <strong>[03] MINECRAFT FORGE (mcmod.info)</strong>
                        <p style="margin-top: 0.25rem;">
                            Supports mod array or <code>modList</code> object with <code>modid</code>, <code>name</code>, <code>version</code>, and <code>mcversion</code>.
                        </p>
                    </div>
                    <div>
                        <strong>[04] XML ERROR QUARANTINE</strong>
                        <p style="margin-top: 0.25rem;">
                            Files with XML/JSON syntax errors are isolated under <code>_quarantine/</code> and listed in the Inventory Table with a <strong>VIVID RED</strong> badge and line/column report.
                        </p>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__header">
                    <h2 class="heading-md">STORAGE DISK LAYOUT</h2>
                </div>
                <pre style="font-family: var(--font-mono); font-size: 0.76rem; overflow-x: auto;">storage/app/mods/
├── farming-simulator/
│   └── {mod-slug}/
│       └── {version}/
│           └── FS25_Mod.zip
├── minecraft/
│   └── {mod-slug}/
│       └── {version}/
│           └── mod.jar
└── _quarantine/
    └── {YYYY}/{MM}/
        └── {sha256}-broken.xml</pre>
            </div>
        </aside>
    </div>
@endsection
