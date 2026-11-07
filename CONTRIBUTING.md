# Contributing

Thanks for helping. Bug reports, fixes, documentation and new features are all welcome.

## Before you start

- For a bug, open an issue with the steps to reproduce it, what you expected and what happened.
- For a new feature, open an issue first to talk it through, so your time isn't spent on something that won't be merged.
- For a security problem, don't open an issue: follow [SECURITY.md](SECURITY.md).

## Setting up

Follow the [quick start](README.md#quick-start). You need Docker and Git.

## Making a change

1. Fork the repository and create a branch from `main`: `git checkout -b fix/short-description`.
2. Make the change, with tests. Every feature and fix needs a Pest test that would fail without it.
3. Run the checks CI runs:

   ```bash
   ./vendor/bin/sail composer format          # fix code style
   ./vendor/bin/sail composer check           # style, static analysis, tests
   ./vendor/bin/sail composer check:generator # if you changed the module generator or stubs/module
   ```

4. Commit with a [Conventional Commits](https://www.conventionalcommits.org/) message, such as `fix(api): return 404 for deleted users`.
5. Open a pull request into `main`. Say what changed and why, and link the issue.

## Code conventions

- `declare(strict_types=1);` in every PHP file, typed properties and return types.
- Thin controllers and screens: business logic goes in an action class in `app/Actions`.
- Fixed values are enums in `app/Enums`.
- Every model has a policy; never rely on hiding a button.
- API input goes through a form request, output through an API resource.
- Comment the "why" of anything not obvious; skip comments that repeat the code.
- Write user-facing text in plain, sentence-case English.

Pint and Larastan (level 8) enforce most of this, and architecture tests in `tests/Arch` check the rest.

## Changelog

Add a line under "Unreleased" in [CHANGELOG.md](CHANGELOG.md) for anything a user of Pundasyon would notice.
