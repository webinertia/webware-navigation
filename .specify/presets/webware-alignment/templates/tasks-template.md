# Tasks: Webware-Tools Alignment

**Input**: `specs/[###-feature]/spec.md`, `plan.md`

Replace package parameters (PHP versions, MSI thresholds, test namespaces) before executing.

## Phase 1 — composer.json

- [ ] T001 Align `composer.json` `require.php` to `~8.4.1 || ~8.5.0` (no `8.6.0-dev`), set
  `config.platform.php` to `8.4.99` in `composer.json`
- [ ] T002 Align `require-dev`: PHPUnit `^13.3.0`, add `infection/infection: ^0.34.1`,
  `phpbench/phpbench: ^1.7`, `roave/backward-compatibility-check: ^8.21.0`; keep package deps,
  `webware/webware-tools`, `roave/security-advisories` in `composer.json`
- [ ] T003 Set `autoload-dev` namespaces: `WebwareTest\<Package>\` → `test/unit/`,
  `WebwareTestIntegration\<Package>\` → `test/integration/` in `composer.json`
- [ ] T004 Define scripts `test`, `test-coverage`, `test-integration`, `mutation-test` in
  `composer.json`
- [ ] T005 Run `composer update` and commit `composer.lock`

## Phase 2 — phpunit.xml.dist

- [ ] T006 Create `phpunit.xml.dist`: PHPUnit 13.1 schema, `bootstrap="vendor/autoload.php"`,
  strict flags (`requireCoverageMetadata`, `failOnNotice`, `failOnDeprecation`, `failOnWarning`),
  suites `unit test` → `test/unit` and `integration test` → `test/integration`,
  `<source>` including `src`
- [ ] T007 Add test scaffolding: at least one test in `test/unit/` and one in
  `test/integration/` so PHPUnit and Infection do not error on empty suites

## Phase 3 — Mago tooling

- [ ] T008 Create `mago.toml`: `extends = "vendor/webware/webware-tools/mago.toml"`,
  `php-version = "8.4.1"`, linter/analyzer baseline paths, source paths `["src", "test"]`
- [ ] T009 Create empty `lint-baseline.toml` and `analysis-baseline.toml`
- [ ] T010 Fix pass: run `mago format`, `mago lint`, `mago analyze`, `mago guard`; fix all
  findings in `src/`; baseline only maintainer-approved remainder

## Phase 4 — Infection / Codecov / Renovate / PHPBench configs

- [ ] T011 Create `infection.json5.dist`: `source.directories = ["src"]`, `timeout = 10`,
  `threads = "max"`, `staticAnalysisTool: "mago"`, stryker badge regex
- [ ] T012 Create `codecov.yml` copying the reference consumer verbatim
- [ ] T013 Create `renovate.json` with `"extends": ["local>webinertia/.github:renovate-config"]`
- [ ] T014 Create `phpbench.json.dist` with runner config (`runner.path: benchmarks`,
  `*Bench.php`); no `benchmarks/` directory required

## Phase 5 — Workflow wrapper + agent instructions

- [ ] T015 Create `.github/workflows/continuous-integration.yml` wrapper calling
  `webinertia/webware-tools/.github/workflows/continuous-integration.yml@0.1.x` with
  `secrets: inherit` and package inputs (`php-versions`, `run-integration`, `enable-codecov`,
  `enable-infection`, `coverage-php-version`, `min-msi`, `min-covered-msi`)
- [ ] T016 Create `.github/copilot-instructions.md` with PHPUnit 13 mock-vs-stub and coverage
  metadata rules
- [ ] T017 Add `/.specify/` and `/specs/` to `.gitattributes` `export-ignore` so spec-kit
  scaffolding never ships in distro packages (`/.github/` is already ignored and covers agent
  skill dirs)

## Phase 6 — README badges

- [ ] T018 Add README badges matching `webware/webware-message`: PHP version, latest version,
  license, Continuous Integration, codecov, and Mutation testing. CI and codecov badge URLs carry
  no `?branch=` parameter, so they always point at the default branch. The Stryker mutation badge
  URL embeds the branch segment; update that segment in both the badge URL and the dashboard link
  whenever the default branch changes. Update `README.md`

## Verification

- [ ] T019 Run full local check: `mago format --check && mago lint && mago analyze && mago
  guard`, `composer test`, `composer test-coverage`, `composer test-integration`,
  `composer mutation-test`
- [ ] T020 Push branch, open PR, confirm all CI jobs green (mago, test matrix, codecov,
  mutation-test)
