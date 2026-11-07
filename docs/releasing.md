# Releasing

How a version of Pundasyon is published. Version numbers follow [Semantic Versioning](https://semver.org/): a new major version for breaking changes, minor for new features, patch for fixes.

## Checklist

### 1. Code

- [ ] `main` holds everything for the release, and CI is green on it (style, static analysis, tests on SQLite, MySQL and PostgreSQL, module generator).
- [ ] `composer audit` and `npm audit --omit=dev` report no known vulnerabilities, or each is understood and noted below.
- [ ] Dependabot pull requests are merged or deliberately postponed.

### 2. A clean install works

From a fresh clone, on a machine or folder without a previous copy, follow the README's quick start exactly:

- [ ] `composer install`, `sail up -d --wait`, `sail artisan app:install --demo`, `sail npm install`, `sail npm run build` all succeed.
- [ ] Sign in as `admin@example.com`; the dashboard, Users, Roles, Activity log and Settings open.
- [ ] A password-reset email arrives in Mailpit (proves the queue and mail settings).
- [ ] `sail composer check` and `sail composer check:generator` pass.
- [ ] `/docs/api` lists every endpoint.

### 3. Security

- [ ] [docs/security.md](security.md) still describes what the code does.
- [ ] Anything security-related since the last release has been reviewed against the OWASP Top 10.

### 4. Words

- [ ] [CHANGELOG.md](../CHANGELOG.md): move "Unreleased" under a new heading such as `## [1.0.0] - 2026-11-07` and add the comparison links at the bottom.
- [ ] `API_VERSION` in `.env.example` matches the release (it's shown in the API docs).
- [ ] README screenshots still match the app.

### 5. Publish

```bash
git checkout main && git pull
git tag -a v1.0.0 -m "Pundasyon 1.0.0"
git push origin v1.0.0
gh release create v1.0.0 --title "Pundasyon 1.0.0" --notes-file <(sed -n '/## \[1.0.0\]/,/## \[/p' CHANGELOG.md | sed '$d')
```

- [ ] The GitHub release shows the changelog section for this version.
- [ ] For a public repository: turn on private vulnerability reporting (Settings → Code security), which [SECURITY.md](../SECURITY.md) relies on.

## v1.0.0 status

Checked on 2026-11-07, before the code was first committed:

| Item | Status |
|---|---|
| Tests, style, static analysis | Pass locally (204 tests, Larastan level 8) |
| Module generator check | Pass (24 generated tests) |
| Fresh install from the files a commit would contain | Pass, after fixing three problems it found: `.env.example` used SQLite and wrote emails to a log file, and `app:install` failed if run while MySQL was still starting |
| OWASP Top 10 review | Done: 13 findings, all fixed, 3 accepted risks documented. See [security review](security-review.md) |
| Dependency audits | `composer audit` and `npm audit`: no known vulnerabilities |
| CI on GitHub | Not run yet: needs the code pushed |
| Tag and release | Not done: needs the code committed and pushed |
