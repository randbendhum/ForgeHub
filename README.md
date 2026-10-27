# ForgeMod Hub — Mod Manager & XML/JSON Validator

Neo-Brutalist web portal built with **PHP 8.3, Laravel 11, MySQL 8.4, Vanilla JS, and Pure CSS3 Grid** for submitting, validating structural manifests (`modDesc.xml`, `fabric.mod.json`, `mcmod.info`), automatically extracting metadata, and managing versioned packages for **Farming Simulator (FS19/FS22/FS25)** and **Minecraft (Fabric & Forge)**.

Live Production Instance: **https://forgehub.humbertorandon.com**

---

## 1. Technical Architecture

- **Strict Upload & Security Validation:**
  - [`ValidateModUploadRequest`](./app/Http/Requests/ValidateModUploadRequest.php) & [`StoreModUploadRequest`](./app/Http/Requests/StoreModUploadRequest.php)
  - [`SafeArchive`](./app/Rules/SafeArchive.php) & [`ArchiveInspector`](./app/Services/ModParsing/ArchiveInspector.php): in-memory ZIP/JAR inspection with Path Traversal (`../`) blocking, entry count limits, and anti-Zip Bomb compression ratio checks.
- **PHP XML & JSON Parsing Engine:**
  - [`FarmingSimulatorModDescParser`](./app/Services/ModParsing/Parsers/FarmingSimulatorModDescParser.php): `DOMDocument` + `DOMXPath` with `libxml_use_internal_errors(true)`, `<!DOCTYPE`/`<!ENTITY` blocking (anti-XXE), line/column error reporting with surrounding code snippets, and `descVersion` compatibility mapping (`40–59 → FS19`, `60–89 → FS22`, `>=90 → FS25`).
  - [`FabricModJsonParser`](./app/Services/ModParsing/Parsers/FabricModJsonParser.php) & [`ForgeMcmodInfoParser`](./app/Services/ModParsing/Parsers/ForgeMcmodInfoParser.php): structural validation and dependency extraction for Minecraft mods.
- **Eloquent ORM Relational Schema:**
  - `Developer hasMany Mods`, `Mod belongsTo Game`, `Mod hasMany ModVersions`, `Mod belongsToMany Tags`, `ModVersion hasMany ValidationIssues`, `ModVersion belongsToMany GameVersions`.
- **Structured Storage Disk (`Storage::disk('mods')`):**
  - Validated packages: `storage/app/mods/{game-slug}/{mod-slug}/{version}/{filename}`
  - Packages with XML/JSON syntax errors: quarantined in `storage/app/mods/_quarantine/{YYYY}/{MM}/{sha256}-{filename}` and displayed in the **Mod Inventory Table** with a vivid red `XML/JSON SYNTAX ERROR` badge and full line/column report.

---

## 2. Production Deployment (Oracle Cloud E2.1.Micro / 1 GB RAM + Custom Domain)

```bash
sudo bash deploy/oracle-e2-setup.sh
docker compose -f docker-compose.prod.yml up -d --build
```

- **Demo Credentials:** `demo@forgemod.test` / `password`

---

## 3. Local Development & Automated Tests

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```
