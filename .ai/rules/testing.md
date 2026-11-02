---
paths:
  - phpunit.xml
  - tests/**
---

# Testing

## Windows process env can hijack the test environment
This machine exports `APP_ENV=local`, `DB_CONNECTION=mysql`, `DB_DATABASE=telfmaps`, etc. as real process env vars. PHPUnit `<env>` entries do NOT override them (Laravel's Env repository reads `$_SERVER` first, which inherits process env). Symptom: tests hit the real MySQL dev DB, `RefreshDatabase` wipes it, and POST tests 419 because CSRF stays on. Fix (already applied): every `<env>` in phpunit.xml has a matching `<server>` entry with `force="true"` — keep the pairs in sync when adding new ones. If tests ever touch `telfmaps` again or 419 for no reason, this regressed.

## Test uploads need real bytes
`UploadedFile::fake()->image()->size(N)` only fakes the *reported* size; Livewire's temporary-upload round trip re-reads the real file (~1 KB), so oversize tests silently pass. Pad the fake's real temp file instead (see `paddedImage()` in tests/Feature/ImageUploadSizeTest.php).
