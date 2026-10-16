/**
 * AI Governance workbench (sidebar + live sections + capabilities search).
 *
 * Data is read live from the Vinkius API; every deep action opens the
 * Vinkius cloud in a new tab. Design mirrors the Vinkius cloud frontend.
 *
 * @license GPLv2 or later
 */
(function () {
	'use strict';

	if (typeof VCConfig === 'undefined') {
		return;
	}

	var root = document.getElementById('vc-gov');

	if (!root) {
		return;
	}

	var body = document.getElementById('vc-gov-body');
	var titleEl = document.getElementById('vc-gov-title');
	var periodsWrap = document.getElementById('vc-gov-periods');

	if (!body || !titleEl || !periodsWrap) {
		return;
	}

	function L(key, fallback) {
		var labels = (VCConfig && VCConfig.labels) || {};

		return labels[key] || fallback;
	}

	function vcRest(path, options) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['Content-Type'] = 'application/json';
		options.headers['X-WP-Nonce'] = VCConfig.restNonce;

		return fetch(VCConfig.restUrl + path, options).then(function (r) {
			if (r.status === 401 || r.status === 403) {
				throw new Error('vc_forbidden');
			}

			return r.json();
		});
	}

	function connected() {
		return root.dataset.status === 'connected';
	}

	function el(tag, className, text) {
		var n = document.createElement(tag);

		if (className) {
			n.className = className;
		}

		if (text !== undefined && text !== null) {
			n.textContent = text;
		}

		return n;
	}

	function num(v, digits) {
		if (v === null || v === undefined || v === '') {
			return '\u2014';
		}

		var n = Number(v);

		if (isNaN(n)) {
			return String(v);
		}

		return digits === undefined ? n.toLocaleString() : n.toFixed(digits);
	}

	function pct(v) {
		if (v === null || v === undefined || v === '') {
			return '\u2014';
		}

		var n = Number(v);

		if (isNaN(n)) {
			return String(v);
		}

		if (n >= 0 && n <= 1) {
			return Math.round(n * 1000) / 10 + '%';
		}

		return Math.round(n * 10) / 10 + '%';
	}

	function fmtBytes(v) {
		if (v === null || v === undefined || !Number(v)) {
			return '\u2014';
		}

		var units = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = 0;
		var n = Number(v);

		while (n >= 1024 && i < units.length - 1) {
			n /= 1024;
			i++;
		}

		return n.toFixed(n >= 100 ? 0 : 1) + ' ' + units[i];
	}

	function prettyKey(key) {
		return String(key)
			.replace(/_/g, ' ')
			.replace(/\b\w/g, function (c) {
				return c.toUpperCase();
			})
			.trim();
	}

	/* ── KPI + table builders ────────────────────────────────────── */

	function kpiCard(key, value, cls) {
		var card = el('div', 'vc-gov-kpi' + (cls ? ' ' + cls : ''));

		card.appendChild(el('span', 'vc-gov-kpi-value', value));
		card.appendChild(el('span', 'vc-gov-kpi-label', prettyKey(key)));
		return card;
	}

	function renderKpis(kpi, wrap) {
		if (!kpi || typeof kpi !== 'object' || !Object.keys(kpi).length) {
			return;
		}

		var grid = el('div', 'vc-gov-kpis');

		Object.keys(kpi).forEach(function (key) {
			var v = kpi[key];
			var text;
			var cls = '';

			if (key === 'vinkius_reliability') {
				text = num(v) + '%';
				cls = Number(v) < 99 ? 'is-warn' : '';
			} else if (key.indexOf('rate') >= 0 || key === 'success_rate' || key === 'roi_percentage') {
				text = pct(v);
			} else if (key === 'total_cost' || key === 'savings' || key === 'total_savings') {
				text = '$' + num(v, 2);
			} else if (key.indexOf('bytes') >= 0) {
				text = fmtBytes(v);
			} else if (key === 'avg_latency_ms') {
				text = num(v) + ' ms';
			} else if (key === 'avg_session_seconds') {
				text = num(v) + ' s';
			} else {
				text = num(v);
			}

			if ((key === 'total_errors' || key.indexOf('errors') === 0) && Number(v) > 0) {
				cls = 'is-bad';
			} else if (key === 'total_errors' && !Number(v)) {
				cls = 'is-good';
			}

			grid.appendChild(kpiCard(key, text, cls));
		});

		wrap.appendChild(grid);
	}

	// column: [label, type, key]  type: ''|num|pct|ms|bytes|usd
	function tableOf(titleText, rows, columns) {
		if (!rows || !rows.length) {
			return null;
		}

		var wrap = el('div', 'vc-gov-table-wrap');

		wrap.appendChild(el('div', 'vc-gov-table-title', titleText));

		var table = el('table', 'vc-gov-table');
		var thead = el('thead');
		var hrow = el('tr');

		columns.forEach(function (c) {
			hrow.appendChild(el('th', c[1] === 'num' ? 'vc-num' : '', c[0]));
		});

		thead.appendChild(hrow);
		table.appendChild(thead);

		var tbody = el('tbody');

		rows.slice(0, 50).forEach(function (r) {
			var tr = el('tr');

			columns.forEach(function (c) {
				var v = r[c[2]];
				var numeric = c[1] === 'num' || c[1] === 'pct' || c[1] === 'ms' || c[1] === 'bytes' || c[1] === 'usd';
				var td = el('td', numeric ? 'vc-num' : '');

				if (numeric) {
					if (c[1] === 'pct') {
						td.textContent = pct(v);
					} else if (c[1] === 'ms') {
						td.textContent = num(v) + ' ms';
					} else if (c[1] === 'bytes') {
						td.textContent = fmtBytes(v);
					} else if (c[1] === 'usd') {
						td.textContent = '$' + num(v, 2);
					} else {
						td.textContent = num(v);
					}
				} else {
					td.textContent = v === null || v === undefined ? '\u2014' : String(v);
				}
				tr.appendChild(td);
			});

			tbody.appendChild(tr);
		});
		table.appendChild(tbody);
		wrap.appendChild(table);
		return wrap;
	}

	function firstArray(obj, keys) {
		for (var i = 0; i < keys.length; i++) {
			if (obj && Array.isArray(obj[keys[i]]) && obj[keys[i]].length) {
				return obj[keys[i]];
			}
		}
		return null;
	}

	/* Per-section table specs: [title, [label,type,key], ...] */
	var SECTION_TABLES = {
		clients: [
			[L('by_client', 'By client'), ['Client', '', 'name'], ['Transport', '', 'transport'], ['Requests', 'num', 'requests'], ['Tool calls', 'num', 'tool_calls'], ['Errors', 'num', 'errors'], ['Avg latency', 'ms', 'avg_latency_ms']],
		],
		connectors: [
			[L('connectors', 'Connectors'), ['Connector', '', 'name'], ['Requests', 'num', 'requests'], ['Volume', 'bytes', 'volume']],
		],
		'token-fleet': [
			[L('access_tokens', 'Access tokens'), ['Connector', '', 'name'], ['Tokens', 'num', 'token_count'], ['Requests', 'num', 'total_requests'], ['Avg latency', 'ms', 'avg_latency_ms'], ['Status', '', 'status']],
		],
		cost: [
			[L('spend_by_server', 'Spend by server'), ['Server', '', 'server_name'], ['Requests', 'num', 'requests'], ['Tokens', 'num', 'tokens'], ['Cost', 'usd', 'cost'], ['Savings', 'usd', 'savings']],
		],
		security: [
			[L('events_by_server', 'Events by server'), ['Server', '', 'server_name'], ['Requests', 'num', 'requests'], ['DLP redactions', 'num', 'dlp_redactions'], ['Truncations', 'num', 'finops_truncations']],
		],
		errors: [
			[L('failures_by_server', 'Failures by server'), ['Server', '', 'server_name'], ['Requests', 'num', 'requests'], ['Errors', 'num', 'errors'], ['Error rate', 'pct', 'error_rate']],
		],
	};

	function renderSectionTables(section, d, wrap) {
		if (section === 'tool-performance') {
			var slowCols = [
				[L('tool', 'Tool'), '', 'tool'],
				[L('server', 'Server'), '', 'server'],
				[L('requests', 'Requests'), 'num', 'requests'],
				[L('avg_latency', 'Avg latency'), 'ms', 'avg_latency_ms'],
			];
			var failCols = [
				[L('tool', 'Tool'), '', 'tool'],
				[L('server', 'Server'), '', 'server'],
				[L('errors', 'Errors'), 'num', 'total_errors'],
			];
			var s1 = tableOf(L('slowest_tools', 'Slowest tools'), firstArray(d, ['slowest']), slowCols);
			var s2 = tableOf(L('most_failing', 'Most failing'), firstArray(d, ['most_failing']), failCols);

			if (s1) {
				wrap.appendChild(s1);
			}
			if (s2) {
				wrap.appendChild(s2);
			}
			return;
		}

		var spec = SECTION_TABLES[section];

		if (!spec) {
			return;
		}

		var rowsKey = {
			clients: ['by_client', 'clients'],
			connectors: ['connectors'],
			'token-fleet': ['mcps'],
			cost: ['by_server'],
			security: ['servers'],
			errors: ['by_server'],
		}[section] || [];

		spec.forEach(function (s) {
			var tbl = tableOf(s[0], firstArray(d, rowsKey), s.slice(1));

			if (tbl) {
				wrap.appendChild(tbl);
			}
		});
	}

	function renderLiveFeed(live, wrap) {
		var rows;

		if (live && live.data && live.data.live_feed) {
			rows = live.data.live_feed;
		} else if (live && live.live_feed) {
			rows = live.live_feed;
		} else if (Array.isArray(live)) {
			rows = live;
		} else {
			rows = [];
		}

		if (!rows.length) {
			return;
		}

		var block = el('div', 'vc-gov-block');

		block.appendChild(el('h3', null, L('live_activity', 'Live activity')));
		var ul = el('ul', 'vc-gov-feed');

		rows.slice(0, 25).forEach(function (ev) {
			var li = el('li');
			var code = parseInt(ev.status_code, 10) || 0;

			li.appendChild(el('span', 'vc-gov-feed-time', ev.created_at_iso || ev.created_at || ''));
			li.appendChild(el('span', 'vc-gov-feed-tool', (ev.server_name || '') + ' \u00B7 ' + (ev.tool_name || '')));
			li.appendChild(el('span', 'vc-pill ' + (code >= 400 ? 'err' : 'ok'), code ? String(code) : '\u00B7'));
			ul.appendChild(li);
		});
		block.appendChild(ul);
		wrap.appendChild(block);
	}

	function renderUsage(data, wrap) {
		var usage = data.usage || {};
		var plan = String(data.plan || 'free');
		var hasUsage = Object.keys(usage).length;

		if (!hasUsage && !data.plan) {
			return;
		}

		var block = el('div', 'vc-gov-block');

		block.appendChild(el('h3', null, L('plan_usage', 'Plan & usage')));
		var head = el('div', 'vc-gov-kpis');
		head.appendChild(kpiCard('plan', plan.charAt(0).toUpperCase() + plan.slice(1)));
		head.appendChild(kpiCard('used', num(usage.used || 0)));
		head.appendChild(kpiCard('limit', num(usage.effective_limit != null ? usage.effective_limit : usage.limit || 0)));
		block.appendChild(head);

		if (data.free === true || usage.overage_blocked === true) {
			var a = el('a', 'vc-cap-open');
			a.textContent = L('subscribe', 'Subscribe to Vinkius');
			a.href = (VCConfig.gov && VCConfig.gov.upgrade) || (VCConfig.links && VCConfig.links.upgrade) || '#';
			a.target = '_blank';
			a.rel = 'noopener';
			block.appendChild(a);
		}
		wrap.appendChild(block);
	}

	function renderHalt(data, wrap) {
		var halted = data.halted === true;
		var block = el('div', 'vc-gov-block');
		var head = el('div', 'vc-gov-kpis');
		head.appendChild(kpiCard('emergency_halt', halted ? L('halted', 'Halted') : L('running', 'Running'), halted ? 'is-bad' : 'is-good'));

		var btn = el('button', 'vc-cap-open');
		btn.type = 'button';

		if (halted) {
			btn.textContent = L('resume_agents', 'Resume agents');
			btn.addEventListener('click', function () {
				vcRest('panel/resume', { method: 'POST', body: '{}' }).then(function () {
					document.location.reload();
				});
			});
		} else {
			btn.textContent = L('halt_all', 'Halt all');
			btn.addEventListener('click', function () {
				if (!window.confirm(L('confirm_halt_all', 'Halt all agents on this site?'))) {
					return;
				}
				vcRest('panel/halt', { method: 'POST', body: JSON.stringify({ scope: 'all', reason: 'operator' }) }).then(function () {
					document.location.reload();
				});
			});
		}
		head.appendChild(btn);

		var manage = el('a', 'vc-cap-open');
		manage.href = (VCConfig.links && VCConfig.links.governance) || '#';
		manage.target = '_blank';
		manage.rel = 'noopener';
		manage.innerHTML = L('halt_manage', 'Manage per-connector halt in Vinkius') + ' ' + ARROW_SVG;
		head.appendChild(manage);

		block.appendChild(head);
		wrap.appendChild(block);
	}

	function renderCircuit(data, wrap) {
		var circuit = data.circuit || {};

		if (!Object.keys(circuit).length) {
			return;
		}

		var block = el('div', 'vc-gov-block');
		var head = el('div', 'vc-gov-kpis');
		head.appendChild(kpiCard('circuit_breaker', circuit.is_tripped ? L('tripped', 'Tripped') : L('open', 'Open'), circuit.is_tripped ? 'is-bad' : 'is-good'));

		if (circuit.is_tripped) {
			var reset = el('button', 'vc-cap-open');
			reset.type = 'button';
			reset.textContent = L('reset_circuit', 'Reset circuit');
			reset.addEventListener('click', function () {
				vcRest('panel/circuit-reset', { method: 'POST', body: '{}' }).then(function () {
					document.location.reload();
				});
			});
			head.appendChild(reset);
		}

		block.appendChild(head);
		wrap.appendChild(block);
	}

	function renderPeriods(list) {
		periodsWrap.innerHTML = '';
		(list || []).forEach(function (p) {
			var chip = el('button', 'vc-gov-period-chip' + (p === currentPeriod ? ' is-active' : ''));

			chip.type = 'button';
			chip.textContent = p;
			chip.addEventListener('click', function () {
				currentPeriod = p;
				renderSection();
			});
			periodsWrap.appendChild(chip);
		});
	}

	/* ── Section view ────────────────────────────────────────────── */

	var currentSection = 'overview';
	var currentPeriod = '30d';

	function renderSection() {
		body.innerHTML = '';
		periodsWrap.innerHTML = '';

		var govTitles = (VCConfig.gov && VCConfig.gov.titles) || {};
		titleEl.textContent = govTitles[currentSection] || prettyKey(currentSection);

		var spinner = el('div', 'vc-gov-loading');
		spinner.appendChild(el('div', 'vc-gov-spinner'));
		body.appendChild(spinner);

		vcRest(
			'panel/section?section=' + encodeURIComponent(currentSection) + '&period=' + encodeURIComponent(currentPeriod)
		).then(function (res) {
			body.innerHTML = '';

			if (!res || res.state !== 'ok') {
				body.appendChild(el('div', 'vc-gov-empty', L('session_expired', 'Session expired. Log in again to see live data.')));
				return;
			}

			var data = res.data || {};
			var d = data.data || {};

			renderKpis(d.kpi, body);

			if (currentSection === 'overview') {
				renderLiveFeed(data.live, body);
				renderUsage(data, body);
				renderHalt(data, body);
				renderCircuit(data, body);
			}

			renderSectionTables(currentSection, d, body);

			if (currentSection === 'security' && d.coverage && typeof d.coverage === 'object') {
				var flags = el('div', 'vc-gov-kpis');

				Object.keys(d.coverage).forEach(function (k) {
					var on = d.coverage[k];

					flags.appendChild(kpiCard(k, on ? L('on', 'On') : L('off', 'Off'), on ? 'is-good' : ''));
				});
				body.appendChild(flags);
			}

			renderPeriods(d.allowed_periods || ['today', '3d', '7d', '14d', '30d']);

			if (!Object.keys(d).length) {
				body.appendChild(el('div', 'vc-gov-empty', L('no_data', 'No data in this period.')));
			}
		}).catch(function () {
			body.innerHTML = '';
			body.appendChild(el('div', 'vc-gov-empty', L('session_expired', 'Session expired. Log in again to see live data.')));
		});
	}

	/* ── Capabilities view ───────────────────────────────────────── */

	var capState = { q: '', page: 1, more: false, busy: false, category: '', timer: null, observer: null };

	function capBase() {
		return (VCConfig.gov && VCConfig.gov.capabilityBase) || '';
	}

	function openCapability(slug) {
		if (slug) {
			window.open(capBase() + encodeURIComponent(slug), '_blank', 'noopener');
		}
	}

	var MONO_COLORS = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6', '#ef4444', '#14b8a6', '#f97316', '#3b82f6'];

	function monogram(item, logo) {
		var h = 0;
		var s = String(item.id || item.slug || item.title || '');

		for (var i = 0; i < s.length; i++) {
			h = (h * 31 + s.charCodeAt(i)) >>> 0;
		}

		logo.style.background = MONO_COLORS[h % MONO_COLORS.length];
		logo.textContent = (item.title || '?').charAt(0).toUpperCase();
	}

	function capLogo(item, wrap) {
		var logo = el('span', 'vc-cap-logo');
		var url = item.icon_url || (item.integration && item.integration.logo_url) || null;
		var domain = null;

		if (!url && item.integration && item.integration.url) {
			try {
				domain = new URL(item.integration.url).hostname;
			} catch (e) {
				domain = null;
			}
		}

		if (url) {
			var img = el('img');

			img.src = url;
			img.alt = '';
			img.loading = 'lazy';
			img.onerror = function () {
				logo.innerHTML = '';
				monogram(item, logo);
			};
			logo.appendChild(img);
		} else if (domain) {
			var fi = el('img');

			fi.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(domain) + '&sz=128';
			fi.alt = '';
			fi.loading = 'lazy';
			fi.onerror = function () {
				logo.innerHTML = '';
				monogram(item, logo);
			};
			logo.appendChild(fi);
		} else {
			monogram(item, logo);
		}
		wrap.appendChild(logo);
	}

	var ARROW_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>';

	function capRow(item) {
		var row = el('div', 'vc-cap-row');
		var main = el('button', 'vc-cap-row-main');

		main.type = 'button';
		capLogo(item, main);

		var text = el('div', 'vc-cap-row-text');

		text.appendChild(el('h3', 'vc-cap-row-title', item.title || item.slug));

		if (item.short_description) {
			text.appendChild(el('p', 'vc-cap-row-desc', item.short_description));
		}

		main.appendChild(text);
		main.addEventListener('click', function () {
			openCapability(item.slug);
		});
		row.appendChild(main);

		var side = el('div', 'vc-cap-row-side');

		if (item.connected === true) {
			side.appendChild(el('span', 'vc-cap-connected', L('cap_connected', 'Connected')));
		}

		var open = el('a', 'vc-cap-open');

		open.href = capBase() + encodeURIComponent(item.slug || '');
		open.target = '_blank';
		open.rel = 'noopener';
		open.innerHTML = L('cap_open', 'Open in Vinkius') + ' ' + ARROW_SVG;
		side.appendChild(open);
		row.appendChild(side);
		return row;
	}

	function capStateBox(kind, opts) {
		var box = el('div', 'vc-cap-state');
		var icons = {
			search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
			empty: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M8 11h6"/></svg>',
			error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
		};

		box.innerHTML = '<span class="vc-cap-state-icon">' + icons[kind] + '</span>';
		box.appendChild(el('div', 'vc-cap-state-title', opts.title));

		if (opts.hint) {
			box.appendChild(el('div', 'vc-cap-state-hint', opts.hint));
		}
		return box;
	}

	function ensureResults() {
		var r = document.getElementById('vc-cap-results');

		if (!r) {
			r = el('div', 'vc-cap-results');
			r.id = 'vc-cap-results';
			body.appendChild(r);
		}
		return r;
	}

	function loadCapabilities(reset) {
		if (capState.busy) {
			return;
		}

		capState.busy = true;
		var results = ensureResults();
		var append = !reset;

		if (append) {
			var sentinel = document.getElementById('vc-cap-sentinel');

			if (sentinel) {
				sentinel.innerHTML = '';
				sentinel.appendChild(el('div', 'vc-gov-spinner'));
			}
		}

		vcRest(
			'panel/capabilities?q=' + encodeURIComponent(capState.q) +
			'&page=' + capState.page +
			'&category=' + encodeURIComponent(capState.category || '')
		).then(function (res) {
			var d = (res && res.data) || {};

			if (reset) {
				results.innerHTML = '';
			}

			if (reset) {
				var stats = d.stats || {};
				var statsEl = document.querySelector('.vc-cap-stats');

				if (statsEl && (stats.catalog_count || stats.connectors_count)) {
					statsEl.textContent = L('cap_catalog', '%1$s connectors \u00B7 %2$s capabilities')
						.replace('%1$s', num(stats.connectors_count || stats.catalog_count))
						.replace('%2$s', num(stats.capabilities_count || 0));
				}

				renderCategories(d.categories || []);
			}

			var items = d.items || [];

			if (items.length) {
				items.forEach(function (item) {
					results.appendChild(capRow(item));
				});
				capState.more = Boolean(d.has_more);
				capState.busy = false;
				armSentinel();
				return;
			}

			if (reset) {
				if (capState.q && capState.q.length >= 2) {
					results.appendChild(capStateBox('search', {
						title: L('cap_no_matches', 'No matches for "%s"').replace('%s', capState.q),
						hint: L('cap_try', 'Try rephrasing your search.'),
					}));
				} else {
					results.appendChild(capStateBox('empty', {
						title: L('cap_none', 'No connectors in the catalog yet.'),
						hint: null,
					}));
				}
			}
			capState.more = false;
			capState.busy = false;
		}).catch(function () {
			if (reset) {
				results.innerHTML = '';
				results.appendChild(capStateBox('error', {
					title: L('cap_unavailable', 'Search is unavailable right now. Please try again.'),
					hint: null,
				}));
			}
			capState.busy = false;
		});
	}

	function armSentinel() {
		var sentinel = document.getElementById('vc-cap-sentinel');

		if (sentinel) {
			sentinel.remove();
		}

		if (!capState.more) {
			return;
		}

		sentinel = el('div', 'vc-cap-sentinel');
		sentinel.id = 'vc-cap-sentinel';
		sentinel.appendChild(el('div', 'vc-gov-spinner'));
		ensureResults().appendChild(sentinel);

		if (capState.observer) {
			capState.observer.disconnect();
		}

		capState.observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					capState.page += 1;
					loadCapabilities(false);
				}
			});
		}, { rootMargin: '800px 0px' });

		capState.observer.observe(sentinel);
	}

	function renderCategories(cats) {
		var wrap = document.getElementById('vc-cap-cats');

		if (!wrap) {
			wrap = el('div', 'vc-cap-cats');
			wrap.id = 'vc-cap-cats';
			var hero = document.querySelector('.vc-cap-hero');

			if (hero) {
				hero.parentNode.insertBefore(wrap, hero.nextSibling);
			} else {
				return;
			}
		}

		wrap.innerHTML = '';

		var list = [{ slug: '', label: L('cap_all', 'All'), listing_count: null }];

		(cats || []).forEach(function (c) {
			if (c && c.slug && c.slug !== 'other') {
				list.push(c);
			}
		});

		list.forEach(function (c) {
			var chip = el('button', 'vc-cap-cat' + (capState.category === (c.slug || '') ? ' is-active' : ''));

			chip.type = 'button';
			chip.appendChild(el('span', null, c.label || c.slug || L('cap_all', 'All')));

			if (c.listing_count) {
				chip.appendChild(el('span', 'vc-cap-cat-count', String(c.listing_count)));
			}

			chip.addEventListener('click', function () {
				capState.category = c.slug || '';
				capState.q = '';
				var input = document.querySelector('.vc-cap-input');

				if (input) {
					input.value = '';
				}
				wrap.querySelectorAll('.vc-cap-cat').forEach(function (n) {
					n.classList.toggle('is-active', n === chip);
				});
				loadCapabilities(true);
			});
			wrap.appendChild(chip);
		});
	}

	function capHero() {
		var hero = el('div', 'vc-cap-hero');
		var search = el('div', 'vc-cap-search');

		var icon = el('span', 'vc-cap-search-icon');
		icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
		search.appendChild(icon);

		var input = el('input', 'vc-cap-input');

		input.type = 'search';
		input.maxLength = 120;
		input.placeholder = L('cap_search', 'Search capabilities and connectors\u2026');
		input.value = capState.q;
		input.setAttribute('autocomplete', 'off');
		input.addEventListener('input', function () {
			capState.q = input.value;
			capState.category = '';
			capState.page = 1;
			toggleClear();
			debounceSearch();
		});
		search.appendChild(input);

		var clear = el('button', 'vc-cap-clear' + (input.value ? ' is-visible' : ''));

		clear.type = 'button';
		clear.setAttribute('aria-label', L('cap_clear', 'Clear'));
		clear.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
		clear.addEventListener('click', function () {
			input.value = '';
			capState.q = '';
			capState.category = '';
			capState.page = 1;
			toggleClear();
			loadCapabilities(true);
			input.focus();
		});
		search.appendChild(clear);
		hero.appendChild(search);
		hero.appendChild(el('div', 'vc-cap-stats', '\u00A0'));

		function toggleClear() {
			clear.classList.toggle('is-visible', Boolean(input.value));
		}
		return hero;
	}

	function debounceSearch() {
		if (capState.timer) {
			clearTimeout(capState.timer);
		}

		if (!capState.q || capState.q.length < 2) {
			capState.timer = setTimeout(function () {
				capState.page = 1;
				loadCapabilities(true);
			}, 250);
			return;
		}

		capState.timer = setTimeout(function () {
			capState.page = 1;
			loadCapabilities(true);
		}, 250);
	}

	function renderCapabilities() {
		titleEl.textContent = L('cap_title', 'Capabilities');
		periodsWrap.innerHTML = '';
		body.innerHTML = '';

		body.appendChild(capHero());
		loadCapabilities(true);
	}

	/* ── Sidebar navigation + boot ───────────────────────────────── */

	var items = Array.prototype.slice.call(root.querySelectorAll('.vc-gov-item'));

	function selectSection(section) {
		currentSection = section;

		items.forEach(function (btn) {
			btn.classList.toggle('is-active', btn.dataset.section === section);
		});

		if (section === 'capabilities') {
			history.replaceState(null, '', document.location.pathname + document.location.search + '#capabilities');
			renderCapabilities();
			return;
		}

		renderSection();
	}

	items.forEach(function (btn) {
		btn.addEventListener('click', function () {
			selectSection(btn.dataset.section);
		});
	});

	document.addEventListener('keydown', function (e) {
		if (e.metaKey || e.ctrlKey || e.altKey) {
			return;
		}

		var t = document.activeElement;

		if (t && /INPUT|TEXTAREA|SELECT/.test(t.tagName)) {
			return;
		}

		var n = parseInt(e.key, 10);

		if (n >= 1 && n <= 8 && items[n - 1]) {
			e.preventDefault();
			selectSection(items[n - 1].dataset.section);
		}
	});

	selectSection('overview');
})();
