# Code Review Guidelines

> **Note:** Claude reads all `.md` files in `docs/code-review/` before reviewing PRs. You can split guidelines into multiple files (e.g., `security.md`, `style.md`, `api.md`).

These guidelines are used by Claude to align feedback with project expectations.

## Focus areas

- Readability and maintainability
- Avoiding unnecessary complexity
- Performance impact on critical user flows
- Database query efficiency
- Security implications (auth, payments, PII)

## Review tone

- Be constructive
- Prefer suggestions over rewrites
- Call out risks clearly
- Avoid stylistic nitpicking unless it affects clarity or safety

## Project-specific rules (customize these)

### PHP code style

Run `phpcbf` against any PHP file you change before opening a PR. We use the **Drupal** coding standard (which CiviCRM's `Civi` ruleset extends).

```bash
phpcbf --standard=Drupal path/to/changed/file.php
```

Example:

```bash
phpcbf --standard=Drupal wp-content/civi-extensions/goonjcustom/Civi/UrbanExternalMeetingService.php
```

If `phpcbf` isn't on your machine, install PHP_CodeSniffer globally with Composer (`composer global require squizlabs/php_codesniffer`) and ensure `~/.composer/vendor/bin` is on your `PATH`.

<!-- Add other project-specific rules below. Examples: -->
<!-- - All API endpoints must have authentication middleware -->
<!-- - Database migrations must be reversible -->
<!-- - Feature flags required for new user-facing features -->
