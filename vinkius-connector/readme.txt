=== Vinkius Connector ===
Contributors: vinkius
Tags: ai, agent, mcp, governance, vinkius
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vinkius AI Governance for WordPress: see governed agent operations, inspect incidents and trigger emergency halt — without leaving wp-admin.

== Description ==

The plugin is the owner's observation-and-response panel for the Vinkius runtime:

* **AI Governance workbench** — Mission Control, Agent Activity, Connector Traffic, Access Tokens, Agent Passport, AI Spend, Capability Reliability, Security Posture and Request Failures, all read live from the runtime and drillable in place.
* **Compliance & Evidence** — readiness scorecard, framework coverage and re-verifiable evidence packs.
* **Incidents** — errors, DLP blocks, quarantined connectors and the operator control trail, with resumption actions.
* **Emergency halt** — stop one connector or the whole runtime, and approve resumption, directly from the panel.
* **Connect** — one device-flow login binds this site to your Vinkius account.

All data is read live from the Vinkius API. Nothing is stored in WordPress and no backend secret is exposed. Managing connectors, credentials, policies, webhooks or publishing happens in the Vinkius cloud — the panel links you there.

= Site surfaces =

* `[vinkius_badge]` — Vinkius-verified agent API badge.
* `[vinkius_consent]` — visitor opt-in before agents act on their behalf (the only data stored on the site).

== Installation ==

1. Upload the `vinkius-connector` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Open **Vinkius AI Governance → Settings** and click **Connect with Vinkius**.
4. Approve the device code on your Vinkius account.
5. Open the Governance dashboard.

== Frequently Asked Questions ==

= Does the plugin call external servers? =

Yes — only the Vinkius platform (fixed endpoints, no user configuration), and only after you log in. External calls are user-authorized; this satisfies the wordpress.org policy on external communication.

= Is data stored on the site? =

Only visitor consent records. Agent executions, incidents and fleet data are read live from the Vinkius runtime and never persisted in WordPress.

== Changelog ==

= 1.0.0 =
* Initial release: Vinkius AI Governance panel, agent fleet, incidents, evidence, emergency halt, device-flow login, site badge and consent surfaces, 6 locales.
