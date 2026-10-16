# Vinkius Connector for WordPress

Open source (**GPLv2 or later**) WordPress plugin that embeds the **Vinkius AI
Governance** panel inside `wp-admin` — the owner's observation-and-response
surface for the Vinkius runtime, reading every value live from the Vinkius API.

The plugin lives in [`vinkius-connector/`](./vinkius-connector) and is
wordpress.org-directory ready (`readme.txt` included, < 10 MB, no external
calls until the owner logs in).

## What it does

- **AI Governance workbench** — a cloud-faithful sidebar (Mission Control,
  Agent Activity, Connector Traffic, Access Tokens, AI Spend, Capability
  Reliability, Security Posture, Request Failures) with live KPIs, tables,
  period filters and the live activity feed.
- **Capabilities search** — the Vinkius catalog search with the same design as
  `cloud.vinkius.com/capabilities`: debounced query, category chips, infinite
  scroll, and one-click deep links that open any connector in the Vinkius
  cloud (new tab).
- **Emergency response** — halt one connector or the whole site, approve
  resumption, reset a tripped circuit breaker. Enforcement happens in the
  Vinkius runtime; the panel only triggers it.
- **Plan & usage** — live quota, Free-plan subscribe CTA.
- **Consent & badge** — `[vinkius_consent]` (the only data stored on the
  site) and `[vinkius_badge]` (Vinkius-verified agent API, served by the
  cloud).
- **Device-flow login** — one click opens the approval page; the site
  connects automatically. No passwords, no API keys in the database.

## The one rule

The plugin **never manages connectors, credentials, policies, webhooks or
publishing**. Those live in the Vinkius cloud; the panel deep-links there.
All panel data is read live (nothing is stored in WordPress), and the only
local store is the site's visitor-consent record.

## Quick start

1. Upload `vinkius-connector/` to `wp-content/plugins/` and activate.
2. **Vinkius → Settings → Connect with Vinkius** and approve the device code
   on your Vinkius account. The Vinkius platform endpoints are fixed —
   there is nothing to configure.
3. Open **Vinkius → Governance**: the workbench streams live. Free accounts
   see the plan bar and the subscribe link; expired sessions get a clear
   "log in again" state.

## Requirements

- WordPress 6.0+, PHP 7.4+.
- A Vinkius account (Free plan is enough to connect; paid plans unlock the
  analytics windows).

## Internationalization

Six locales ship: `en` (base `.pot`), `pt_BR`, `fr_FR`, `es_ES`, `de_DE`,
`ja_JP`. The UI language is selectable in **Settings** (default: follow the
site). PO files are parsed at runtime; compiled `.mo` files are used first
when present.

## Repository layout

```
vinkius-connector/
├── vinkius-connector.php   # plugin header (GPLv2 or later)
├── includes/
│   ├── class-vinkius-api.php   # live reads + control triggers, zero management
│   ├── class-governance.php    # composes API reads into panel payloads
│   ├── class-links.php         # deep links into the Vinkius cloud
│   ├── auth/                   # device-flow login + Passport session
│   ├── i18n/                   # 6-locale catalogs, user-selectable
│   ├── models/consent          # the only local store (site legal record)
│   ├── rest/                   # vinkius/v1 routes
│   └── admin/                  # menu + 7 screens
├── templates/                # workbench shell + screens
├── admin/css|js/             # vanilla, no build step
├── languages/                # .pot + 5 locale catalogs
├── readme.txt                # wordpress.org directory readme
├── uninstall.php
└── LICENSE                   # GPL-2.0 full text
```

No composer dependencies. No database tables for Vinkius data.

## Development

See [CONTRIBUTING.md](./CONTRIBUTING.md). CI runs `php -l` on all files,
`node --check` on the admin scripts, and an i18n coverage check
(every translatable string must exist in the catalog).

## License

GPLv2 or later. See [LICENSE](./LICENSE).
