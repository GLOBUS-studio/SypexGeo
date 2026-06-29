# AGENTS.md

Pure-PHP reader for the SypexGeo binary IP-to-geo database. One library class, no runtime deps.

## Commands

- Test: `composer test` (alias for `vendor/bin/phpunit`). Run a single test with `vendor/bin/phpunit --filter <TestName>`.
- CI also runs `composer validate --strict` — keep `composer.json` valid when editing it.
- There is **no** lint / formatter / static-analysis / typecheck tooling. Do not invent one or assume phpstan/php-cs-fixer exists.
- PHP `^8.1`; CI matrix is 8.1–8.5.

## Layout

- All reader logic lives in a single file: `src/SxGeo.php` (class `GlobusStudio\SypexGeo\SxGeo`).
- Public property `$id2iso` (the ISO country table) is part of the API and is asserted directly in tests.
- Tests reach internals via reflection: protected `unpack()`/`parseCity()` and private props `pack`/`info`/`max_city`. Renaming these breaks tests even though they aren't public.

## Database fixtures (the main gotcha)

- All `*.dat` files are git-ignored (`.gitignore`); only `tests/fixtures/README.md` is tracked. A ~38 MB `tests/fixtures/SxGeoCity.dat` is present locally but untracked — do not assume it exists in a fresh clone, and never commit a `.dat`.
- `SxGeoUnitTest` needs no database. `SxGeoIntegrationTest` and `SxGeoCoverageTest` auto-skip when no `.dat` is found, so a green run does not prove they executed.
- `SxGeoCoverageTest` hard-codes `tests/fixtures/SxGeoCity.dat` and asserts behaviour of **specific IPs/blocks in that exact fixture** (e.g. `1.17.0.1` country-only, `4.4.4.4` wide block, `11.0.0.1` single-entry). Swapping in a different city DB will break coverage tests, not just skip them.
- `SxGeoIntegrationTest` resolves a DB from, in order: `SXGEO_DB` env var, `tests/fixtures/{SxGeo,SxGeoCity,SxGeoCityMax}.dat`, then `./SxGeo.dat`.
- Fetch a DB with `composer fetch-db -- <url>` (direct `.dat` or `.zip` containing one). It writes `tests/fixtures/SxGeo.dat` and refuses to overwrite without `--force`. In CI it only runs if the `SXGEO_URL` secret is set.

## Behaviour notes

- IPv4 only. Private/reserved ranges (`10/8`, `127/8`, `0/8`) intentionally return `false`/empty.
- Constructor throws `\RuntimeException` on missing file or bad signature/format; relative paths resolve against `src/`.
- Lookup modes are bit flags: `MODE_FILE=0`, `MODE_MEMORY=1`, `MODE_BATCH=2` (combinable).

## Working with GitHub

`gh` isn't logged in on this machine. Pull the token from Credential Manager into
`$env:GH_TOKEN` **and run `gh` in the same shell block** (the env var doesn't survive
across invocations). Never echo or commit the token.

```powershell
$cred = "protocol=https`nhost=github.com`n" | git credential-manager get 2>$null
$env:GH_TOKEN = (($cred | Where-Object { $_ -match '^password=' }) -replace '^password=', '')
gh release create vX.Y.Z --title "vX.Y.Z" --notes-file notes.md
```

Release: `composer test` green → CHANGELOG entry committed → `git tag -a vX.Y.Z` →
push `main` + tag → `gh release create` (notes via `--notes-file`).