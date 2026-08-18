# [PROJECT_NAME] Constitution

## Core Principles

### I. Webware-Tools CI Alignment

CI/CD pipeline and dev tooling follow the `webinertia/webware-tools` reusable workflow. The
repository provides a thin wrapper workflow with package-specific inputs and `secrets: inherit`.
Composer scripts `test`, `test-coverage`, `test-integration`, and `mutation-test` exist and match
what the reusable workflow invokes. `composer.lock` is committed (required by the `locked` matrix
leg). `mago.toml` extends `vendor/webware/webware-tools/mago.toml`; the consumer file defines only
`php-version`, baseline paths, and source paths.

### II. PHPUnit 13 Strict Mode

- `requireCoverageMetadata="true"`: every test class declares `#[CoversClass]` for each source
  class and `#[CoversMethod]` for each method exercised.
- Mock vs stub separation: `createStub()` for value-returning test doubles; `createMock()` only
  when behavior is verified with `expects()`. Never `createMock()` without `expects()`.
- `failOnNotice="true"`, `failOnDeprecation="true"`, `failOnWarning="true"` — no loose PHPUnit
  notices in CI.

### III. Code Quality Gates

- Mago `format`, `lint`, `analyze`, `guard` run in CI; findings are fixed in source, never
  silently suppressed. Baseline entries require explicit maintainer approval per issue.
- Infection runs with Mago as `staticAnalysisTool`; `min-msi` and `min-covered-msi` start at 95
  and may only be lowered with justification.

### IV. PHP Compatibility

- Support current supported PHP versions only (`~8.4.1 || ~8.5.0`); `config.platform.php` pinned
  to `8.4.99` for dependency resolution.
- No `8.6.0-dev` in `require.php`.

### V. Naming

- Never repeat the namespace in class or interface names. `Webware\Input` namespace has
  `FilterInterface`, not `InputFilterInterface`. Applies to all new code.

## Quality Gates

Every pull request passes, on all CI matrix legs:

- Mago format check, lint, analyze, guard
- Unit tests under lowest/locked/latest dependency strategies
- Integration tests when `run-integration` is set
- Codecov upload from the canonical coverage leg (report-only)
- Infection mutation score at or above configured MSI thresholds

## Governance

- Constitution supersedes other practices; conflicts are resolved in its favor or the
  constitution is amended via PR.
- Wrapper workflow inputs change only when the reusable workflow version bumps or a deliberate
  policy decision is recorded in a spec.
- `.github/copilot-instructions.md` carries the operational rules derived from this constitution.

**Version**: [CONSTITUTION_VERSION] | **Ratified**: [RATIFICATION_DATE] | **Last Amended**: [LAST_AMENDED_DATE]
