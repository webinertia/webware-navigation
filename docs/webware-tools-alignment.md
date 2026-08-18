# Webware Tools Alignment — webware-navigation (Audit)

## Purpose

Audit of `webware/webware-navigation` against the CI/CD and dev-tooling shape
established by `webware/webware-tools` (reusable workflow
`webinertia/webware-tools@0.1.x`). The reference for this audit is the
webware-mailer alignment (`webware-mailer/docs/webware-tools-alignment.md`) —
mailer is the aligned consumer and the template this repo adopts.

**Scope:** CI/CD pipeline, tooling configs, composer metadata, baseline files,
and the minimum test scaffolding required for a green pipeline.

**Explicitly out of scope (same as webware-mailer):**

- PHPStan (`phpstan.neon.dist`, stubs, type-coverage, PHPStan deps).
- Local Docker dev tooling (`bin/install-deps.sh`, `compose.yml`, `docker/`).
- Full test suite for all classes — only scaffolding / minimum required to
  keep the pipeline green.
- Benchmarks (`benchmarks/*Bench.php`) — config only.

## Current state

Branch `0.1.x` (matches the `[0-9]+.[0-9]+.x` branch pattern the workflow
triggers on). `git ls-files` shows `LICENSE` as the only tracked file;
everything else (src, tests, docs, configs) is untracked — same pre-alignment
state mailer started from. `composer install` has been run this session, so
`vendor/` and a fresh `composer.lock` now exist locally.

