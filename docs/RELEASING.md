# Releasing v1.0.0 — manual checklist

Everything automatable is done; these steps need Erik's accounts and a
session restart. ~15 minutes.

## 1. Rename the directory (between Claude sessions)

```sh
mv ~/git/open-source-code/php-browse ~/git/open-source-code/webfetch
```

Then update phagent's path repository (it points at `../php-browse`):

```sh
cd ~/git/open-source-code/phagent
composer config repositories.webfetch '{"type": "path", "url": "../webfetch", "options": {"symlink": true}}'
composer update devilsberg/webfetch && composer check
git add composer.json composer.lock && git commit -m "Point webfetch path repo at renamed directory" && git push
```

## 2. Create the GitHub repository and push

```sh
cd ~/git/open-source-code/webfetch
gh repo create erik-ros-devilsberg/webfetch --public --source . --push
# or create it in the UI, then:
# git remote add origin git@github.com:erik-ros-devilsberg/webfetch.git && git push -u origin main
```

Verify the CI workflow runs green on GitHub (first live run of
`.github/workflows/ci.yml` — it has only been validated locally).

## 3. Tag and release

```sh
git tag v1.0.0 && git push origin v1.0.0
gh release create v1.0.0 --title "v1.0.0" --notes-file CHANGELOG.md
```

## 4. Packagist

- Submit `https://github.com/erik-ros-devilsberg/webfetch` at
  <https://packagist.org/packages/submit> (logged in as your account).
- Enable the GitHub webhook (Packagist shows the instructions after
  submission) so future tags auto-update.

## 5. Flip phagent to the Packagist version

```sh
cd ~/git/open-source-code/phagent
composer config --unset repositories.webfetch
composer require devilsberg/webfetch:^1.0
composer check && git add -A && git commit -m "Use devilsberg/webfetch from Packagist" && git push
```

## 6. Aftercare

- Check the Packagist page renders README/keywords correctly.
- Enable GitHub Issues.
- Move `docs/user-stories/backlog/11-v1-release.md` to done and update
  `docs/status.md` (or let the next Claude session wrap it).
