# Webware Tools Alignment — Spec Kit Preset

Spec Kit preset for aligning a Webware package's CI/CD pipeline and dev tooling with the
`webinertia/webware-tools` reusable workflow.

## What it provides

Four template overrides for Spec Kit:

| Template | Content |
|---|---|
| `spec-template` | Alignment spec skeleton: purpose, scope, Package Parameters table, functional requirements, success criteria |
| `plan-template` | Plan prefilled with the reusable workflow contract (inputs, jobs, consumer obligations) and 6 implementation phases |
| `tasks-template` | Task list T001–T019: composer, phpunit, mago, infection/codecov/renovate/phpbench, workflow wrapper, README badges |
| `constitution-template` | Webware constitution: CI alignment, PHPUnit 13 strict mode, Mago gates, PHP compatibility, naming |

The Package Parameters table in the generated spec is empty by design. Fill in package-specific
values before planning. Reference instance: webware-mailer's
`specs/001-webware-tools-alignment/`.

## Install

Requirements: `specify` CLI (`spec-kit` ≥ 0.16.0) and an initialized Spec Kit project
(`specify init`).

Local development (unreleased):

```bash
specify preset add --dev /path/to/webware-tools/presets/webware-alignment
```

From a release archive:

```bash
specify preset add --from https://github.com/webinertia/webware-tools/archive/refs/tags/v0.1.0.zip
```

From the catalog (once submitted):

```bash
specify preset add webware-alignment
```

Verify resolution:

```bash
specify preset resolve spec-template
specify preset list
```

Remove after testing:

```bash
specify preset remove webware-alignment
```

## Usage workflow

1. `specify init --here --force --integration copilot` (or your agent) in the target package.
2. Install this preset (one of the commands above).
3. `/speckit-specify "Align CI/CD and tooling with webware-tools"` — template is prefilled;
   fill the Package Parameters table with the package's values.
4. `/speckit-plan` — produces the artifact-by-artifact plan with the package's inputs.
5. `/speckit-tasks` — produces T001–T019.
6. `/speckit-implement` — creates the wrapper workflow, `phpunit.xml.dist`, `mago.toml`
   (extends `vendor/webware/webware-tools/mago.toml`), baselines, `infection.json5.dist`,
   `codecov.yml`, `renovate.json`, `phpbench.json.dist`, composer changes + lock,
   `.github/copilot-instructions.md`, and README badges.

## When to use / when not

Use for: any `webinertia` package that consumes the webware-tools reusable workflow and needs its
first alignment or a re-alignment after a workflow version bump.

Do not use for: feature work inside an already-aligned package, or packages outside the webware
ecosystem.
