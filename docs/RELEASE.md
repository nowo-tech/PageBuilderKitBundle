# Release process

1. Update [CHANGELOG.md](CHANGELOG.md) with the new version section.
2. Update [UPGRADING.md](UPGRADING.md) if the release adds upgrade steps or breaking changes.
3. Confirm package metadata in `composer.json` is correct for the release.
4. Run pre-release checks: `make release-check`.
5. Commit release documentation changes.
6. Create an annotated tag such as `v1.0.0`.
7. Push the branch and tag.
8. Verify Packagist and GitHub Release automation if applicable.

After creating the release commit, run `make check-no-cursor-coauthor` again **before** `git push`.

## Example

```bash
git add -A
git status
make release-check
git commit -m "Release 1.0.0"
make check-no-cursor-coauthor
git tag -a v1.0.0 -m "Release 1.0.0"
git push origin main
git push origin v1.0.0
```

## Security checklist

Before tagging a release, confirm dependency audit is clean, no secrets are committed, and `docs/SECURITY.md` remains accurate.
