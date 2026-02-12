# CLAUDE.md — Goonj CRM

## Project Overview

Goonj CRM is a constituent relationship management system built on **WordPress + CiviCRM**, developed by [ColoredCow](https://coloredcow.com) for [Goonj](https://goonj.org). Licensed under AGPL.

## Multi-Repo Setup

> **Important:** Code and issue tracking live in separate GitHub repositories.

| Purpose | Repository | URL |
|---------|-----------|-----|
| **Code** (this repo) | `ColoredCow/goonj` | https://github.com/ColoredCow/goonj |
| **Issue Tracking** | `ColoredCow/goonj-crm` | https://github.com/ColoredCow/goonj-crm |

- **All issues, bug reports, and feature requests** are tracked in [`ColoredCow/goonj-crm`](https://github.com/ColoredCow/goonj-crm/issues).
- When referencing issues in commits, PRs, or code comments, use the full format: `ColoredCow/goonj-crm#<issue-number>`.
- To fetch issue details, use: `gh issue view <number> --repo ColoredCow/goonj-crm`.
- To list open issues: `gh issue list --repo ColoredCow/goonj-crm`.
- The separation exists because the issue tracker contains sensitive data (monetary/operational screenshots) that cannot be in a public repo.

## Tech Stack

- **CMS:** WordPress (PHP 8.x)
- **CRM:** CiviCRM
- **Database:** MySQL 5.7.5+ / MariaDB 10.2+
- **CLI Tools:** WP-CLI (`wp`), CiviCRM CLI (`cv`)
- **Testing:** Playwright (E2E)
- **CI/CD:** GitHub Actions (code review, E2E tests, staging deployment)

## Project Structure

```
wp-content/
├── civi-extensions/       # CiviCRM extensions (custom logic lives here)
│   ├── goonjcustom/       # Primary custom extension for Goonj
│   ├── civirazorpay/      # Razorpay payment integration
│   ├── civiglific/        # Glific messaging integration
│   └── ...                # Other CiviCRM extensions
├── plugins/               # WordPress plugins
│   ├── civicrm/           # CiviCRM core plugin
│   ├── goonj-blocks/      # Custom Gutenberg blocks
│   └── ...                # Other plugins
└── themes/
    └── goonj-crm/         # Custom WordPress theme
```

## Key Conventions

- **Branch strategy:** `dev` is the primary development branch (auto-deploys to staging).
- **PR labels:** Add `ready for review` to trigger Claude Code Review; `in review` triggers E2E tests.
- **Code review guidelines** are in `docs/code-review/`. Claude reads all `.md` files in that directory during reviews.
- **E2E tests** use Playwright and run against staging. Config is in `playwright.config.js`, tests in `playwright/e2e/`.

## Working with Issues

When working on a task linked to a GitHub issue:
1. Fetch the issue context: `gh issue view <number> --repo ColoredCow/goonj-crm`
2. Reference it in your PR description as `ColoredCow/goonj-crm#<number>`
3. Use `Closes ColoredCow/goonj-crm#<number>` in PR descriptions to auto-close issues on merge

## Custom Agents

Custom agents are defined in `.claude/agents/`. Use them via the Task tool with the matching `subagent_type`.

| Agent | When to Use |
|-------|-------------|
| `implementation-planner` | When the user asks for an implementation plan, technical breakdown, or task planning for a feature or issue. Accepts a GitHub issue URL/number, explores the codebase, creates a detailed plan with 4-hour task breakdowns, and posts it as a comment on the GitHub issue. |
