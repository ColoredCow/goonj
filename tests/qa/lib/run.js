#!/usr/bin/env node
/**
 * QA check runner. No dependencies.
 *
 *   npm run qa
 *   npm run qa -- --id MON-012
 *   npm run qa -- --area receipts
 *   npm run qa -- --changed
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Optional: works without node_modules if the variables are already exported.
try {
  require('dotenv').config();
} catch { /* dotenv not installed — rely on the environment */ }

const ROOT = path.resolve(__dirname, '..');
const REPO = path.resolve(__dirname, '../../..');

function parseArgs(argv) {
  const args = { id: null, area: null, changed: false };
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === '--id') args.id = argv[++i];
    else if (argv[i] === '--area') args.area = argv[++i];
    else if (argv[i] === '--changed') args.changed = true;
  }
  return args;
}

function discover(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name !== 'lib') out.push(...discover(full));
    } else if (entry.name.endsWith('.js') && !entry.name.startsWith('_')) {
      out.push(full);
    }
  }
  return out;
}

/** Minimal glob: ** matches any depth, * matches within a segment. */
function globToRegExp(glob) {
  const escaped = glob.replace(/[.+^${}()|[\]\\]/g, '\\$&');
  const body = escaped.split('**').map((p) => p.replace(/\*/g, '[^/]*')).join('.*');
  return new RegExp(`^${body}$`);
}

function changedPaths() {
  // On a PR, compare against the base branch; locally, against HEAD.
  const base = process.env.GITHUB_BASE_REF;
  const commands = base
    ? [`git diff --name-only origin/${base}...HEAD`]
    // Locally: tracked edits plus files that are new and not yet staged.
    : ['git diff --name-only HEAD', 'git ls-files --others --exclude-standard'];

  const files = new Set();
  for (const cmd of commands) {
    try {
      execSync(cmd, { cwd: REPO, encoding: 'utf8' })
        .split('\n')
        .filter(Boolean)
        .forEach((f) => files.add(f));
    } catch { /* not a git repo, or nothing to report */ }
  }
  return [...files];
}

function matchesChanged(check, changed) {
  if (!check.paths || !check.paths.length) return false;
  const patterns = check.paths.map(globToRegExp);
  return changed.some((file) => patterns.some((re) => re.test(file)));
}

// ---- assertions -------------------------------------------------------------

function makeAssert(caseId) {
  const fail = (msg) => {
    throw new Error(`${caseId}: ${msg}`);
  };
  return {
    eq(actual, expected, what) {
      if (String(actual) !== String(expected)) {
        fail(`${what} — expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
      }
    },
    ok(condition, what) {
      if (!condition) fail(what);
    },
    count(rows, expected, what) {
      const n = Array.isArray(rows) ? rows.length : rows;
      if (n !== expected) fail(`${what} — expected ${expected}, got ${n}`);
    },
    money(actual, expected, what) {
      const norm = (v) => Number(v).toFixed(2);
      if (norm(actual) !== norm(expected)) {
        fail(`${what} — expected ${norm(expected)}, got ${norm(actual)}`);
      }
    },
    fail,
  };
}

// ---- main -------------------------------------------------------------------

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const files = discover(ROOT);

  let checks = files.map((f) => {
    const mod = require(f);
    return { ...mod, file: path.relative(REPO, f) };
  });

  if (args.id) checks = checks.filter((c) => c.id === args.id);
  if (args.area) checks = checks.filter((c) => (c.area || []).includes(args.area));
  if (args.changed) {
    const changed = changedPaths();
    if (!changed.length) {
      console.log('No changed files detected — nothing to run.');
      return 0;
    }
    checks = checks.filter((c) => matchesChanged(c, changed));
    console.log(`${changed.length} changed file(s) → ${checks.length} check(s)\n`);
  }

  if (!checks.length) {
    console.log('No checks matched.');
    return 0;
  }

  const results = [];
  for (const check of checks) {
    const assert = makeAssert(check.id);
    const started = Date.now();
    try {
      await check.run({ assert });
      const ms = Date.now() - started;
      const unverified = check.verifiedBy ? '' : '  [unverified]';
      console.log(`PASS  ${check.id}  ${check.title}  (${ms}ms)${unverified}`);
      results.push({ check, ok: true, unverified: !check.verifiedBy });
    } catch (err) {
      console.log(`FAIL  ${check.id}  ${check.title}`);
      console.log(`      ${err.message.split('\n').join('\n      ')}`);
      console.log(`      origin: ${check.origin || 'unknown'}   file: ${check.file}`);
      results.push({ check, ok: false, error: err });
    }
  }

  const failed = results.filter((r) => !r.ok);
  const unverified = results.filter((r) => r.ok && r.unverified);
  console.log(`\n${results.length - failed.length}/${results.length} passed`);
  if (unverified.length) {
    console.log(
      `${unverified.length} check(s) have never been proven to fail when the bug is ` +
      `present — see verifiedBy in .claude/skills/qa-check/SKILL.md`
    );
  }
  return failed.length ? 1 : 0;
}

main()
  .then((code) => process.exit(code))
  .catch((err) => {
    console.error(err.message);
    process.exit(1);
  });
