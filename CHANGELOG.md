# Changelog

## [1.1.1] - 2026-06-29

### Added
- `examples/lookup.php` runnable usage example.
- Integration tests covering the `get()` database-type adaptation and the country-DB `getCountryId()`/`getCountry()` path.

### Changed
- Documentation: corrected usage (absolute paths, real `about()` output), reworked `tests/fixtures/README.md`, and added a download link for the SypexGeo database.

## [1.1.0] - 2026-06-29

### Changed
- `$batch_mode` and `$memory_mode` properties are now `public` (were `protected`).
- Improved `composer.json` metadata: fixed homepage URL, added `support` section.

### Added
- PHPDoc for `getCountryId()` return value.

## [1.0.0] - 2026-06-28

### Added
- Initial PSR-4 release: modernized class with PHP 8.1–8.5 support.
- `SxGeo` reader with Country, City, City Max database support.
- File, memory and batch lookup modes.
- Full PHPUnit test suite (100% coverage).

[1.1.1]: https://github.com/globus-studio/SypexGeo/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/globus-studio/SypexGeo/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/globus-studio/SypexGeo/releases/tag/v1.0.0
