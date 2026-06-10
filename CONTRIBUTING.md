# Contributing

Thanks for considering a contribution.

## Ground rules

- Open an issue before large changes — small fixes can go straight to a PR.
- `composer check` (style + PHPStan level 8 + tests) must pass; CI runs the
  same gate plus `composer audit`.
- Tests first: new behavior comes with tests, bug fixes come with a
  regression test. The suite never touches the live network.
- The JSON output schema is the public contract — schema changes are
  breaking changes and need an issue first.

## Workflow

1. Fork and branch from `main`.
2. `composer install`
3. Make your change, with tests.
4. `composer check`
5. Open a PR describing what and why.

By contributing you agree your work is licensed under the project's
[MIT license](LICENSE).
