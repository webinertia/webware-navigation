# Implementation Plan: Webware-Tools Alignment

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: `specs/[###-feature-name]/spec.md`

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

## Summary

Align the package's CI/CD pipeline and dev-tooling configuration with the
`webinertia/webware-tools` reusable workflow. The package provides a thin wrapper workflow with
package-specific inputs, aligned composer metadata, PHPUnit 13 strict configuration, Mago tooling
extending the shared config, Infection/Codecov/Renovate/PHPBench config files, and committed
`composer.lock`.

## Technical Context

**Language/Version**: PHP `~8.4.1 || ~8.5.0`

**Primary Dependencies**: PHPUnit `^13.3.0`, Infection `^0.34.1`, PHPBench `^1.7`,
roave/backward-compatibility-check `^8.21.0`, `webware/webware-tools` (dev, via reusable workflow
+ `mago.toml` extend)

**Storage**: [Package-specific: N/A for no-DB packages; reusable workflow DB steps skipped when
`db-image` omitted]

**Testing**: PHPUnit 13.1 (`unit test` and `integration test` suites), Infection mutation testing
with Mago as static analysis tool

**Target Platform**: Linux CI runners (GitHub Actions)

**Project Type**: PHP library

**Performance Goals**: N/A

**Constraints**: CI matrix `["8.4", "8.5"]`; `config.platform.php` = `8.4.99`; `min-msi` and
`min-covered-msi` = 95

**Scale/Scope**: Tooling alignment only (test scaffolding, not full coverage)

## Constitution Check

- **I. Webware-Tools CI Alignment** — wrapper workflow, required composer scripts, committed
  `composer.lock`, `mago.toml` extends vendor config: satisfied by this plan (Phases 1, 2, 3).
- **II. PHPUnit 13 Strict Mode** — `requireCoverageMetadata` + mock/stub rules: enforced via
  `phpunit.xml.dist` and `.github/copilot-instructions.md` (Phases 2, 5).
- **III. Code Quality Gates** — Mago clean + approved-only baselines; Infection MSI thresholds
  (Phases 3, 4).
- **IV. PHP Compatibility** — no `8.6.0-dev` in `require.php`; platform `8.4.99` (Phase 1).
- **V. Naming** — no namespace repetition: no new classes introduced by this plan.

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── spec.md              # Feature spec (package parameters filled in)
├── plan.md              # This file
└── tasks.md             # Task list
```

### Source Code (repository root) — artifacts this feature produces

```text
.github/
├── workflows/continuous-integration.yml   # wrapper calling reusable workflow
└── copilot-instructions.md                # PHPUnit 13 rules
phpunit.xml.dist                           # strict PHPUnit 13.1 config
mago.toml                                  # extends vendor/webware/webware-tools/mago.toml
lint-baseline.toml                         # starts empty
analysis-baseline.toml                     # starts empty
infection.json5.dist                       # mago staticAnalysisTool
codecov.yml                                # reference copy
renovate.json                              # local>webinertia/.github:renovate-config
phpbench.json.dist                         # runner config only
composer.json                              # scripts + require-dev aligned
composer.lock                              # generated + committed
README.md                                  # standard badge set
test/
├── unit/                                  # WebwareTest\<Package>\
└── integration/                           # WebwareTestIntegration\<Package>\
```

## Reference Mechanics (reusable workflow contract)

`webinertia/webware-tools/.github/workflows/continuous-integration.yml@X.Y.x` exposes
`workflow_call`:

- **Inputs**: `php-versions` (JSON array), `run-integration`, `composer-options`, `db-image`,
  `db-env-json`, `db-port`, `db-health-cmd`, `db-health-retries`, `db-health-interval-seconds`,
  `enable-codecov`, `enable-infection`, `coverage-php-version`, `min-msi`, `min-covered-msi`,
  `test-env-json`.
- **Secrets**: `CODECOV_TOKEN`, `INFECTION_DASHBOARD_API_KEY` (optional), forwarded via
  `secrets: inherit`.
- **Jobs**:
  1. **mago** — matrix over `php-versions`; runs `mago format --check`, `mago lint`,
     `mago analyze`, `mago guard` (each `success() || failure()`).
  2. **test** — matrix `php-versions` × `[lowest, locked, latest]`; optional DB container
     (skipped when `db-image` empty); `composer test` on non-canonical legs, `composer
     test-coverage` on the canonical leg (`coverage-php-version` + locked, pcov);
     `composer test-integration` when `run-integration`; uploads `clover.xml` artifact.
  3. **codecov** — needs `test`; `codecov/codecov-action@v5`, `files: clover.xml`,
     `fail_ci_if_error: false` (report-only).
  4. **mutation-test** — needs `test`; PHP `coverage-php-version` with pcov + `tools: mago`;
     `composer mutation-test -- --min-msi=… --min-covered-msi=… --logger-github`; Infection
     invokes Mago via `staticAnalysisTool`.

Consumer obligations derived from the contract:

- Required composer scripts must exist.
- `phpunit.xml.dist` must define suites named `unit test` and `integration test`.
- `composer.lock` must be committed.
- `mago.toml` must be present.
- Pipeline is red until at least one test exists per suite (PHPUnit errors on zero tests;
  Infection cannot score an empty suite).

## Implementation Phases

### Phase 1 — composer.json

- `require.php`: `~8.4.1 || ~8.5.0`. Do not add `8.6.0-dev`.
- `require-dev`: PHPUnit `^13.3.0`; add Infection `^0.34.1`, PHPBench `^1.7`,
  roave/backward-compatibility-check `^8.21.0`; keep existing package deps,
  `webware/webware-tools`, `roave/security-advisories`.
- `config.platform.php`: `8.4.99`.
- `autoload-dev`: `WebwareTest\<Package>\` → `test/unit/`; add
  `WebwareTestIntegration\<Package>\` → `test/integration/`.
- `scripts`:
  - `test`: `phpunit --no-coverage --colors=always --testsuite "unit test"`
  - `test-coverage`: `phpunit --colors=always --coverage-clover clover.xml --coverage-html
    coverage/html --coverage-text`
  - `test-integration`: `phpunit --no-coverage --colors=always --testsuite "integration test"`
  - `mutation-test`: `infection`
- No PHPStan package, no PHPStan script.
- Regenerate + commit `composer.lock`.

### Phase 2 — phpunit.xml.dist (new)

- Schema 13.1 (`https://schema.phpunit.de/13.1/phpunit.xsd`), `bootstrap="vendor/autoload.php"`,
  `colors="true"`, `cacheDirectory=".phpunit.cache"`.