| Artifact | webware-mailer (reference) | webware-navigation (current) | Action |
|---|---|---|---|
| `.github/workflows/continuous-integration.yml` | wrapper calling webware-tools | absent | create |
| `.github/copilot-instructions.md` | present | absent | port |
| `phpunit.xml.dist` | PHPUnit 13.1 schema, suites `unit test` / `integration test`, `ignoreIndirectDeprecations="true"` | **monorepo leftover** — 16 suites, none named `unit test`/`integration test`; suites point at `src/webware-{core,acl,admin,navigation,usermanager,configmanager}/test/*`; `<source>` missing `ignoreIndirectDeprecations` | rewrite to mailer shape |
| `mago.toml` | extends webware-tools, `php-version = "8.4.1"`, baselines | created this session (untracked) | verify, keep |
| `lint-baseline.toml` / `analysis-baseline.toml` | present (3 approved lint issues, empty analysis) | created empty this session | keep empty until fix pass |
| `infection.json5.dist` | present | absent | create |
| `codecov.yml` | present | absent | copy |
| `renovate.json` | present | absent | copy |
| `phpbench.json.dist` | present (no benchmarks dir) | absent | copy (config only) |
| `composer.lock` | committed | absent (generated locally, untracked) | commit |
| `composer.json` scripts | `test`, `test-coverage`, `test-integration`, `mutation-test` | **none** | add |
| require-dev | infection, phpbench, phpunit `^13.3.0`, roave/backward-compatibility-check, roave/security-advisories, webware-tools | phpunit `^13.3.0` ✓, webware-tools ✓, roave/security-advisories ✓, laminas-permissions-acl; **missing** infection, phpbench, backward-compatibility-check | add |
| `config.platform.php` | `8.4.99` | absent | add |
| `config.allow-plugins` | laminas-component-installer, package-versions-deprecated, infection/extension-installer | absent | add as applicable |
| `extra.laminas.config-provider` | present | absent | add (`Webware\Navigation\ConfigProvider`) |
| autoload-dev namespaces | `WebwareTest\<Pkg>\` + `WebwareTestIntegration\<Pkg>\` | `Webware\NavigationTest\` + `Webware\NavigationIntegrationTest\` | rename to mailer pattern |
| `require.php` | `~8.4.1 \|\| ~8.5.0` | `~8.4.0 \|\| ~8.5.0` | align |
| `.gitattributes` / `.gitignore` | mago/phpbench/infection/codecov export-ignore set | php-cs-fixer, phpstan, k6 leftovers; missing mago/phpbench/infection/codecov entries | rewrite to mailer set |
| test suites | both populated | `test/unit` + `test/integration` exist with `.gitkeep` only | keep scaffold; suite is a later step |

## Probe results (mago runs, this session)

`mago` 1.46.0, config `mago.toml` as created (extends webware-tools base,
`php-version = "8.4.1"`):

- `mago format --check` — **6 files need formatting** (list TBD at fix pass).
- `mago lint` — **28 issues**: 3 error, 5 warning, 20 help.
  - errors: `cyclomatic-complexity`, `kan-defect`, `too-many-methods` (all in
    `src/NavigationContainer.php`)
  - warnings: `no-isset` (×2), `literal-named-argument` (×2),
    `no-shorthand-ternary` (×1)
  - helps: `yoda-conditions` (×10), `string-style` (×5), `no-else-clause`
    (×2), `no-negated-ternary` (×1), `ambiguous-function-call` (×1)
- `mago analyze` — **49 issues**: 31 error, 16 warning, 2 help. Dominant
  clusters:
  - `non-existent-use-import` / `non-existent-class-like` (≈20) for
    `Webware\Acl\Acl`, `Webware\Acl\AclInterface`,
    `Webware\UserManager\UserInterface` — **blocker**, see Findings.
  - `unhandled-thrown-type` (×10) — `Psr\Container\*ExceptionInterface` in the
    three `__invoke` factories.
  - `side-effects-in-condition` (×7), `mixed-assignment` (×4),
    `imprecise-type` (×3), `less-specific-argument` (×2),
    `possibly-invalid-argument` (×3), `invalid-method-access` (×1),
    `redundant-docblock-type` (×1), `unused-parameter` (×2).
- `mago guard` — no-op. Base webware-tools config defines no guard rules
  (perimeter and structural checks report "skipped due to missing
  configuration"). Nothing to do here; matches mailer.

## Findings

### F1 — Undeclared package dependencies — RESOLVED (Option 3)

`src/` imports `Webware\Acl\Acl`, `Webware\Acl\AclInterface`, and
`Webware\UserManager\UserInterface`, but no composer package provides them
(verified against the IMS monorepo: neither module has a standalone repo).

**Resolution: keep the coupling.** Tooling-only alignment (Option 3). The
package is private to IMS, consumed via a VCS repository entry, never published
to Packagist. `mago analyze` stays red on the ≈20 unresolved-symbol errors and
the test jobs stay red until `webware-acl` + `webware-usermanager` publish as a
pair. Full analysis and rejected alternatives: see Research section.

### F2 — phpunit.xml.dist is a monorepo leftover

16 suites, 6 of which are sibling-component suites (`webware-core`,
`webware-acl`, `webware-admin`, `webware-usermanager`,
`webware-configmanager`, plus `default` → `test/AppTest`, `async` →
`test/AsyncTest`) that do not exist in this standalone repo. None of the
suites is named `unit test` or `integration test`, which is what the reusable
workflow's `composer test` / `test-coverage` / `test-integration` scripts
select. Strict flags are already correct; missing
`ignoreIndirectDeprecations="true"` on `<source>`.

### F3 — composer.json gaps

- No `scripts` section (workflow jobs call `composer test`,
  `test-coverage`, `test-integration`, `mutation-test`).
- require-dev missing `infection/infection: ^0.34.1`,
  `phpbench/phpbench: ^1.7`, `roave/backward-compatibility-check: ^8.21.0`.
- No `config.platform.php` (mailer pins `8.4.99` for lock resolution).
- No `config.allow-plugins` (`infection/extension-installer` needed once
  Infection is added).
- No `extra.laminas.config-provider` despite shipping a `ConfigProvider`.
- autoload-dev namespace convention differs from the mailer/log pattern
  (`Webware\NavigationTest\` vs `WebwareTest\Navigation\` /
  `WebwareTestIntegration\Navigation\`); test namespaces in `docs/testing.md`
  are silent on this, so no code depends on it yet.
- `require.php` is `~8.4.0 || ~8.5.0` (mailer: `~8.4.1 || ~8.5.0`).

### F4 — Repo-level tool configs absent

`infection.json5.dist`, `codecov.yml`, `renovate.json`, `phpbench.json.dist`
all missing. Straight copies of the mailer files (infection badge regex
`/^\d+\.\d+\.x$/` matches the `0.1.x` branch).

### F5 — .gitignore / .gitattributes stale

Navigation versions carry php-cs-fixer / phpstan / k6 entries and are missing
the mago/phpbench/infection/codecov exports mailer ships. Align to mailer set.

### F6 — CI wrapper + copilot-instructions absent

No `.github/` at all. Wrapper mirrors mailer verbatim except inputs:
navigation integration tests run against laminas-view + mezzio-router in
process — no DB image needed (`db-image` omitted). PHPUnit 13
mock-vs-stub and `requireCoverageMetadata` rules (already present in the
mailer attachment) must be ported to this repo's own
`.github/copilot-instructions.md`.

### F7 — Empty suites are expected-red until the test-suite step

`test/unit` + `test/integration` contain only `.gitkeep`. PHPUnit 13 errors on
zero executed tests and Infection cannot score an empty suite, so `test` and
`mutation-test` jobs stay red until tests land. `mago` and `codecov` jobs are
independent of test content. Same accepted-risk posture as mailer's D1.

### F8 — Docs carry monorepo leftovers

- `README.md`: "Registered as a path-repository package" and "The PSR-4
  namespace `Webware\Navigation\` maps to `src/webware-navigation/src/`" —
  stale monorepo instructions.
- `docs/testing.md`: examples type against `Webware\Acl\AclInterface`
  (see F1) and use `final class` test names without namespace guidance
  matching the planned autoload-dev rename (F3).
- Docs refresh depends on F1/F3 decisions; flagging, not fixing.

## Research: interconnection with webware-acl / webware-usermanager (2026-08-17)

### Sources

- App monorepo: `/home/jsmith/github.com/tyrsson/inventory-management-system`
- Migration plan: `docs/module/component-migration-plan.md` (§6.6 navigation,
  §10.8 extraction order, §12 release availability matrix)

### In-tree parity

- `src/webware-navigation/src` in the app is byte-identical to this repo's
  `src/` (verified via `diff -rq` — clean).
- In-tree `src/webware-navigation/composer.json` declares `webware/acl: ^0.1`
  and `mezzio/mezzio-authentication: ^1.13`; it does **not** declare
  `webware/usermanager` — the monorepo autoload hides that hole. The standalone
  split dropped both entries.

### Runtime dependency surface

- `Webware\Acl\Acl` (`src/webware-acl/src/Acl.php`):
  `final class Acl extends Laminas\Permissions\Acl\Acl implements AclInterface`.
  Overrides `isAllowed` with three behaviors navigation depends on:
  1. **Fail-closed** — unregistered resource returns `false` (source comment:
     "FAIL CLOSED — intentional, do not change to true").
  2. **DB rule load** — `load()` pulls rules from `RuleRepository`, role
     registry from `RoleRepository`, registers route names as resources from
     `RouteCollectorInterface`, adds Developer allow-all.
  3. **Multi-role** — `Webware\Acl\Role\UserRoleIterator` iterates
     `UserInterface::getRoles()`, any-match wins.
- `Webware\Acl\AclInterface` — navigation uses it only as a type hint,
  request-attribute key, and container key. Its own method
  `isAllowedRoute(?UserInterface, ResourceInterface)` is a thin wrapper over the
  `isAllowed` override; navigation never calls it — it calls
  `$acl->isAllowed($user, $route->getName(), null)` directly.
- `Webware\UserManager\UserInterface`
  (`src/webware-usermanager/src/UserInterface.php`) extends
  `Laminas\Permissions\Acl\Role\RoleInterface`, `ResourceInterface`,
  `ProprietaryInterface`, `WithRowDataPrototypeInterface`. Navigation uses it
  only as a role carrier passed to `isAllowed`.

### App wiring

- `config/pipeline.php`: `IdentityMiddleware` (line 39) → `NavigationMiddleware`
  (line 80) → `AclMiddleware` (line 84).
- Navigation is piped **before** `AclMiddleware`, so the `AclInterface::class`
  attribute is not yet set when `NavigationMiddleware` runs; the helper's ACL
  comes from the container via `NavigationFactory` and persists across requests
  (`resetState()` deliberately does not reset `$acl`).
- Attribute setters: `src/webware-usermanager/src/Middleware/IdentityMiddleware.php`
  line 73 (`UserInterface::class`), `src/webware-acl/src/Middleware/AclMiddleware.php`
  line 22 (`AclInterface::class`).

### Mutual entanglement

- `webware-acl` imports `Webware\UserManager\UserInterface`; `webware-usermanager`
  imports acl. The two must publish as a **pair** — the migration plan's linear
  order (§10.8: acl → admin → navigation → usermanager) does not capture this.
- Migration plan §12: neither `webware/webware-acl` nor
  `webware/webware-usermanager` has a standalone repo; neither is on Packagist.

### Alternatives considered and rejected

- **Plain-Laminas decouple** (`Laminas\Permissions\Acl\AclInterface` +
  `RoleInterface`): call site is identical, but loses fail-closed, DB rule
  loading, and multi-role resolution — security-relevant behavior change.
  Rejected.
- **Navigation-local contract + app adapter**: preserves semantics and
  unblocks standalone CI, but invents a throwaway contract for a temporary
  package. Deferred — per strategy of not reinventing the wheel until
  laminas-navigation lands.

### Consumption plan (final)

- Package is private to IMS. Never published to Packagist.
- Consumer pulls via a VCS repository entry:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/webinertia/webware-navigation" }
  ],
  "require": {
    "webware/webware-navigation": "0.1.x-dev"
  }
}
```

