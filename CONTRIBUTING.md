# Contributing to Vinkius Connector

Thanks for helping improve the Vinkius AI Governance panel for WordPress.

## Ground rules

This plugin has **one non-negotiable architectural rule**: it is an
*observation-and-response* surface. It **never** manages connectors,
credentials, policies, webhooks, or publishing inside WordPress. Those live
in the Vinkius cloud, and the panel deep-links there. All panel data is read
**live** from the Vinkius API — nothing is persisted in WordPress except the
site's own consent records (`vc_consents`) and local settings.

Please keep that boundary intact in every change.

## Development

There is no build step. The admin UI is vanilla PHP + CSS + JS.

- PHP 7.4+ (WordPress coding standards, tabs for indentation).
- JS: plain ES5, no framework, no bundler (`admin/js/*`).
- CSS: plain, namespaced with `vc-` (`admin/css/*`).

### Checks you should run

```bash
# PHP syntax
cd vinkius-connector && find . -name '*.php' -exec php -l {} \;

# JS syntax
node --check vinkius-connector/admin/js/vinkius-connector.js
node --check vinkius-connector/admin/js/vc-governance.js

# i18n: every __()/esc_html_e() string must exist in the .pot
# (the CI job does this for you).
```

## Internationalization

The UI ships in 6 locales: `en` (base `.pot`), `pt_BR`, `fr_FR`, `es_ES`,
`de_DE`, `ja_JP`. When you add or change a user-facing string:

1. Add the `msgid` to `languages/vinkius-connector.pot`.
2. Add a translated `msgstr` to **each** of the 5 `.po` files.
3. CI fails if a code string is missing from the `.pot`.

## What to submit

- **Bug reports**: include WordPress + PHP version, the exact screen, and
  what you expected vs. what happened. If it involves the API, the HTTP
  method/endpoint and status code help a lot.
- **Features**: confirm they respect the "one rule" above. If a change would
  push management logic into WordPress, it belongs in the Vinkius cloud
  instead.

## License

By contributing you agree that your contributions are licensed under
**GPLv2 or later**.