- Strict flags: `requireCoverageMetadata="true"`, `failOnNotice="true"`,
  `failOnDeprecation="true"`, `failOnWarning="true"`.
- Testsuites: `unit test` → `test/unit`; `integration test` → `test/integration`.
- `<source restrictNotices="true" ignoreIndirectDeprecations="true">` including `src`.
- No bootstrap extensions, no env vars.

### Phase 3 — Mago tooling

- `mago.toml` (new):
  - `extends = "vendor/webware/webware-tools/mago.toml"`
  - `php-version = "8.4.1"`
  - `[linter] baseline = "lint-baseline.toml"`; `[analyzer] baseline = "analysis-baseline.toml"`
  - `[source] paths = ["src", "test"]`, `includes = ["vendor"]`
- `lint-baseline.toml` + `analysis-baseline.toml`: start empty; only maintainer-approved
  suppressions added.
- Fix pass: `mago format`, then `mago lint` + `mago analyze` + `mago guard`; fix all `src/`
  findings; baseline approved remainder only.

### Phase 4 — Infection / Codecov / Renovate / PHPBench configs

- `infection.json5.dist`: `source.directories = ["src"]`, `timeout = 10`, `threads = "max"`,
  logs `text: infection.log`, `summary: summary.log`, stryker badge regex `/^\d+\.\d+\.x$/`,
  `mutators: {"@default": true}`, `staticAnalysisTool: "mago"`.
- `codecov.yml`: reference copy (targets `auto`, threshold `0%`, comment layout
  `diff, flags, files`).
- `renovate.json`: `"extends": ["local>webinertia/.github:renovate-config"]`.
- `phpbench.json.dist`: `runner.path: benchmarks`, `*Bench.php`; config only, no
  `benchmarks/` directory, no CI job.

### Phase 5 — Workflow wrapper + agent instructions + test scaffolding

- `.github/workflows/continuous-integration.yml`: wrapper mirroring the reference with package
  inputs:
  - `on`: `pull_request` → branches `[0-9]+.[0-9]+.x`; `push` → same branches + tags
    `[0-9]+.[0-9]+.[0-9]+`.
  - `uses: webinertia/webware-tools/.github/workflows/continuous-integration.yml@0.1.x`
  - `secrets: inherit`
  - `with`: package parameters from spec (PHP versions, integration, codecov, infection flags,
    coverage version, MSI thresholds); omit DB inputs when no database.
- `.github/copilot-instructions.md`: PHPUnit 13 mock-vs-stub rules (`createStub()` for
  value-returning doubles, `createMock()` only with `expects()`) and
  `requireCoverageMetadata="true"` rules (`#[CoversClass]` / `#[CoversMethod]` per test class).
- `test/`: scaffolding with at least one test per suite so the pipeline is green (PHPUnit
  errors on zero executed tests; Infection cannot score an empty suite).
- `.gitattributes`: add `/.specify/` and `/specs/` to the `export-ignore` list; `/.github/`
  is already ignored, which covers agent skill dirs.

### Phase 6 — README badges

- `README.md`: standard badge set matching `webware/webware-message`: PHP version, latest
  version, license, Continuous Integration, codecov, Mutation testing. CI and codecov badge URLs
  carry no `?branch=` parameter, so they always point at the default branch. The Stryker mutation
  badge URL embeds the branch segment; update that segment in both the badge URL and the
  dashboard link whenever the default branch changes.

## Complexity Tracking

No constitution violations.