- `composer update webware/webware-navigation` after each commit here (lock
  pins the commit hash).
- IMS autoloads `Webware\Acl\` + `Webware\UserManager\` itself — symbols
  unresolved here are resolved there.
- Later: when laminas-navigation releases, an integration layer fills the gaps
  for IMS and this package is retired.

### Consequence for this repo

- `composer install` stays green (no undeclared requires).
- `mago analyze` stays red on ≈20 unresolved-symbol errors; `test` / coverage /
  mutation stay red until the acl + usermanager pair publishes. Accepted.
- `mago format`, `mago lint`, `mago guard` fixes proceed normally.

## Work items (after F1 decision)

1. `composer.json`: F3 changes; run `composer update`; commit `composer.lock`
   (the `locked` matrix leg requires it).
2. `phpunit.xml.dist`: rewrite to mailer shape — two suites
   (`unit test` → `test/unit`, `integration test` → `test/integration`),
   `<source restrictNotices="true" ignoreIndirectDeprecations="true">`.
3. Tool configs: `infection.json5.dist`, `codecov.yml`, `renovate.json`,
   `phpbench.json.dist` (copies); `mago.toml` + baselines already created —
   baselines stay empty until the fix pass; suppressions only with explicit
   approval per issue.
4. Mago pass: `mago format` (isolated commit), then lint/analyze fixes for
   the findings listed in Probe results; baseline only the approved remainder.
5. `.github/workflows/continuous-integration.yml` wrapper +
   `.github/copilot-instructions.md`.
6. `.gitattributes` / `.gitignore` alignment (F5).
7. Docs cleanup (F8) gated on F1/F3 decisions.

## Decisions for review

| # | Decision | Open question |
|---|---|---|
| D1 | F1 dependency strategy | **Resolved — Option 3**: keep `Webware\Acl\*` / `Webware\UserManager\*` coupling; tooling-only alignment; VCS consumption; never Packagist |
| D2 | `run-integration` | **Resolved — `true`** (in-process suites, no services) |
| D3 | `enable-codecov` / `enable-infection` | **Resolved — `true` / `true`**, `min-msi: 95`, `min-covered-msi: 95` |
| D4 | autoload-dev rename | **Resolved — adopted** `WebwareTest\Navigation\` + `WebwareTestIntegration\Navigation\` |
| D5 | `config.platform.php` | **Resolved — `8.4.99`** |
| D6 | `require.php` | **Resolved — `~8.4.1 \|\| ~8.5.0`** |
| D7 | Docs cleanup scope | **Resolved — README + testing.md refreshed**; filter-iterator doc examples kept behind a "blocked until deps publish" note |
| D8 | Guard configuration | **Resolved — do nothing** until webware-tools adds guard rules |

## Proposed execution order (post-review)

1. F1 resolved (Option 3); apply `composer.json` (F3); `composer update`;
   commit lock.
2. Rewrite `phpunit.xml.dist`; create tool configs (item 3).
3. Mago pass (item 4) with isolated format commit.
4. CI wrapper + copilot-instructions (item 5).
5. `.gitattributes` / `.gitignore` (item 6).
6. Docs cleanup per D7.
7. Local verification: `composer validate --strict`, `mago format --check`,
   `mago lint`, `mago analyze`, `mago guard` green; `composer test` /
   `test-integration` / `test-coverage` / `mutation-test` expected red until
   the test-suite step.
8. Push, open PR against `0.1.x`; `mago` + `codecov` jobs green; `test` +
   `mutation-test` green when the suite lands.
9. Repo settings (user): Infection dashboard key, Codecov/Renovate app
   access, optional branch protection on `0.1.x` (same as mailer).

## Execution status (2026-08-17)

Work items 1–6 executed. Local verification:

- `composer validate --strict` green.
- `mago format --check` green.
- `mago guard` no-op (expected).
- `composer test` green (14 tests, 32 assertions); `composer test-integration`
  green (1 test, 6 assertions).
- `mago lint` — 3 remaining errors: `cyclomatic-complexity`, `kan-defect`,
  `too-many-methods`, all on `NavigationContainer` (class-splitting refactor;
  deferred).
- `mago analyze` — 25 errors + 4 warnings remaining:
  - 24 errors: `non-existent-*` for `Webware\Acl\*` /
    `Webware\UserManager\*` (accepted per D1 — unresolved until pair-publish).
  - 1 error: `possibly-invalid-argument` on `NavigationFilterIterator`'s
    `FilterIterator` constructor (stub-generics limitation).
  - 4 warnings: `mixed-assignment` — PSR-7 `getAttribute()` returns `mixed`
    (3×) and one `$options['navigation'] ?? null` read (1×).
- `composer mutation-test` / `test-coverage` not run locally (no coverage
  driver); exercised by the CI canonical leg.

Baseline candidates (pending explicit user approval per issue): the 3 lint
complexity errors, the 24 dep-driven analyze errors, the 1
`possibly-invalid-argument`, and the 4 `mixed-assignment` warnings. Nothing has
been baselined yet.
