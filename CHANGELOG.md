# Changelog

## 0.1.3 - 2026-11-25

- Credits of the Moodle Observability family; first public release.

## 0.1.2 - 2026-11-24

- Vimeo: when oEmbed answers 404, the player (with the site as Referer) tells a deleted video (`removed`) from a
  private or password protected one (`private`).
- On demand analyses record the HTTP requests, the time waiting for the providers and the slowest request; the
  `verify_resources` task logs them per provider.

## 0.1.1 - 2026-11-24

- The installation flags every course for scanning, so the first scan does not wait for the weekly full sweep.

## 0.1.0 - 2026-11-23

- Incremental scan of the course texts, flagged by events, with a weekly safety sweep.
- Checks with a rate limit per provider, a pause on 429, N confirmations and a history of status changes.
- Providers (subplugins `extdepsprovider`): YouTube, Vimeo, eduplay and SCORM (local and external packages, videos
  inside the packages).
- On demand analysis (external services, one ad hoc task per request with a lock, CLI `analyze.php`) with verdicts
  and evidence, `--dry-run`, `--strict` and `--compare`.
- Alerts through `local_alerthub` and metrics through `tool_prometheus`, both optional.
- Accepted false positives, Privacy API.
