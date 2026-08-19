# Feature Specification: Webware-Tools Alignment

**Feature Branch**: `[###-feature-name]`

**Created**: [DATE]

**Status**: Draft

**Input**: User description: "Align [PACKAGE]'s CI/CD pipeline and dev tooling with the
webinertia/webware-tools reusable workflow"

## Purpose

Every Webware package runs the same CI/CD pipeline and dev tooling, owned centrally by
`webware/webware-tools` and consumed through a thin per-package wrapper. This spec defines the
consumer-side contract: which artifacts a package must provide, what shape they take, and which
values are package-specific parameters.

## Scope

**In scope:** CI/CD pipeline, tooling configs, composer metadata, baseline files, and the minimum
test scaffolding required for a green pipeline.

**Out of scope (explicit):**

- PHPStan: `phpstan.neon.dist`, `stubs/`, type-coverage packages. The reusable workflow runs no
  PHPStan job.
- Local Docker dev tooling: install scripts, `compose.yml`, `docker/` (not referenced by CI).
- Full test suite coverage; only scaffolding sufficient to keep the pipeline green is required.

## Package Parameters

Fill in before planning. See webware-mailer's `specs/001-webware-tools-alignment/spec.md` for the
reference instance.

| Parameter | [PACKAGE] value |
|---|---|
| `php-versions` | `["8.4", "8.5"]` |
| `require.php` | `~8.4.1 \|\| ~8.5.0` |
| `config.platform.php` | `8.4.99` |
| `run-integration` | [true/false] |
| `enable-codecov` | `true` |
| `enable-infection` | `true` |
| `coverage-php-version` | highest supported PHP |
| `min-msi` / `min-covered-msi` | `95` / `95` |
| DB container (`db-image`) | [omitted unless package needs a database] |
| Integration container | [e.g. Mailpit, MySQL, omitted] |
| Test autoload namespaces | `WebwareTest\<Package>\` → `test/unit/`, `WebwareTestIntegration\<Package>\` → `test/integration/` |

## User Scenarios & Testing

### User Story 1 - Maintainer opens a PR and gets a full pipeline (Priority: P1)

A maintainer opens a pull request against a release branch. The wrapper workflow triggers the
reusable workflow, which runs Mago checks, the test matrix (lowest/locked/latest dependency
strategies), integration tests, Codecov upload, and Infection mutation testing.

**Why this priority**: The pipeline is the deliverable; nothing else in this spec has value
without it.

**Independent Test**: Open a PR touching `src/`; all CI jobs are scheduled and pass on a clean
change.

**Acceptance Scenarios**:

1. **Given** a PR against a `X.Y.x` branch, **When** pushed, **Then** Mago, test, codecov, and
   mutation-test jobs all run.
2. **Given** a PR with a Mago lint finding, **When** pushed, **Then** the Mago job fails and
   reports the finding.
3. **Given** a change that drops a test suite to zero tests, **When** pushed, **Then** the test
   job fails (PHPUnit errors on zero executed tests).

### User Story 2 - Tooling updates propagate with a version bump (Priority: P2)

When `webware/webware-tools` releases a new workflow version, consumers update the pinned ref and
regenerate baselines, without rewriting per-package config.

**Why this priority**: Ongoing maintenance loop.

**Independent Test**: Bump the pinned workflow ref; pipeline still green after `mago` fix pass.

**Acceptance Scenarios**:

1. **Given** a new webware-tools version, **When** the wrapper ref is bumped, **Then** only the
   wrapper and possibly baselines change.

### Edge Cases

- No `db-image` set: both DB steps of the reusable workflow are skipped at zero cost.
- Integration tests do not exist yet: `test-integration` leg runs an empty suite; acceptable
  temporarily, but at least one test per suite is required for a green pipeline.
- Zero tests: PHPUnit 13 errors, and Infection cannot score an empty suite; pipeline is red until
  scaffolding tests exist.

## Requirements

### Functional Requirements

- **FR-001**: Repository MUST provide `.github/workflows/continuous-integration.yml` calling
  `webinertia/webware-tools/.github/workflows/continuous-integration.yml` with `secrets: inherit`
  and package-specific inputs.
- **FR-002**: `composer.json` MUST define scripts `test`, `test-coverage`, `test-integration`,
  and `mutation-test`.
- **FR-003**: `phpunit.xml.dist` MUST use PHPUnit 13.1 schema, strict flags
  (`requireCoverageMetadata`, `failOnNotice`, `failOnDeprecation`, `failOnWarning`), and suites
  named `unit test` and `integration test`.
- **FR-004**: `mago.toml` MUST extend `vendor/webware/webware-tools/mago.toml` and define
  `php-version`, baseline paths, and source paths.
- **FR-005**: `lint-baseline.toml` and `analysis-baseline.toml` MUST start empty; entries only for
  maintainer-approved intentional suppressions.
- **FR-006**: `infection.json5.dist` MUST configure `source.directories = ["src"]` and
  `staticAnalysisTool: "mago"`.
- **FR-007**: `codecov.yml` MUST match the reference consumer (targets `auto`, threshold `0%`).
- **FR-008**: `renovate.json` MUST extend `local>webinertia/.github:renovate-config`.
- **FR-009**: `phpbench.json.dist` MUST exist with runner config; a `benchmarks/` directory is not
  required.
- **FR-010**: `composer.lock` MUST be committed (locked matrix leg).
- **FR-011**: `.github/copilot-instructions.md` MUST carry PHPUnit 13 mock-vs-stub and coverage
  metadata rules.
- **FR-012**: Each test suite MUST contain at least one test.
- **FR-013**: `README.md` MUST carry the standard badge set (PHP version, latest version,
  license, CI, codecov, mutation testing) with CI/codecov badges tracking the default branch and
  the Stryker badge updated whenever the default branch changes.
- **FR-014**: Repository MUST add spec-kit scaffolding directories (`/.specify/`, `/specs/`) to
  `.gitattributes` `export-ignore` so distro packages exclude them.

### Key Entities

- **Wrapper workflow**: per-package file translating package parameters into reusable workflow
  inputs.
- **Reusable workflow**: `webinertia/webware-tools@X.Y.x`; owns job definitions (mago, test,
  codecov, mutation-test).
- **Baselines**: per-package TOML files holding approved Mago suppressions.

## Success Criteria

### Measurable Outcomes

- **SC-001**: All CI jobs green on the canonical branch: Mago (all versions), test matrix,
  codecov, mutation-test.
- **SC-002**: `mago format --check`, `mago lint`, `mago analyze`, `mago guard` report zero
  unbaselined issues.
- **SC-003**: Infection MSI and covered MSI at or above package thresholds (95 reference).
- **SC-004**: Codecov receives coverage upload from exactly one matrix leg (canonical:
  `coverage-php-version` + locked).

## Assumptions

- The reusable workflow keeps its documented inputs until a deliberate version bump.
- Test scaffolding (not full test coverage) is sufficient for alignment scope.
