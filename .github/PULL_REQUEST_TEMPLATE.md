## Summary

<!-- One or two sentences: what changes and why. -->

## Rule check

- [ ] No connector/credential/policy/webhook management added to WordPress (the panel only observes and responds; management deep-links to the Vinkius cloud).
- [ ] No Vinkius data persisted in WordPress tables/options (consent records are the only exception).
- [ ] No backend secrets or internal implementation details exposed in comments or responses.

## Tests

- [ ] `php -l` clean on all changed files
- [ ] `node --check` clean on changed JS (if any)
- [ ] New/changed user-facing strings added to `.pot` + all 5 `.po` catalogs

## Screenshots (if UI)

