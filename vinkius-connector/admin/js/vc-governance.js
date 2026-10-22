/**
 * Vinkius AI Governance — section pages + capabilities catalog.
 *
 * Each governance page (Mission Control … Compliance & Evidence) reads its
 * section live from the Vinkius API and renders the same KPIs, charts and
 * tables as the Vinkius cloud governance UI (cloudfrontend). Charts run on
 * the vendored ECharts through window.VcCharts (admin/js/vc-charts.js), a
 * 1:1 port of the cloud's chart builders; table rows deep-link to the cloud
 * detail pages. The AI Connections page searches the capabilities catalog.
 *
 * Markup uses native WordPress admin components (postbox, nav-tab, notice,
 * spinner) plus the vc-* card/table/accordion styles, themed by
 * admin/css/*.css.
 *
 * @license GPLv2 or later
 */
(function () {
	'use strict';

	if (typeof VCConfig === 'undefined') {
		return;
	}

	var C = (typeof window !== 'undefined' && window.VcCharts) ? window.VcCharts : null;

	function L(key, fallback) {
		var labels = (VCConfig && VCConfig.labels) || {};

		return labels[key] || fallback;
	}

	/** Interpolate "{x}" templates with local label fallbacks. */
	function TT(key, fallback, vars) {
		return String(L(key, fallback)).replace(/\{(\w+)\}/g, function (m, k) {
			return vars && vars[k] !== undefined ? String(vars[k]) : m;
		});
	}

	function vcRest(path, options) {
		options = options || {};
		options.headers = options.headers || {};
		options.headers['Content-Type'] = 'application/json';
		options.headers['X-WP-Nonce'] = VCConfig.restNonce;

		return fetch(VCConfig.restUrl + path, options).then(function (r) {
			if (r.status === 401 || r.status === 403) {
				var e = new Error('vc_auth');
				e.auth = true;

				throw e;
			}

			return r.json();
		});
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

	function fmt(v) {
		return C ? C.fmt(v) : String(v);
	}

	/* Canonical in-panel loading line: spinner + caption. Every pending
	 * async surface renders one of these (never bare text), so the panel
	 * settles with a predictable rhythm. */
	function vcLoader(text) {
		var p = el('p', 'vc-loading');

		p.appendChild(el('span', 'spinner is-active'));

		// aria-hidden on the decorative spinner, text stays screen-readable.
		p.firstChild.setAttribute('aria-hidden', 'true');
		p.appendChild(el('span', null, text || L('loading', 'Loading…')));
		return p;
	}

	function num(v) {
		if (v === null || v === undefined || v === '') {
			return '\u2014';
		}

		return Number(v).toLocaleString();
	}

	function stateNotice(kind, msg) {
		var div = el('div', 'notice ' + kind);

		div.appendChild(el('p', null, msg));
		return div;
	}

	/* Only a genuine auth failure (401/403, or a not_connected state) is a
	 * "session expired" — a slow/5xx upstream read is a load error, not login. */
	function loadErrorNotice(res, err) {
		var isAuth = (res && res.state === 'not_connected') || (err && err.auth === true);

		return isAuth
			? stateNotice('notice-error', L('session_expired', 'Session expired. Log in again from Settings to see live data.'))
			: stateNotice('notice-warning', L('load_error', 'Data could not be loaded. Try again or use a shorter period.'));
	}

	function postbox(title, content) {
		var box = el('div', 'postbox');
		var head = el('div', 'postbox-header');

		head.appendChild(el('h2', 'hndle', title));
		box.appendChild(head);

		var inside = el('div', 'inside');

		inside.appendChild(content);
		box.appendChild(inside);
		return box;
	}

	/* ── Deep links into the Vinkius cloud (detail pages). ───────────
	 * cloudfrontend routes: /governance/{section}/{id}[/{subId}] and
	 * /governance/requests/{id}. Row clicks open the cloud record. */

	var DETAIL_SECTION = {
		client: 'clients',
		connector: 'connectors',
		cost: 'cost',
		tool: 'tool-performance',
		security: 'security-posture',
		error: 'errors',
		token: 'token-fleet'
	};

	function govBase() {
		return (VCConfig.links && VCConfig.links.governance) || '#';
	}

	function deepLink(kind, id, subId, period) {
		var url = govBase() + '/' + DETAIL_SECTION[kind] + '/' + encodeURIComponent(id);

		if (subId) {
			url += '/' + encodeURIComponent(subId);
		}

		if (period) {
			url += '?period=' + encodeURIComponent(period);
		}

		return url;
	}

	function deepRequest(id, period) {
		return govBase() + '/requests/' + encodeURIComponent(id) + (period ? '?period=' + encodeURIComponent(period) : '');
	}

	function sectionLink(section) {
		return govBase() + '/' + section;
	}

	/* ── KPI cards (port of OverviewKpiCard / KpiStat / hand-built cards) ── */

	var STATUS_LINE = {
		healthy: '#34d399',
		warning: '#fbbf24',
		critical: '#f87171',
		neutral: '#a1a1aa'
	};

	function latencyStatus(ms) {
		if (!C) {
			return 'neutral';
		}

		switch (C.latencyTone(ms)) {
			case 'green':
				return 'healthy';
			case 'amber':
				return 'warning';
			case 'red':
				return 'critical';
			default:
				return 'neutral';
		}
	}

	function reliabilityStatus(r) {
		if (r >= 99) {
			return 'healthy';
		}

		if (r >= 95) {
			return 'warning';
		}

		return 'critical';
	}

	/**
	 * Premium KPI card: label + status dot, big value, subtitle and an
	 * optional inline sparkline (port of OverviewKpiCard.tsx).
	 */
	function kpiPremium(opts) {
		var status = opts.status || 'neutral';
		var card = el('div', 'vc-kpi-card');
		var head = el('div', 'vc-kpi-card-head');

		head.appendChild(el('span', 'vc-kpi-card-label', opts.label));

		if (status !== 'neutral') {
			head.appendChild(el('span', 'vc-kpi-dot vc-kpi-dot--' + status));
		}

		card.appendChild(head);

		var value = el('div', 'vc-kpi-card-value' + (status !== 'neutral' ? ' vc-kpi-value--' + status : ''));

		value.textContent = opts.value;

		if (opts.tooltip) {
			value.title = opts.tooltip;
		}

		card.appendChild(value);

		if (opts.sub) {
			card.appendChild(el('span', 'vc-kpi-card-sub', opts.sub));
		}

		if (opts.spark && opts.spark.length && C && C.available) {
			var spark = el('div', 'vc-kpi-spark');

			card.appendChild(spark);
			C.render(spark, C.sparklineOption({
				values: opts.spark,
				color: opts.accent || STATUS_LINE[status],
				smooth: 0.4,
				floor: 0.85,
				gutter: 0
			}));
		}
		return card;
	}

	/**
	 * Flat KPI card: label (left) + value (right) on one row, optional
	 * progress bar and subtitle below (port of KpiStat.tsx + the
	 * hand-built cards of ErrorsView/SecurityView/ToolPerformanceView).
	 */
	function kpiCard(opts) {
		var card = el('div', 'vc-kpi-card');
		var head = el('div', 'vc-kpi-card-head');

		head.appendChild(el('span', 'vc-kpi-card-label', opts.label));

		if (opts.value !== undefined && opts.value !== null && opts.value !== '') {
			var v = el('span', 'vc-kpi-value-row' + (opts.valueClass ? ' ' + opts.valueClass : ''));
			var vtext = String(opts.value);

			if (vtext.indexOf('<') >= 0) {
				v.innerHTML = vtext;
			} else {
				v.textContent = vtext;
			}

			if (opts.valueTitle) {
				v.title = opts.valueTitle;
			}

			head.appendChild(v);
		}

		card.appendChild(head);

		if (opts.progress && C) {
			var bar = el('div', 'vc-segbar');

			opts.progress.forEach(function (seg) {
				var s = el('div', 'vc-segbar-seg vc-seg--' + seg.cls);

				s.style.width = seg.pct + '%';
				bar.appendChild(s);
			});
			card.appendChild(bar);
		}

		if (opts.sub !== undefined) {
			var sub = el('span', 'vc-kpi-card-sub');

			sub.textContent = opts.sub;
			card.appendChild(sub);
		}

		if (opts.extra) {
			opts.extra(head, card);
		}
		return card;
	}

	/** KPI strip (a row of premium cards, 6-up on wide admin windows). */
	function kpiStrip(cards) {
		var strip = el('div', 'vc-kpi-strip');

		cards.forEach(function (c) {
			strip.appendChild(c);
		});
		return strip;
	}

	/** KPI stat row (port of the cloud's grid-cols-2 @3xl:4 KpiStat grid). */
	function kpiRow(cards) {
		var strip = el('div', 'vc-kpi-strip vc-kpi-strip--2');

		cards.forEach(function (c) {
			strip.appendChild(c);
		});
		return strip;
	}

	/* ── Chart grid (port of the ChartCard rows) ──────────────────── */

	function chartGrid(cards, cls) {
		var grid = el('div', 'vc-chart-grid' + (cls ? ' ' + cls : ''));

		cards.forEach(function (c) {
			if (C && C.available) {
				grid.appendChild(C.chartCard(c.option, c.title, c.subtitle, c.height, c.detailsHref));
			} else {
				var fallback = el('div', 'vc-chart-card');

				fallback.appendChild(el('div', 'vc-tbl-empty', L('no_data_available', 'No data available')));
				grid.appendChild(fallback);
			}
		});
		return grid;
	}

	/* ── Data table card (port of DataTable.tsx) ──────────────────── */

	/**
	 * cols: [{ h, align: 'right'|'center', cell: fn(row)->(node|string) }]
	 * rowClick: fn(row) -> url (opened in a new tab when present).
	 */
	function tableCard(opts) {
		var card = el('div', 'vc-table-card');

		if (opts.live) {
			var headRow = el('div', 'vc-tbl-head-row');

			headRow.appendChild(el('span', 'vc-live-dot'));
			var title = el('h3', 'vc-tbl-title', opts.title);
			var wrap = el('div', 'vc-tbl-head');

			wrap.appendChild(headRow);

			if (opts.subtitle) {
				wrap.appendChild(el('p', 'vc-tbl-sub', opts.subtitle));
			}

			card.appendChild(wrap);
		} else if (opts.title || opts.subtitle) {
			var head2 = el('div', 'vc-tbl-head');

			if (opts.title) {
				head2.appendChild(el('h3', 'vc-tbl-title', opts.title));
			}

			if (opts.subtitle) {
				head2.appendChild(el('p', 'vc-tbl-sub', opts.subtitle));
			}

			card.appendChild(head2);
		}

		if (!opts.rows || !opts.rows.length) {
			card.appendChild(el('div', 'vc-tbl-empty', opts.empty || L('no_data_available', 'No data available')));
			return card;
		}

		var table = el('table', 'vc-tbl' + (opts.rowClick ? ' is-clickable' : ''));
		var thead = el('thead');
		var hrow = el('tr');

		opts.cols.forEach(function (col) {
			hrow.appendChild(el('th', col.align ? 'vc-' + col.align : '', col.h));
		});
		thead.appendChild(hrow);
		table.appendChild(thead);

		var tbody = el('tbody');

		opts.rows.forEach(function (r) {
			var tr = el('tr');

			opts.cols.forEach(function (col) {
				var td = el('td', col.align === 'right' ? 'vc-right vc-mono' : col.align === 'center' ? 'vc-center' : '');

				if (col.cls) {
					td.className += ' ' + col.cls;
				}

				var out = col.cell ? col.cell(r) : td_text(r, col.key);
				td.innerHTML = '';

				// Cells may return a Node, an HTML string (the string cells
				// escape their own interpolated values) or a bare number.
				if (out === null || out === undefined) {
					td.textContent = '';
				} else if (out.nodeType) {
					td.appendChild(out);
				} else if (typeof out === 'string') {
					td.innerHTML = out;
				} else {
					td.textContent = out;
				}
				tr.appendChild(td);
			});

			if (opts.rowClick) {
				var url = opts.rowClick(r);

				if (url) {
					tr.title = L('drill_hint', 'Drill into this record');
					tr.addEventListener('click', function () {
						/* In-panel drill when the boot context is armed for the
						 * section; otherwise fall back to the cloud page. */
						if (window.__vcDrillHook && window.__vcDrillHook(url)) {
							return;
						}
						window.open(url, '_blank', 'noopener');
					});
				}
			}
			tbody.appendChild(tr);
		});
		table.appendChild(tbody);

		var wrap = el('div', 'vc-tbl-wrap');

		wrap.appendChild(table);
		card.appendChild(wrap);
		return card;
	}

	function td_text(r, key) {
		var v = r[key];

		return (v === null || v === undefined) ? '\u2014' : String(v);
	}

	/** Pagination bar: "from to of total" + numbered pages (port of PaginationBar). */
	function paginationBar(pag, onPage) {
		if (!pag || !pag.last_page || pag.last_page <= 1) {
			return null;
		}

		var bar = el('div', 'vc-pagbar');
		var from = (pag.current_page - 1) * pag.per_page + 1;
		var to = Math.min(pag.current_page * pag.per_page, pag.total || 0);
		var range = el('span', null, TT('pag_range', '{from} to {to} of {total}', { from: from, to: to, total: pag.total || 0 }));
		var pages = el('div', 'vc-pagbar-pages');

		function btn(label, page, opts) {
			opts = opts || {};
			var b = el('button', opts.current ? 'is-current' : '', label);

			b.type = 'button';

			if (opts.disabled) {
				b.disabled = true;
			} else if (!opts.current) {
				b.addEventListener('click', function () {
					onPage(page);
				});
			}
			pages.appendChild(b);
		}

		btn(L('prev_page', 'Previous'), pag.current_page - 1, { disabled: pag.current_page <= 1 });

		function around(cur, last) {
			var set = [1, last, cur - 1, cur, cur + 1];
			var out = [];
			var prev = 0;

			set.forEach(function (p) {
				if (p < 1 || p > last || p === prev) {
					return;
				}

				if (p - prev > 1) {
					out.push('…');
				}
				out.push(p);
				prev = p;
			});
			return out;
		}

		around(pag.current_page, pag.last_page).forEach(function (p) {
			if (p === '…') {
				pages.appendChild(el('span', 'vc-pagbar-ellipsis', '…'));
			} else {
				btn(String(p), p, { current: p === pag.current_page });
			}
		});

		btn(L('next_page', 'Next'), pag.current_page + 1, { disabled: pag.current_page >= pag.last_page });
		bar.appendChild(range);
		bar.appendChild(pages);
		return bar;
	}

	/* ── AI briefing card (port of AiBriefingCard) ────────────────── */

	var BRIEFING_SCOPE = {
		overview: { scope: 'overview', desc: 'briefing_desc_overview' },
		clients: { scope: 'clients', desc: 'briefing_desc_clients' },
		connectors: { scope: 'connectors', desc: 'briefing_desc_connectors' },
		'token-fleet': { scope: 'token-fleet', desc: 'briefing_desc_token_fleet' },
		cost: { scope: 'cost', desc: 'briefing_desc_cost' },
		'tool-performance': { scope: 'tool-performance', desc: 'briefing_desc_tool_performance' },
		security: { scope: 'security-posture', desc: 'briefing_desc_security' },
		errors: { scope: 'errors', desc: 'briefing_desc_errors' }
	};

	var BRAIN_SVG = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5a3 3 0 1 1 3 3v0a4 4 0 1 1-3-3Z"></path><path d="M9 12a4 4 0 0 1-4 4 4 4 0 1 0 4-4Z"></path><path d="M15 8a4 4 0 0 1 4 4 4 4 0 1 0-4-4Z"></path><path d="M12 15a3 3 0 1 0 3 3v0a4 4 0 0 0-3-3Z"></path></svg>';

	function briefingCard(section, ctx) {
		var spec = BRIEFING_SCOPE[section];

		if (!spec || !C) {
			return null;
		}

		var box = el('div', 'vc-briefing');
		var icon = el('div', 'vc-briefing-icon');

		icon.innerHTML = BRAIN_SVG;
		box.appendChild(icon);

		var main = el('div');
		main.appendChild(el('h3', 'vc-briefing-title', L('briefing_title', 'AI Briefing')));
		var bodyEl = el('p', 'vc-briefing-body');
		bodyEl.textContent = '…';
		main.appendChild(bodyEl);
		box.appendChild(main);

		function locked() {
			bodyEl.className = 'vc-briefing-body';
			box.classList.add('vc-briefing-locked');
			bodyEl.textContent = L(spec.desc, '');

			var pill = el('a', 'vc-briefing-unlock', L('briefing_unlock', 'Upgrade to unlock'));

			pill.href = (VCConfig.gov && VCConfig.gov.upgrade) || '#';
			pill.target = '_blank';
			pill.rel = 'noopener';
			main.appendChild(pill);
		}

		if (ctx.knownFree) {
			locked();
			return box;
		}

		vcRest('panel/briefing?scope=' + encodeURIComponent(spec.scope) + '&period=' + encodeURIComponent(ctx.period))
			.then(function (res) {
				var data = res && res.data ? res.data : {};

				if (data.text) {
					bodyEl.textContent = data.text;
				} else if (data.is_free === true) {
					locked();
				} else {
					bodyEl.textContent = L(spec.desc, '—');
				}
			})
			.catch(function () {
				bodyEl.textContent = L(spec.desc, '—');
			});
		return box;
	}

	/* ── Period tabs (FilterHeader's segmented control) ───────────── */

	var PERIOD_LABEL = {
		today: 'period_today',
		'3d': 'period_3d',
		'7d': 'period_7d',
		'14d': 'period_14d',
		'30d': 'period_30d'
	};

	function renderPeriods(list, active, onChange) {
		var wrap = document.getElementById('vc-gov-periods');

		if (!wrap) {
			return;
		}

		wrap.innerHTML = '';
		(list || ['today', '3d', '7d', '14d', '30d']).forEach(function (p) {
			var tab = el('a', 'nav-tab' + (p === active ? ' nav-tab-active' : ''), L(PERIOD_LABEL[p] || p, p));

			tab.href = '#vc-gov-periods';
			tab.addEventListener('click', function (e) {
				e.preventDefault();
				onChange(p);
			});
			wrap.appendChild(tab);
		});
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Section renderers — one per cloud view
	 * ═══════════════════════════════════════════════════════════ */

	/* ── Mission Control (OverviewView) ───────────────────────────── */

	function outcomeMeta(e) {
		var policyActive = (e.dlp_redactions || 0) > 0 || e.finops_truncated === true;
		var msg = (e.error_message || '').trim();

		if (e.status_code >= 500 && e.error_origin === 'vinkius') {
			return {
				label: L('ov_vinkius_err', 'Vinkius Err'),
				cls: 'vc-outcome--vinkius',
				title: msg || L('ov_vinkius_err_title', 'Vinkius error {code}. Failure originated inside the Vinkius proxy.').replace('{code}', e.status_code)
			};
		}

		if (e.status_code < 400) {
			if (policyActive) {
				var parts = [];

				if ((e.dlp_redactions || 0) > 0) {
					parts.push(TT(e.dlp_redactions > 1 ? 'ov_dlp_many' : 'ov_dlp_one', e.dlp_redactions > 1 ? '{count} DLP redactions' : '{count} DLP redaction', { count: e.dlp_redactions }));
				}
				if (e.finops_truncated) {
					parts.push(L('ov_enforced_finops', 'FinOps truncated oversized payload'));
				}
				return {
					label: L('ov_enforced', 'Enforced'),
					cls: 'vc-outcome--enforced',
					title: L('ov_enforced_title', 'Vinkius policy applied in-flight: {parts}. Upstream returned {code}. Enforcement working as designed.').replace('{parts}', parts.join(' · ')).replace('{code}', e.status_code)
				};
			}
			return {
				label: L('ov_ok', 'OK'),
				cls: 'vc-outcome--ok',
				title: msg || L('ov_ok_title', 'Upstream returned {code}.').replace('{code}', e.status_code)
			};
		}

		if (e.status_code < 500) {
			return {
				label: L('ov_agent_err', 'Agent Err'),
				cls: 'vc-outcome--agent',
				title: msg || L('ov_agent_err_title', 'Client error {code}. The caller sent a bad request.').replace('{code}', e.status_code)
			};
		}

		return {
			label: L('ov_upstream_err', 'Upstream Err'),
			cls: 'vc-outcome--upstream',
			title: msg || L('ov_upstream_err_title', 'Upstream error {code}. The upstream provider failed.').replace('{code}', e.status_code)
		};
	}

	function renderLiveFeed(feed, ctx) {
		var PAGE = 25;
		var rows = (feed && feed.length > PAGE) ? feed.slice(0, PAGE) : (feed || []);
		var card = tableCard({
			live: true,
			title: L('ov_feed_title', 'Live Activity'),
			subtitle: L('ov_feed_sub', 'Latest capability executions across all connectors'),
			empty: L('ov_feed_empty', 'No activity recorded in this period.'),
			rows: rows,
			cols: [
				{
					h: L('tb_traces', 'Trace'),
					align: 'center',
					cell: function (e) {
						if (!e.trace_id) {
							return '';
						}

						var d = el('span', null, '');
						d.title = 'Trace ' + e.trace_id;

						d.appendChild(el('span', 'vc-trace-dot'));
						return d;
					}
				},
				{ h: L('col_connector', 'Connector'), cell: function (e) { return '<span class="vc-bold">' + String(e.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_capability', 'Capability'), cell: function (e) { return '<span class="vc-mono-chip">' + String(e.tool_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('tb_token', 'Token'), cell: function (e) { return '<span class="vc-muted">' + String(e.token_name || L('not_available', 'Not available')).replace(/</g, '&lt;') + '</span>'; } },
				{
					h: L('ov_outcome', 'Outcome'),
					cell: function (e) {
						var m = outcomeMeta(e);
						var s = el('span', 'vc-outcome ' + m.cls);

						s.title = m.title;
						s.textContent = m.label + ' ';
						s.appendChild(el('span', 'vc-outcome-code', String(e.status_code || '')));
						return s;
					}
				},
				{
					h: L('col_api', 'API'),
					align: 'right',
					cell: function (e) {
						var s = el('span', null, C ? C.formatLatency(e.api_response_time_ms) : '—');
						s.className = C ? C.latencyTextClass(e.api_response_time_ms) : '';
						s.title = C ? C.latencyTooltip(e.response_time_ms, e.api_response_time_ms) : '';
						return s;
					}
				},
				{
					h: L('col_overhead', 'Overhead'),
					align: 'right',
					cell: function (e) {
						var o = C ? C.overheadMs(e.response_time_ms, e.api_response_time_ms) : null;
						var s = el('span', null, C ? C.formatLatency(o) : '—');
						s.className = C ? C.latencyTextClass(o) : '';
						return s;
					}
				},
				{
					h: L('col_total', 'Total'),
					align: 'right',
					cell: function (e) {
						var s = el('span', null, C ? C.formatLatency(e.response_time_ms) : '—');
						s.className = C ? C.latencyTextClass(e.response_time_ms) : '';
						return s;
					}
				},
				{
					h: L('tb_time', 'Time'),
					align: 'right',
					cell: function (e) {
						var s = el('span', 'vc-muted', C ? C.timeAgo(e.created_at_iso || e.created_at) : String(e.created_at_iso || e.created_at || ''));

						if (e.created_at_iso) {
							s.title = e.created_at_iso;
						}
						return s;
					}
				}
			],
			rowClick: function (e) {
				return e.id ? deepRequest(e.id, ctx.period) : null;
			}
		});
		var more = feed && feed.length > PAGE ? feed.length - PAGE : 0;

		if (more > 0) {
			card.appendChild(el('div', 'vc-tbl-empty', L('ov_feed_more', 'Showing the latest {count} of {total} in this period.', { count: PAGE, total: feed.length }).replace('{count}', PAGE).replace('{total}', feed.length)));
		}
		return card;
	}

	function renderOverview(d, ctx, wrap) {
		var kpi = d.kpi || {};
		var hourly = ctx.period === 'today' || ctx.period === '3d';
		var cadence = L(hourly ? 'ov_cadence_hourly' : 'ov_cadence_daily', hourly ? 'Hourly' : 'Daily');

		wrap.appendChild(briefingCard('overview', ctx));

		/* Row 1: hero KPI strip (6 cards with sparklines). */
		var sparks = {
			requests: (d.latency_7d || []).map(function (p) { return p.total_requests; }),
			latency: (d.latency_7d || []).map(function (p) { return p.avg_latency_ms; }),
			reliability: (d.error_timeline || []).map(function (p) { return 100 - (p.vinkius_error_rate || 0); }),
			tokens: (d.latency_7d || []).map(function (p) { return p.total_requests * 30; }),
			security: (d.security_timeline || []).map(function (p) { return (p.dlp_redactions || 0) + (p.finops_truncations || 0); }),
			cost: (d.cost_trend || []).map(function (p) { return (p.cost || 0) * 1000; })
		};

		var cards = [
			kpiPremium({
				label: L('ov_kpi_requests', 'Requests'),
				value: kpi.total_requests !== undefined ? fmt(kpi.total_requests) : L('not_available', 'Not available'),
				sub: L('ov_kpi_requests_hint', 'Total in this period'),
				status: 'neutral',
				accent: '#3b82f6',
				spark: sparks.requests
			}),
			kpiPremium({
				label: L('ov_kpi_avg_latency', 'Avg Latency'),
				value: kpi.avg_latency_ms !== undefined ? C.formatLatency(kpi.avg_latency_ms) : L('not_available', 'Not available'),
				sub: kpi.avg_api_latency_ms != null
					? TT('api_overhead_sub', 'API: {apiValue} · Overhead: {overhead}', { apiValue: C.formatLatency(kpi.avg_api_latency_ms), overhead: C.formatLatency(Math.max(0, kpi.avg_latency_ms - kpi.avg_api_latency_ms)) })
					: L('ov_kpi_no_data', 'No data'),
				status: kpi.avg_latency_ms !== undefined ? latencyStatus(kpi.avg_latency_ms) : 'neutral',
				accent: '#f59e0b',
				spark: sparks.latency
			}),
			kpiPremium({
				label: L('ov_kpi_reliability', 'Vinkius Reliability'),
				value: kpi.vinkius_reliability !== undefined ? kpi.vinkius_reliability + '%' : L('not_available', 'Not available'),
				sub: TT('error_origin_breakdown', 'Agent: {agentCount} · Upstream: {upstreamCount} · Vinkius: {vinkiusCount}', {
					agentCount: kpi.client_errors || 0,
					upstreamCount: kpi.upstream_errors || 0,
					vinkiusCount: kpi.vinkius_errors || 0
				}),
				status: kpi.vinkius_reliability !== undefined ? reliabilityStatus(kpi.vinkius_reliability) : 'neutral',
				accent: '#34d399',
				spark: sparks.reliability
			}),
			kpiPremium({
				label: L('ov_kpi_tokens', 'Tokens'),
				value: kpi.tokens_consumed !== undefined ? fmt(kpi.tokens_consumed) : L('not_available', 'Not available'),
				sub: L('ov_kpi_tokens_hint', 'AI tokens processed'),
				status: 'neutral',
				accent: '#8b5cf6',
				spark: sparks.tokens
			}),
			kpiPremium({
				label: L('ov_kpi_dlp_protected', 'DLP Protected'),
				value: kpi.pii_redactions !== undefined ? fmt(kpi.pii_redactions) : L('not_available', 'Not available'),
				sub: L('ov_kpi_dlp_hint', 'Sensitive data redacted'),
				status: kpi.pii_redactions > 0 ? 'healthy' : 'neutral',
				accent: '#06b6d4',
				spark: sparks.security
			}),
			kpiPremium({
				label: L('ov_kpi_cost_saved', 'Cost Saved'),
				value: kpi.payloads_truncated !== undefined ? fmt(kpi.payloads_truncated) : L('not_available', 'Not available'),
				sub: L('ov_kpi_cost_hint', 'FinOps truncations applied'),
				status: kpi.payloads_truncated > 0 ? 'healthy' : 'neutral',
				accent: '#10b981',
				spark: sparks.cost
			})
		];
		wrap.appendChild(kpiStrip(cards));

		if (C && C.available) {
			/* Row 2: heatmap + request volume. */
			wrap.appendChild(chartGrid([
				{ option: C.heatmapOption(d.heatmap || []), title: L('ov_chart_agents', 'AI Agents Activities'), subtitle: L(hourly ? 'ov_agents_sub_hourly' : 'ov_agents_sub_daily', hourly ? 'Interactions per hour' : 'Interactions per day'), height: 180 },
				{ option: C.requestVolumeOption(d.latency_7d || []), title: L('ov_chart_volume', 'Request Volume & Latency'), subtitle: TT('ov_volume_sub', '{cadence} dual-axis overview', { cadence: cadence }) }
			]));

			/* Row 3: connectors + security + cost. */
			wrap.appendChild(chartGrid([
				{ option: C.topConnectorsOption(d.top_connectors || []), title: L('ov_chart_top', 'Top Connectors'), subtitle: L('ov_top_sub', 'Volume by connector · color = latency health'), height: 200, detailsHref: sectionLink('connectors') },
				{ option: C.securityTimelineOption(d.security_timeline || []), title: L('ov_chart_firewall', 'Firewall Enforcement'), subtitle: TT('ov_firewall_sub', '{cadence} DLP + FinOps actions', { cadence: cadence }), height: 200, detailsHref: sectionLink('security') },
				{ option: C.costSummaryOption(d.cost_trend || []), title: L('ov_chart_cost', 'Cost vs Savings'), subtitle: TT('ov_cost_sub', '{cadence} token cost attribution', { cadence: cadence }), height: 200, detailsHref: sectionLink('cost') }
			], 'vc-chart-grid--3'));

			/* Row 4: failures + capability matrix. */
			wrap.appendChild(chartGrid([
				{ option: C.errorSummaryOption(d.error_timeline || []), title: L('ov_chart_failures', 'Failure Breakdown'), subtitle: TT('ov_failures_sub', '{cadence} requests · Agent / Upstream / Vinkius failures', { cadence: cadence }), detailsHref: sectionLink('errors') },
				{ option: C.toolRankingOption(d.tool_slowest || [], d.tool_failing || []), title: L('ov_chart_matrix', 'Capability Health Matrix'), subtitle: L('ov_matrix_sub', 'Latency × Failure rate · bubble size = request volume'), detailsHref: sectionLink('tool-performance') }
			]));
		}

		/* Row 5: live activity feed (cloud columns). */
		var live = (ctx.data && ctx.data.live) || {};
		var feed = (live && Array.isArray(live.live_feed) ? live.live_feed : null) || (Array.isArray(live) ? live : []) || [];

		if (feed.length) {
			wrap.appendChild(renderLiveFeed(feed, ctx));
		}
	}

	/* ── Agent Activity (ClientsView) ─────────────────────────────── */

	function renderClients(d, ctx, wrap) {
		wrap.appendChild(briefingCard('clients', ctx));

		var perSession = d.total_connections > 0 ? (d.total_tool_calls / d.total_connections).toFixed(1) : 0;

		wrap.appendChild(kpiRow([
			kpiCard({ label: L('cl_kpi_connections', 'Connections'), value: fmt(d.total_connections), sub: L('cl_kpi_connections_hint', 'Active sessions in period') }),
			kpiCard({ label: L('tb_capability_requests', 'Capability Requests'), value: fmt(d.total_tool_calls), sub: TT('cl_kpi_req_per_session', '{count} requests/session', { count: perSession }) }),
			kpiCard({ label: L('cl_kpi_data_transfer', 'Data Transfer'), value: C ? C.formatBytes(d.total_data_bytes) : String(d.total_data_bytes || 0), sub: L('cl_kpi_data_transfer_hint', 'Total payload volume') }),
			kpiCard({ label: L('cl_kpi_avg_session', 'Avg Session'), value: C ? C.formatDuration(d.avg_session_seconds) : String(d.avg_session_seconds || 0), sub: L('cl_kpi_avg_session_hint', 'Average connection duration') })
		]));

		if (C && C.available) {
			wrap.appendChild(chartGrid([
				{ option: C.clientStackedOption(d.by_client || []), title: L('cl_chart_token_title', 'Token Distribution'), subtitle: L('cl_chart_token_sub', 'Connections per client, stacked by token'), height: 220 },
				{ option: C.toolCallsBarOption(d.clients || []), title: L('cl_chart_cap_title', 'Capability Usage by Client'), subtitle: L('cl_chart_cap_sub', 'Total capability requests · color = latency health'), height: 220 }
			]));
		}

		var latTxt = function (v) {
			return function () {
				var s = el('span', null, C ? C.formatLatency(v) : String(v || ''));

				s.className = C ? C.latencyTextClass(v) : '';
				return s;
			};
		};

		wrap.appendChild(tableCard({
			empty: L('clients_empty', 'No AI client connections recorded in this period.'),
			rows: d.clients || [],
			cols: [
				{
					h: L('tb_ai_client', 'AI Client'),
					cell: function (c) {
						var span = el('span', 'vc-bold', c.name);
						var wrapEl = el('span');

						wrapEl.appendChild(span);

						if (c.client_version) {
							wrapEl.appendChild(el('span', 'vc-chip', 'v' + c.client_version));
						}
						if (c.transport) {
							wrapEl.appendChild(el('span', 'vc-chip', c.transport));
						}
						return wrapEl;
					}
				},
				{ h: L('tb_connections', 'Connections'), align: 'right', cell: function (c) { return c.connections; } },
				{ h: L('tb_capability_requests', 'Capability Requests'), align: 'right', cell: function (c) { return c.tool_calls.toLocaleString(); } },
				{ h: L('tb_data_transfer', 'Data Transfer'), align: 'right', cell: function (c) { return C ? C.formatBytes(c.data_transfer_bytes) : '—'; } },
				{ h: L('col_avg_latency', 'Avg Latency'), align: 'right', cell: function (c) { return latTxt(c.avg_latency_ms)(); } },
				{
					h: L('col_errors', 'Errors'),
					align: 'right',
					cell: function (c) {
						var s = el('span', c.errors > 0 ? 'vc-bad vc-bold' : 'vc-muted', String(c.errors || 0));

						return s;
					}
				},
				{ h: L('col_capabilities', 'Capabilities'), align: 'right', cell: function (c) { return c.unique_tools; } },
				{ h: L('cl_col_connectors', 'Connectors'), align: 'right', cell: function (c) { return c.unique_servers; } },
				{ h: L('tb_avg_session', 'Avg Session'), align: 'right', cell: function (c) { return C ? C.formatDuration(c.avg_session_seconds) : '—'; } }
			],
			rowClick: function (c) {
				return deepLink('client', c.name, null, ctx.period);
			}
		}));
	}

	/* ── Connector Traffic (ConnectorsView) ───────────────────────── */

	function renderConnectors(d, ctx, wrap) {
		wrap.appendChild(briefingCard('connectors', ctx));

		var connectors = d.connectors || [];
		var totalRequests = 0, calls = 0, totalLat = 0, totalApi = 0;

		connectors.forEach(function (c) {
			totalRequests += c.total_calls;
			calls += c.total_calls;
			totalLat += c.avg_latency_ms * c.total_calls;
			totalApi += (c.avg_api_latency_ms || 0) * c.total_calls;
		});
		var avgLatency = calls > 0 ? Math.round(totalLat / calls) : 0;
		var avgApi = calls > 0 ? Math.round(totalApi / calls) : 0;

		/* Peak hour: busiest hour bucket across the week, "12 PM" style. */
		var peakHour = L('not_available', 'Not available');

		if (d.volume && d.volume.length) {
			var hourSums = {};

			d.volume.forEach(function (e) {
				hourSums[e.hour] = (hourSums[e.hour] || 0) + e.value;
			});
			var top = null, topKey = -1;

			Object.keys(hourSums).forEach(function (h) {
				if (hourSums[h] > topKey) {
					topKey = hourSums[h];
					top = +h;
				}
			});
			if (top !== null) {
				var hh = top % 12 === 0 ? 12 : top % 12;
				peakHour = hh + ' ' + (top < 12 ? L('time_am', 'AM') : L('time_pm', 'PM'));
			}
		}

		wrap.appendChild(kpiRow([
			kpiCard({ label: L('cn_kpi_active', 'Active Connectors'), value: String(connectors.length), sub: L('cn_kpi_active_hint', 'Connectors with traffic in period') }),
			kpiCard({ label: L('cn_kpi_total_requests', 'Total Requests'), value: fmt(totalRequests), sub: L('across_all_connectors', 'Across all connectors') }),
			kpiCard({
				label: L('col_avg_latency', 'Avg Latency'),
				value: C ? C.formatLatency(avgLatency) : String(avgLatency),
				valueClass: C ? C.latencyTextClass(avgLatency) : '',
				valueTitle: C ? C.latencyTooltip(avgLatency, avgApi) : '',
				progress: [
					{ cls: 'emerald', pct: Math.min((avgApi / Math.max(avgLatency, 1)) * 100, 100) },
					{ cls: 'amber', pct: Math.min(((avgLatency - avgApi) / Math.max(avgLatency, 1)) * 100, 100) }
				],
				sub: TT('api_overhead_sub', 'API: {apiValue} · Overhead: {overhead}', {
					apiValue: C ? C.formatLatency(avgApi) : '—',
					overhead: C ? C.formatLatency(Math.max(0, avgLatency - avgApi)) : '—'
				})
			}),
			kpiCard({ label: L('cn_kpi_peak_hour', 'Peak Hour'), value: peakHour, sub: L('cn_kpi_peak_hour_hint', 'Highest traffic window') })
		]));

		if (C && C.available) {
			wrap.appendChild(chartGrid([
				{ option: C.punchCardOption(d.volume || []), title: L('cn_chart_traffic_title', 'Traffic Distribution'), subtitle: L('cn_traffic_sub', 'Requests by day of week × hour'), height: 320 },
				{ option: C.connectorBarOption(connectors), title: L('cn_chart_volume_title', 'Request Volume by Connector'), subtitle: L('cn_volume_sub', 'Total requests · color = latency health'), height: 320 }
			]));
		}

		function healthDot(ms) {
			var tone = C ? C.latencyTone(ms) : 'unknown';
			var dots = { green: 'vc-dot-ok', amber: 'vc-dot-warn', red: 'vc-dot-bad', unknown: 'vc-dot-mut' };
			var s = el('span', 'vc-health');

			s.appendChild(el('span', 'vc-dot ' + dots[tone]));

			if (C) {
				s.appendChild(el('span', null, C.latencyToneLabel(ms)));
			}
			return s;
		}

		wrap.appendChild(tableCard({
			empty: L('connectors_empty', 'No connectors recorded in this period.'),
			rows: connectors,
			cols: [
				{
					h: L('tb_connector', 'Connector'),
					cell: function (c) {
						var wrapEl = el('span');

						wrapEl.appendChild(el('span', 'vc-bold', c.name));

						if (c.total_calls > 2000) {
							wrapEl.appendChild(el('span', 'vc-chip', L('high_volume', 'High Volume')));
						}
						return wrapEl;
					}
				},
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (c) { return c.total_calls.toLocaleString(); } },
				{ h: L('col_tokens', 'Tokens'), align: 'right', cell: function (c) { return c.token_count > 0 ? c.token_count.toLocaleString() : '—'; } },
				{
					h: L('col_api', 'API'),
					align: 'right',
					cell: function (c) {
						var s = el('span', null, C ? C.formatLatency(c.avg_api_latency_ms) : '—');

						s.className = C ? C.latencyTextClass(c.avg_api_latency_ms) : '';
						s.title = C ? C.latencyTooltip(c.avg_latency_ms, c.avg_api_latency_ms) : '';
						return s;
					}
				},
				{
					h: L('col_overhead', 'Overhead'),
					align: 'right',
					cell: function (c) {
						var o = Math.max(0, c.avg_latency_ms - (c.avg_api_latency_ms || 0));
						var s = el('span', null, C ? C.formatLatency(o) : '—');

						s.className = C ? C.latencyTextClass(o) : '';
						s.title = C ? C.latencyTooltip(c.avg_latency_ms, c.avg_api_latency_ms) : '';
						return s;
					}
				},
				{
					h: L('col_total', 'Total'),
					align: 'right',
					cell: function (c) {
						var s = el('span', 'vc-bold', C ? C.formatLatency(c.avg_latency_ms) : '—');

						s.className = 'vc-bold ' + (C ? C.latencyTextClass(c.avg_latency_ms) : '');
						s.title = C ? C.latencyTooltip(c.avg_latency_ms, c.avg_api_latency_ms) : '';
						return s;
					}
				},
				{ h: L('tb_req_per_day', 'Req/Day'), align: 'right', cell: function (c) { return Math.round(c.total_calls / 30).toLocaleString(); } },
				{ h: L('col_health', 'Health'), align: 'right', cell: healthDot }
			],
			rowClick: function (c) {
				return deepLink('connector', c.id, null, ctx.period);
			}
		}));
	}

	/* ── Access Tokens (TokenFleetView) ───────────────────────────── */

	function renderTokenFleet(d, ctx, wrap) {
		wrap.appendChild(briefingCard('token-fleet', ctx));

		var mcps = d.mcps || [];
		var totalTokens = 0, totalRequests = 0, calls = 0, latTotal = 0;

		mcps.forEach(function (m) {
			totalTokens += m.token_count || 0;
			totalRequests += m.total_requests || 0;

			if (m.total_requests > 0) {
				latTotal += m.avg_latency_ms * m.total_requests;
				calls += m.total_requests;
			}
		});
		var avgLatency = calls > 0 ? Math.round(latTotal / calls) : null;

		wrap.appendChild(kpiRow([
			kpiCard({ label: L('tf_kpi_connectors', 'Connectors'), value: mcps.length.toLocaleString(), sub: L('tf_connectors_hint', 'With active tokens') }),
			kpiCard({ label: L('tf_kpi_total_tokens', 'Total Tokens'), value: totalTokens.toLocaleString(), sub: L('tf_across_connectors', 'Across all connectors') }),
			kpiCard({ label: L('tf_kpi_total_requests', 'Total Requests'), value: fmt(totalRequests), sub: L('tf_requests_hint', 'In selected period') }),
			kpiCard({
				label: L('tf_kpi_avg_latency', 'Avg Latency'),
				value: C ? C.formatLatency(avgLatency) : L('not_available', 'Not available'),
				valueClass: C ? C.latencyTextClass(avgLatency) : '',
				progress: avgLatency !== null ? [{ cls: 'emerald', pct: 0 }] : null,
				sub: C ? C.latencyToneLabel(avgLatency) : ''
			})
		]));

		/* Search + accordion of MCPs with inline latency sparklines. */
		var search = el('div', 'vc-search');

		search.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>';
		var input = el('input');

		input.type = 'search';
		input.placeholder = L('tf_search', 'Search MCP or token name...');
		search.appendChild(input);
		wrap.appendChild(search);

		var acc = el('div', 'vc-acc');
		var expanded = null;
		var tokenCache = {};

		function buildAll() {
			acc.innerHTML = '';
			expanded = null;

			var visible = mcps.filter(function (m) {
				return !input.value || (m.name || '').toLowerCase().indexOf(input.value.trim().toLowerCase()) >= 0;
			});

			if (!visible.length) {
				acc.innerHTML = '';
				var empty = el('div', 'vc-tbl-empty', L('tf_empty_connectors', 'No connectors with tokens found.'));

				if (input.value) {
					empty.appendChild(el('p', null, L('tf_try_different', 'Try a different search term.')));
				}
				acc.appendChild(empty);
				return;
			}

			visible.forEach(function (m) {
				acc.appendChild(row(m));
			});
		}

		function row(m) {
			var r = el('div', 'vc-acc-row');
			var head = el('button', 'vc-acc-head');

			head.type = 'button';
			head.innerHTML = '<svg class="vc-acc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';

			var avatar = el('span', 'vc-avatar', (m.name || '?').charAt(0).toUpperCase());
			head.appendChild(avatar);

			var main = el('div', 'vc-acc-main');
			var titleRow = el('div', 'vc-acc-title-row');

			titleRow.appendChild(el('span', 'vc-acc-name', m.name));
			titleRow.appendChild(el('span', 'vc-chip', TT(m.token_count === 1 ? 'tf_token_one' : 'tf_token_many', m.token_count === 1 ? '{count} token' : '{count} tokens', { count: m.token_count || 0 })));
			main.appendChild(titleRow);

			var sub = el('div', 'vc-acc-sub');
			sub.textContent = TT('tf_req', '{count} req', { count: fmt(m.total_requests || 0) }) + '  ·  ' + TT('tf_avg', '{value} avg', { value: C ? C.formatLatency(m.avg_latency_ms) : '—' });
			main.appendChild(sub);
			head.appendChild(main);

			if (C && C.available && m.sparkline && m.sparkline.length) {
				var spark = el('div', 'vc-acc-spark');

				head.appendChild(spark);
				C.render(spark, C.tokenSparklineOption(m.sparkline));
			}
			r.appendChild(head);

			var band = el('div', 'vc-acc-band');
			band.style.display = 'none';
			r.appendChild(band);

			head.addEventListener('click', function () {
				var isOpen = band.style.display !== 'none';

				if (isOpen) {
					band.style.display = 'none';
					r.classList.remove('is-open');
					expanded = null;
					return;
				}
				band.style.display = '';
				r.classList.add('is-open');
				expanded = m.id;
				loadTokens(m, band);
			});
			return r;
		}

		function loadTokens(m, band) {
			if (tokenCache[m.id]) {
				renderTokens(band, tokenCache[m.id], m);
				return;
			}

			band.innerHTML = '';
			band.appendChild(el('p', 'vc-tbl-empty', L('tf_loading', 'Loading tokens…')));
			vcRest('panel/tokens?server_id=' + encodeURIComponent(m.id))
				.then(function (res) {
					if (!res || res.state !== 'ok') {
						throw new Error('state');
					}
					var raw = res.data && res.data.data !== undefined ? res.data.data : res.data;

					if (Array.isArray(raw)) {
						tokenCache[m.id] = raw;
					} else if (raw && Array.isArray(raw.data)) {
						tokenCache[m.id] = raw.data;
					} else if (raw && Array.isArray(raw.tokens)) {
						tokenCache[m.id] = raw.tokens;
					} else {
						tokenCache[m.id] = [];
					}
					if (expanded === m.id) {
						renderTokens(band, tokenCache[m.id], m);
					}
				})
				.catch(function () {
					if (expanded === m.id) {
						band.innerHTML = '';
						band.appendChild(el('p', 'vc-tbl-empty vc-bad', L('tf_load_failed', 'Failed to load tokens. Collapse and expand again to retry.')));
					}
				});
		}

		function renderTokens(band, tokens, m) {
			band.innerHTML = '';

			if (!tokens.length) {
				band.appendChild(el('p', 'vc-tbl-empty', L('tf_empty_tokens', 'No tokens found for this connector.')));
				return;
			}

			band.appendChild(tableCard({
				empty: '',
				rows: tokens,
				cols: [
					{ h: L('tf_col_name', 'Token Name'), cell: function (t) { return '<span class="vc-bold">' + String(t.name || '—').replace(/</g, '&lt;') + '</span>'; } },
					{ h: L('tf_col_hint', 'Hint'), cell: function (t) { return '<span class="vc-muted">' + String(t.token_hint || '—').replace(/</g, '&lt;') + '</span>'; } },
					{ h: L('col_requests', 'Requests'), align: 'right', cell: function (t) { return (t.request_count || 0).toLocaleString(); } },
					{ h: L('tf_col_last_used', 'Last Used'), align: 'right', cell: function (t) { return C ? C.timeAgo(t.last_used_at, L('never', 'Never')) : String(t.last_used_at || L('never', 'Never')); } },
					{
						h: L('tf_col_status', 'Status'),
						align: 'right',
						cell: function (t) {
							var active = t.is_enabled === true;

							return '<span class="vc-chip ' + (active ? 'vc-pill-ok' : '') + '">' + (active ? L('tf_status_active', 'Active') : L('tf_status_disabled', 'Disabled')) + '</span>';
						}
					}
				],
				rowClick: function (t) {
					return deepLink('token', t.id, null, ctx.period);
				}
			}));
		}

		input.addEventListener('input', function () {
			buildAll();
		});
		wrap.appendChild(acc);
		buildAll();
	}

	/* ── Agent Passport (AgentsView) ─────────────────────────────── */

	function agentKindWord(kind) {
		return L(kind === 'app_user' ? 'ag_kind_app_user' : 'ag_kind_connection', kind === 'app_user' ? 'Connect SDK user' : 'Org automation');
	}

	function agentLifecycleWord(lc) {
		var words = {
			active: ['ag_lc_active', 'Active'],
			dormant: ['ag_lc_dormant', 'Dormant'],
			zombie: ['ag_lc_zombie', 'Zombie'],
			disabled: ['ag_lc_disabled', 'Disabled'],
			'never-used': ['ag_lc_never_used', 'Never used']
		};
		var w = words[lc] || [null, lc || ''];

		return w[0] ? L(w[0], w[1]) : w[1];
	}

	function agentLifecycleHint(lc) {
		var hints = {
			active: ['ag_lc_hint_active', 'Placed a call within the last 7 days.'],
			dormant: ['ag_lc_hint_dormant', 'Last called between 8 and 30 days ago.'],
			zombie: ['ag_lc_hint_zombie', 'Enabled for over 30 days without a single call.'],
			disabled: ['ag_lc_hint_disabled', 'The credential is disabled and cannot place calls.'],
			'never-used': ['ag_lc_hint_never_used', 'Issued, with no call on record.']
		};
		var h = hints[lc] || [null, ''];

		return h[0] ? L(h[0], h[1]) : h[1];
	}

	function agentIdentitySentence(a) {
		if (a.application && a.app_user) {
			return TT('ag_identity_sdk', 'App user of {application} · on behalf of {appUser}', { application: a.application.name, appUser: a.app_user.external_id });
		}

		return TT('ag_identity_connection', 'Automation over {connection}', { connection: a.connection.name });
	}

	function agentPrivilegeSentence(a) {
		var p = a.permissions || {};
		var state = (p.least_privilege && p.least_privilege.state) || 'exact';
		var vars = {
			used: p.used_tools || 0,
			granted: p.granted_tools || 0,
			unused: p.unused_tools || 0,
			calls: (a.usage && a.usage.calls) || 0
		};
		var pairs = {
			exact: ['ag_priv_exact', 'Uses its full grant: {used} of {granted} tools, across {calls} calls in the window.'],
			partial: ['ag_priv_partial', 'Holds {granted} tools but used only {used} in the window, leaving {unused} granted tools unused.'],
			idle: ['ag_priv_idle', 'Holds {granted} granted tools and used none of them in the window.'],
			'no-tools': ['ag_priv_no_tools', 'The connection grants no enabled tools, so this credential cannot place any call.']
		};
		var pair = pairs[state] || pairs.exact;

		return TT(pair[0], pair[1], vars);
	}

	function agentKpiCell(label, value, hint) {
		var cell = el('div', 'vc-kpi-cell');

		cell.appendChild(el('div', 'vc-kpi-cell-label', label));
		cell.appendChild(el('div', 'vc-kpi-cell-value', value));

		if (hint) {
			cell.appendChild(el('div', 'vc-kpi-cell-hint', hint));
		}
		return cell;
	}

	function renderAgents(d, ctx, wrap) {
		var kpis = d.kpis || {};
		var median = kpis.least_privilege_median;

		wrap.appendChild(el('h3', 'vc-band-title', L('ag_registry_title', 'Credential calling overview')));
		wrap.appendChild(el('p', 'vc-band-hint', L('ag_registry_hint', 'Every credential that places calls on behalf of this organization, comparing the tools it may invoke with those it actually used over the selected window.')));

		var cells = el('div', 'vc-kpi-cells');

		cells.appendChild(agentKpiCell(L('ag_kpi_agents', 'Agents'), fmt(kpis.agents || 0), L('ag_agents_hint', 'Credentials with a full identity chain')));
		cells.appendChild(agentKpiCell(L('ag_kpi_active', 'Active'), fmt(kpis.active || 0), L('ag_active_hint', 'With calls in the last 7 days')));
		cells.appendChild(agentKpiCell(L('ag_kpi_zombie', 'Zombie'), fmt(kpis.zombie || 0), L('ag_zombie_hint', 'Enabled without a call for over 30 days')));
		cells.appendChild(agentKpiCell(L('ag_kpi_over', 'Over-provisioned'), fmt(kpis.over_provisioned || 0), L('ag_over_hint', 'Holding granted tools that remain unused')));
		cells.appendChild(agentKpiCell(L('ag_kpi_destructive', 'Destructive reach'), fmt(kpis.destructive_agents || 0), L('ag_destructive_hint', 'Agents with access to destructive tools')));
		cells.appendChild(agentKpiCell(
			L('ag_kpi_median', 'Least-privilege median'),
			median === null || median === undefined ? L('not_available', 'Not available') : median + '%',
			median === null || median === undefined
				? L('ag_median_none', 'No agent holds enabled tools, so no least-privilege score exists yet.')
				: TT('ag_median_sentence', 'Median score: {percent}% of granted tools actually used.', { percent: median })
		));
		wrap.appendChild(cells);

		/* Printable inventory band (export stays in the cloud). */
		var bandRow = el('div', 'vc-band-row');
		var bandText = el('div');

		bandText.appendChild(el('h3', 'vc-band-title', L('ag_export_title', 'Printable inventory')));
		bandText.appendChild(el('p', 'vc-band-hint', L('ag_export_hint', 'One document with the full registry: identity, permissions and usage per credential, ready for your security review.')));
		bandRow.appendChild(bandText);

		var exportBtn = el('a', 'button', L('ag_export_button', 'Export inventory'));

		exportBtn.href = ctx.knownFree ? ((VCConfig.gov && VCConfig.gov.upgrade) || '#') : sectionLink('agents');
		exportBtn.target = '_blank';
		exportBtn.rel = 'noopener';

		if (ctx.knownFree) {
			exportBtn.title = L('ag_export_paid_only', 'Paid plans');
		}
		bandRow.appendChild(exportBtn);
		wrap.appendChild(bandRow);

		if (d.window_hours) {
			wrap.appendChild(el('p', 'vc-band-hint', TT('ag_window', 'Window: the last {hours} hours covered by your plan.', { hours: d.window_hours })));
		}

		/* Registry accordion. */
		var agents = d.agents || [];

		if (!agents.length) {
			var empty = el('div', 'vc-empty');

			empty.appendChild(el('span', null, L('ag_empty', 'No credentials are active yet. Once a connection or an SDK token places its first call, its passport appears here.')));
			wrap.appendChild(empty);
		} else {
			var acc = el('div', 'vc-acc');
			expandedAgent = null;

			agents.forEach(function (a) {
				acc.appendChild(agentRow(a, ctx));
			});

			var pag = paginationBar(d.pagination, function (p) {
				ctx.goPage(p);
			});

			if (pag) {
				acc.appendChild(pag);
			}
			wrap.appendChild(acc);
		}
	}

	var expandedAgent = null;

	function agentRow(a, ctx) {
		var r = el('div', 'vc-acc-row');
		var head = el('button', 'vc-acc-head');

		head.type = 'button';
		head.innerHTML = '<svg class="vc-acc-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>';

		var main = el('div', 'vc-acc-main');
		var titleRow = el('div', 'vc-acc-title-row');

		titleRow.appendChild(el('span', 'vc-acc-name', a.name));
		titleRow.appendChild(el('span', 'vc-chip', agentKindWord(a.kind)));

		if (a.is_enabled === false) {
			titleRow.appendChild(el('span', 'vc-chip', L('ag_disabled_chip', 'Disabled')));
		}
		main.appendChild(titleRow);
		main.appendChild(el('p', 'vc-acc-sub', agentIdentitySentence(a)));
		head.appendChild(main);

		head.appendChild(el('span', 'vc-acc-calls', fmt(a.usage ? a.usage.calls : 0) + ' ' + L('ag_calls', 'calls')));

		var chip = el('span', 'vc-lifecycle vc-lifecycle--' + String(a.lifecycle || 'never-used').replace(/-/g, '_'));

		chip.textContent = agentLifecycleWord(a.lifecycle);
		head.appendChild(chip);
		r.appendChild(head);

		/* Passport band: identity / permissions / usage. */
		var band = el('div', 'vc-acc-band');
		band.style.display = 'none';
		var grid = el('div', 'vc-acc-band-grid');
		var p = a.permissions || {};
		var destructive = p.destructive_tools > 0 ? TT('ag_destructive_sentence', '{n} of its granted tools can destroy data.', { n: p.destructive_tools }) : null;

		var identity = el('div');

		identity.appendChild(el('div', 'vc-band-label', L('ag_band_identity', 'Identity chain')));
		identity.appendChild(el('div', 'vc-band-value', (a.connection && a.connection.name) || L('not_available', 'Not available')));
		identity.appendChild(el('div', 'vc-band-muted', L('ag_via_connection', 'Over this connection')));

		if (a.application) {
			identity.appendChild(el('div', 'vc-band-value', a.application.name));
			identity.appendChild(el('div', 'vc-band-muted', String(a.application.public_id || '')));
		}
		if (a.app_user) {
			identity.appendChild(el('div', 'vc-band-value vc-muted', String(a.app_user.external_id || '')));
			identity.appendChild(el('div', 'vc-band-muted', L('ag_end_user', 'End user the SDK token represents')));
		}
		grid.appendChild(identity);

		var perms = el('div');

		perms.appendChild(el('div', 'vc-band-label', L('ag_band_permissions', 'Permissions')));

		var counts = el('div');
		counts.innerHTML = '<span class="vc-band-num">' + (p.granted_tools || 0) + '</span> ' + L('ag_granted', 'granted') + ' · <span class="vc-band-num">' + (p.used_tools || 0) + '</span> ' + L('ag_used', 'used') + ' · <span class="vc-band-num">' + (p.unused_tools || 0) + '</span> ' + L('ag_unused', 'unused');
		perms.appendChild(counts);
		perms.appendChild(el('p', 'vc-band-note', agentPrivilegeSentence(a)));

		if (destructive) {
			perms.appendChild(el('p', 'vc-band-note vc-band-warn', destructive));
		}
		grid.appendChild(perms);

		var usage = el('div');
		var u = a.usage || {};

		usage.appendChild(el('div', 'vc-band-label', L('ag_band_usage', 'Usage in window')));
		usage.appendChild(el('p', 'vc-band-note', fmt(u.calls || 0) + ' ' + L('ag_calls', 'calls') + ' · ' + fmt(u.errors || 0) + ' ' + L('ag_errors', 'errors')));
		usage.appendChild(el('p', 'vc-band-note', TT('ag_last_used', 'Last call: {when}', { when: C ? C.timeAgo(u.last_used_at, L('never', 'Never')) : (u.last_used_at || L('never', 'Never')) })));
		usage.appendChild(el('p', 'vc-band-note', agentLifecycleHint(a.lifecycle)));
		grid.appendChild(usage);
		band.appendChild(grid);
		r.appendChild(band);

		head.addEventListener('click', function () {
			var open = band.style.display !== 'none';
			band.style.display = open ? 'none' : '';
			r.classList.toggle('is-open', !open);
		});
		return r;
	}

	/* ── AI Spend (CostView) ──────────────────────────────────────── */

	function renderCost(d, ctx, wrap) {
		wrap.appendChild(briefingCard('cost', ctx));
		var kpi = d.kpi || {};

		wrap.appendChild(kpiRow([
			kpiCard({
				label: L('cost_kpi_estimated', 'Estimated Cost'),
				value: C ? C.usd(kpi.total_cost || 0) : '$' + (kpi.total_cost || 0),
				valueClass: 'vc-amber',
				sub: TT('cost_tokens_consumed', '{count} tokens consumed', { count: fmt(kpi.total_tokens || 0) })
			}),
			kpiCard({
				label: L('cost_kpi_savings', 'FinOps Savings'),
				value: C ? C.usd(kpi.total_savings || 0) : '$' + (kpi.total_savings || 0),
				valueClass: 'vc-emerald',
				sub: TT('cost_tokens_saved', '{count} tokens saved', { count: fmt(kpi.tokens_saved || 0) })
			}),
			kpiCard({
				label: L('cost_kpi_per_request', 'Cost per Request'),
				value: C ? C.usd(kpi.cost_per_request || 0) : '$' + (kpi.cost_per_request || 0),
				sub: TT('cost_kpi_requests', '{count} requests', { count: fmt(kpi.total_requests || 0) })
			}),
			kpiCard({
				label: L('cost_kpi_roi', 'FinOps ROI'),
				value: (kpi.roi_percentage || 0) + '%',
				valueClass: (kpi.roi_percentage || 0) > 0 ? 'vc-emerald' : '',
				progress: [{ cls: 'emerald', pct: Math.min(kpi.roi_percentage || 0, 100) }],
				sub: TT('cost_roi_hint', 'DLP: {dlp} · FinOps: {finops}', { dlp: fmt(kpi.dlp_events || 0), finops: fmt(kpi.finops_events || 0) })
			})
		]));

		var hourly = ctx.period === 'today' || ctx.period === '3d';

		if (C && C.available) {
			wrap.appendChild(chartGrid([
				{
					option: C.costTrendOption(d.trend || []),
					title: L('cost_trend_title', 'Cost & Savings Trend'),
					subtitle: L(hourly ? 'cost_trend_sub_hourly' : 'cost_trend_sub_daily', hourly ? 'Hourly estimated spend and savings over time' : 'Daily estimated spend and savings over time'),
					height: 220
				},
				{ option: C.costPieOption(d.by_server || []), title: L('cost_pie_title', 'Cost Distribution'), subtitle: L('cost_pie_sub', 'Spend proportion by connector'), height: 220 }
			]));
		}

		/* Cost by connector — two-segment bars. */
		var byConnector = el('div', 'vc-table-card');

		byConnector.appendChild(el('h3', 'vc-tbl-title', L('cost_by_connector_title', 'Cost by Connector')));
		byConnector.appendChild(el('p', 'vc-tbl-sub', L('cost_by_connector_sub', 'Token consumption in USD per connector')));

		var rowsWrap = el('div', 'vc-tbl-wrap');

		(d.by_server || []).forEach(function (s) {
			var rowEl = el('div');

			rowEl.style.padding = '6px 14px';
			var line = el('div');

			line.style.display = 'flex';
			line.style.justifyContent = 'space-between';
			line.style.marginBottom = '4px';
			line.appendChild(el('span', 'vc-bold', s.server_name));

			var right = el('span');

			right.className = 'vc-muted';
			right.textContent = TT('cost_reqs', '{count} reqs', { count: fmt(s.requests || 0) }) + '  ·  ' + (C ? C.usd(s.cost || 0) : '$' + (s.cost || 0));
			right.style.fontFamily = 'Consolas, monospace';
			line.appendChild(right);
			rowEl.appendChild(line);

			var total = (s.cost || 0) + (s.savings || 0);
			var bar = el('div', 'vc-segbar');
			var costSeg = el('div', 'vc-segbar-seg vc-seg--amber');

			costSeg.style.width = total > 0 ? ((s.cost || 0) / total) * 100 + '%' : '0%';
			bar.appendChild(costSeg);

			if ((s.savings || 0) > 0) {
				var saveSeg = el('div', 'vc-segbar-seg vc-seg--emerald');

				saveSeg.style.width = total > 0 ? ((s.savings || 0) / total) * 100 + '%' : '0%';
				bar.appendChild(saveSeg);
			}
			rowEl.appendChild(bar);
			rowsWrap.appendChild(rowEl);
		});
		byConnector.appendChild(rowsWrap);

		var legend = el('div');

		legend.className = 'vc-seg-legend';
		legend.style.padding = '10px 14px 14px';
		legend.innerHTML = '<span class="vc-seg-legend-cost">' + L('legend_cost', 'Cost') + '</span><span class="vc-seg-legend-savings">' + L('legend_savings', 'Savings') + '</span>';
		byConnector.appendChild(legend);
		wrap.appendChild(byConnector);

		wrap.appendChild(tableCard({
			title: L('cost_table_title', 'Connector Cost Detail'),
			empty: L('cost_empty', 'No cost data recorded in this period.'),
			rows: d.by_server || [],
			cols: [
				{ h: L('tb_connector', 'Connector'), cell: function (s) { return '<span class="vc-bold">' + String(s.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_tokens', 'Tokens'), align: 'right', cell: function (s) { return fmt(s.tokens || 0); } },
				{ h: L('tb_cost', 'Cost'), align: 'right', cell: function (s) { return '<span class="vc-amber vc-bold">' + (C ? C.usd(s.cost || 0) : '$' + (s.cost || 0)) + '</span>'; } },
				{ h: L('tb_saved', 'Saved'), align: 'right', cell: function (s) { return '<span class="vc-emerald">' + fmt(s.tokens_saved || 0) + '</span>'; } },
				{ h: L('tb_savings', 'Savings'), align: 'right', cell: function (s) { return '<span class="vc-emerald vc-bold">' + (C ? C.usd(s.savings || 0) : '$' + (s.savings || 0)) + '</span>'; } },
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (s) { return fmt(s.requests || 0); } },
				{ h: L('col_dlp', 'DLP'), align: 'right', cell: function (s) { return '<span class="' + ((s.dlp_events || 0) > 0 ? 'vc-blue' : 'vc-muted') + '">' + fmt(s.dlp_events || 0) + '</span>'; } },
				{ h: L('col_finops', 'FinOps'), align: 'right', cell: function (s) { return '<span class="' + ((s.finops_events || 0) > 0 ? 'vc-purple' : 'vc-muted') + '">' + fmt(s.finops_events || 0) + '</span>'; } }
			],
			rowClick: function (s) {
				return deepLink('cost', s.mcp_server_id, null, ctx.period);
			}
		}));
	}

	/* ── Capability Reliability (ToolPerformanceView) ─────────────── */

	function toolRate(count, calls) {
		return calls > 0 ? +((count / calls) * 100).toFixed(2) : 0;
	}

	function originCell(field, bold) {
		return function (row) {
			var r = toolRate((row && row[field]) || 0, (row && row.calls) || 0);
			var cls = 'vc-muted';

			if (field === 'client_errors') {
				cls = r === 0 ? 'vc-muted' : (r < 5 ? 'vc-amber' : 'vc-amber');
			} else if (field === 'upstream_errors') {
				cls = r === 0 ? 'vc-muted' : 'vc-bad';
			} else {
				cls = r === 0 ? 'vc-muted' : 'vc-purple';
			}
			return '<span class="' + cls + (bold ? ' vc-bold' : '') + '">' + (row[field] || 0) + ' · ' + r + '%</span>';
		};
	}

	function renderToolPerf(d, ctx, wrap) {
		wrap.appendChild(briefingCard('tool-performance', ctx));
		var kpi = d.kpi || {};

		wrap.appendChild(kpiRow([
			kpiCard({
				label: L('tp_kpi_total_requests', 'Total Requests'),
				value: fmt(kpi.total_requests || 0),
				sub: TT('tp_success_rate', '{pct}% success rate', { pct: kpi.total_requests > 0 ? ((1 - (kpi.total_errors || 0) / kpi.total_requests) * 100).toFixed(1) : 0 })
			}),
			kpiCard({
				label: L('tp_kpi_unique', 'Unique Capabilities'),
				value: String(kpi.unique_tools || 0),
				valueClass: 'vc-purple',
				sub: L('tp_unique_hint', 'Active in this period')
			}),
			kpiCard({
				label: L('tp_kpi_avg_latency', 'Avg Latency'),
				value: C ? C.formatLatency(kpi.avg_latency_ms) : '—',
				valueClass: C ? C.latencyTextClass(kpi.avg_latency_ms) : '',
				valueTitle: C ? C.latencyTooltip(kpi.avg_latency_ms, kpi.avg_api_latency_ms) : '',
				progress: [
					{ cls: 'emerald', pct: Math.min(((kpi.avg_api_latency_ms || 0) / Math.max(kpi.avg_latency_ms || 1, 1)) * 100, 100) },
					{ cls: 'amber', pct: Math.min(((kpi.avg_latency_ms || 0) - (kpi.avg_api_latency_ms || 0) / Math.max(kpi.avg_latency_ms || 1, 1)) * 100, 100) }
				],
				sub: TT('api_overhead_sub', 'API: {apiValue} · Overhead: {overhead}', {
					apiValue: C ? C.formatLatency(kpi.avg_api_latency_ms) : '—',
					overhead: C ? C.formatLatency(Math.max(0, (kpi.avg_latency_ms || 0) - (kpi.avg_api_latency_ms || 0))) : '—'
				})
			}),
			kpiCard({
				label: L('tp_kpi_total_errors', 'Total Errors'),
				value: fmt(kpi.total_errors || 0),
				valueClass: (kpi.total_errors || 0) > 0 ? 'vc-bad' : 'vc-emerald',
				progress: [{ cls: 'red', pct: kpi.total_requests > 0 ? Math.min(((kpi.total_errors || 0) / kpi.total_requests) * 100 * 5, 100) : 0 }],
				sub: TT('error_origin_breakdown', 'Agent: {agentCount} · Upstream: {upstreamCount} · Vinkius: {vinkiusCount}', {
					agentCount: kpi.client_errors || 0,
					upstreamCount: kpi.upstream_errors || 0,
					vinkiusCount: kpi.vinkius_errors || 0
				})
			})
		]));

		if (C && C.available) {
			wrap.appendChild(chartGrid([
				{ option: C.slowestToolsOption(d.slowest || []), title: L('tp_chart_slowest_title', 'Slowest Capabilities'), subtitle: L('tp_slowest_sub', 'Avg latency (amber) · max spike (red)'), height: 280 },
				{ option: C.failingToolsOption(d.most_failing || []), title: L('tp_chart_failing_title', 'Most Failing Capabilities'), subtitle: L('err_server_sub', 'Stacked by Agent / Upstream / Vinkius'), height: 280 },
				{ option: C.toolScatterOption(d.slowest || [], d.most_failing || []), title: L('tp_chart_matrix_title', 'Health Matrix'), subtitle: L('tp_matrix_sub', 'Latency × Failure rate · bubble = request volume'), height: 280 }
			], 'vc-chart-grid--3'));
		}

		function openRow(row) {
			if (row.last_request_id) {
				return deepRequest(row.last_request_id, ctx.period);
			}

			return deepLink('tool', row.mcp_server_id, row.mcp_tool_id, ctx.period);
		}

		wrap.appendChild(tableCard({
			title: L('tp_table_slowest_title', 'Slowest Capabilities Detail'),
			empty: L('tp_empty_slowest', 'No capability performance data recorded in this period.'),
			rows: d.slowest || [],
			cols: [
				{ h: L('tb_connector', 'Connector'), cell: function (r) { return '<span class="vc-bold">' + String(r.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('tb_capability', 'Capability'), cell: function (r) { return '<span class="vc-muted">' + String(r.tool_name || '').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (r) { return fmt(r.calls || 0); } },
				{
					h: L('col_api', 'API'),
					align: 'right',
					cell: function (r) {
						var s = el('span', null, C ? C.formatLatency(r.avg_api_latency_ms) : '—');

						s.className = C ? C.latencyTextClass(r.avg_api_latency_ms) : '';
						s.title = C ? C.latencyTooltip(r.avg_latency_ms, r.avg_api_latency_ms) : '';
						return s;
					}
				},
				{
					h: L('col_overhead', 'Overhead'),
					align: 'right',
					cell: function (r) {
						var o = Math.max(0, (r.avg_latency_ms || 0) - (r.avg_api_latency_ms || 0));
						var s = el('span', null, C ? C.formatLatency(o) : '—');

						s.className = C ? C.latencyTextClass(o) : '';
						return s;
					}
				},
				{
					h: L('col_total', 'Total'),
					align: 'right',
					cell: function (r) {
						var s = el('span', 'vc-bold', C ? C.formatLatency(r.avg_latency_ms) : '—');

						s.className = 'vc-bold ' + (C ? C.latencyTextClass(r.avg_latency_ms) : '');
						return s;
					}
				},
				{ h: L('col_max', 'Max'), align: 'right', cell: function (r) { return '<span class="vc-muted">' + (C ? C.formatLatency(r.max_latency_ms) : '—') + '</span>'; } },
					{ h: L('col_agent', 'Agent'), align: 'right', cell: originCell('client_errors') },
					{ h: L('col_upstream', 'Upstream'), align: 'right', cell: originCell('upstream_errors') },
					{ h: L('col_vinkius', 'Vinkius'), align: 'right', cell: originCell('vinkius_errors') },
					{ h: L('col_rate', 'Rate'), align: 'right', cell: function (r) { return '<span class="' + (C ? C.errorRateClass(r.error_rate || 0) : '') + '">' + (r.error_rate || 0) + '%</span>'; } },
				{
					h: L('tb_last_seen', 'Last seen'),
					align: 'right',
					cell: function (r) {
						var s = el('span', 'vc-muted', r.last_request_at ? C.timeAgo(r.last_request_at) : L('not_available', 'Not available'));

						if (r.last_request_at) {
							s.title = r.last_request_at;
						}
						return s;
					}
				}
			],
			rowClick: openRow
		}));

		wrap.appendChild(tableCard({
			title: L('tp_table_failing_title', 'Most Failing Capabilities Detail'),
			empty: L('tp_empty_failing', 'No capability error data recorded in this period.'),
			rows: d.most_failing || [],
			cols: [
				{ h: L('tb_connector', 'Connector'), cell: function (r) { return '<span class="vc-bold">' + String(r.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('tb_capability', 'Capability'), cell: function (r) { return '<span class="vc-muted">' + String(r.tool_name || '').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (r) { return fmt(r.calls || 0); } },
				{ h: L('col_errors', 'Errors'), align: 'right', cell: function (r) { return '<span class="vc-bad vc-bold">' + fmt(r.errors || 0) + '</span>'; } },
				{ h: L('col_agent', 'Agent'), align: 'right', cell: originCell(null, 'client_errors', true) },
				{ h: L('col_upstream', 'Upstream'), align: 'right', cell: originCell(null, 'upstream_errors', true) },
				{ h: L('col_vinkius', 'Vinkius'), align: 'right', cell: originCell(null, 'vinkius_errors', true) },
				{ h: L('col_rate', 'Rate'), align: 'right', cell: function (r) { return '<span class="' + (C ? C.errorRateClass(r.error_rate || 0) : '') + ' vc-bold">' + (r.error_rate || 0) + '%</span>'; } },
				{
					h: L('col_avg_latency', 'Avg Latency'),
					align: 'right',
					cell: function (r) {
						var s = el('span', 'vc-muted', C ? C.formatLatency(r.avg_latency_ms) : '—');

						s.title = C ? C.latencyTooltip(r.avg_latency_ms, r.avg_api_latency_ms) : '';
						return s;
					}
				},
				{
					h: L('tb_last_seen', 'Last seen'),
					align: 'right',
					cell: function (r) {
						var s = el('span', 'vc-muted', r.last_request_at ? C.timeAgo(r.last_request_at) : L('not_available', 'Not available'));

						if (r.last_request_at) {
							s.title = r.last_request_at;
						}
						return s;
					}
				}
			],
			rowClick: openRow
		}));
	}

	/* ── Security Posture (SecurityView) ──────────────────────────── */

	function boolIcon(on) {
		var s = el('span', on ? 'vc-ok' : 'vc-muted');

		s.textContent = on ? '✓' : '✗';
		return s;
	}

	function renderSecurity(d, ctx, wrap) {
		wrap.appendChild(briefingCard('security', ctx));
		var kpi = d.kpi || {};
		var events = kpi.total_events || 0;
		var dlpPct = events > 0 ? ((kpi.dlp_redactions || 0) / events) * 100 : 0;
		var finPct = events > 0 ? ((kpi.finops_truncations || 0) / events) * 100 : 0;

		/* KPI cluster (2×2) + compliance ring. */
		var duo = el('div', 'vc-duo');
		var cluster = el('div', 'vc-kpi-strip');

		cluster.style.gridTemplateColumns = 'repeat(2, 1fr)';

		cluster.appendChild(kpiCard({
			label: L('sec_kpi_firewall', 'Firewall Events'),
			value: fmt(events),
			hero: true,
			sub: TT('sec_requests_inspected', '{count} requests inspected', { count: fmt(kpi.total_requests || 0) })
		}));
		cluster.appendChild(kpiCard({
			label: L('sec_kpi_dlp', 'DLP Redactions'),
			value: '<span class="vc-blue">' + fmt(kpi.dlp_redactions || 0) + '</span>',
			progress: [{ cls: 'blue', pct: dlpPct }],
			sub: TT('sec_of_events', '{pct}% of firewall events', { pct: Math.round(dlpPct) })
		}));
		cluster.appendChild(kpiCard({
			label: L('sec_kpi_finops', 'FinOps Truncations'),
			value: '<span class="vc-purple">' + fmt(kpi.finops_truncations || 0) + '</span>',
			progress: [{ cls: 'purple', pct: finPct }],
			sub: TT('sec_of_events', '{pct}% of firewall events', { pct: Math.round(finPct) })
		}));
		cluster.appendChild(kpiCard({
			label: L('sec_kpi_data', 'Data Protected'),
			value: '<span class="vc-emerald">' + (C ? C.formatBytes(kpi.bytes_saved || 0) : String(kpi.bytes_saved || 0)) + '</span>',
			sub: TT('sec_total_processed', '{count} total processed', { count: C ? C.formatBytes(kpi.bytes_processed || 0) : String(kpi.bytes_processed || 0) })
		}));
		duo.appendChild(cluster);

		if (C && C.available && d.coverage) {
			var ringCard = el('div', 'vc-chart-card');
			var ringWrap = el('div', 'vc-ring-wrap');
			var ring = el('div', 'vc-ring');

			ringWrap.appendChild(ring);
			C.render(ring, C.complianceRingOption(d.coverage));

			var legend = el('div', 'vc-ring-legend');

			legend.appendChild(ringLegendRow(L('legend_dlp_redactions', 'DLP Redactions'), d.coverage));
			legend.appendChild(ringLegendRow(L('legend_finops_truncations', 'FinOps Truncations'), d.coverage));
			legend.appendChild(ringLegendRow(L('sec_coverage_approval', 'Approval'), d.coverage));
			ringWrap.appendChild(legend);

			var ringHead = el('div', 'vc-chart-head');

			ringHead.appendChild(el('h3', 'vc-chart-title', L('sec_coverage_title', 'Compliance Coverage')));
			ringCard.appendChild(ringHead);
			ringCard.appendChild(ringWrap);
			duo.appendChild(ringCard);
		}
		wrap.appendChild(duo);

		/* Enforcement strip. */
		var strip = kpiRow([
			kpiCard({
				label: L('sec_kpi_enforcement', 'Enforcement Rate'),
				value: (kpi.enforcement_rate || 0) + '%',
				valueClass: (kpi.enforcement_rate || 0) > 0 ? 'vc-amber' : '',
				sub: L('sec_enforcement_hint', 'Events per request ratio')
			}),
			kpiCard({
				label: L('sec_kpi_inspected_card', 'Requests Inspected'),
				value: fmt(kpi.total_requests || 0),
				sub: L('sec_all_through', 'All traffic through firewall')
			}),
			kpiCard({
				label: L('sec_kpi_payload', 'Payload Volume'),
				value: C ? C.formatBytes(kpi.bytes_processed || 0) : String(kpi.bytes_processed || 0),
				sub: L('sec_payload_hint', 'Total data volume inspected')
			})
		]);
		wrap.appendChild(strip);

		if (C && C.available) {
			wrap.appendChild(chartGrid([{
				option: C.firewallOption(d.timeline || []),
				title: L('sec_chart_title', 'Firewall Activity'),
				subtitle: L('sec_chart_sub', 'Daily DLP and FinOps enforcement actions'),
				height: 240
			}]));
		}

		wrap.appendChild(tableCard({
			title: L('sec_table_title', 'Enforcement by Connector'),
			subtitle: L('sec_table_sub', 'Firewall activity and active policies per connector'),
			empty: L('sec_empty', 'No security data recorded in this period.'),
			rows: d.servers || [],
			cols: [
				{ h: L('tb_mcp_server', 'MCP Server'), cell: function (s) { return '<span class="vc-bold">' + String(s.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_dlp', 'DLP'), align: 'center', cell: function (s) { return boolIcon(s.dlp_enabled === true); } },
				{ h: L('col_finops', 'FinOps'), align: 'center', cell: function (s) { return boolIcon(s.finops_enabled === true); } },
				{ h: L('tb_approval', 'Approval'), align: 'center', cell: function (s) { return boolIcon(s.deploy_approval === true); } },
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (s) { return fmt(s.requests || 0); } },
				{ h: L('tb_dlp_events', 'DLP Events'), align: 'right', cell: function (s) { return '<span class="' + ((s.dlp_redactions || 0) > 0 ? 'vc-amber vc-bold' : 'vc-muted') + '">' + fmt(s.dlp_redactions || 0) + '</span>'; } },
				{ h: L('tb_finops_events', 'FinOps Events'), align: 'right', cell: function (s) { return '<span class="' + ((s.finops_truncations || 0) > 0 ? 'vc-amber vc-bold' : 'vc-muted') + '">' + fmt(s.finops_truncations || 0) + '</span>'; } },
				{ h: L('tb_bytes_saved', 'Bytes Saved'), align: 'right', cell: function (s) { return '<span class="vc-emerald">' + (C ? C.formatBytes(s.bytes_saved || 0) : String(s.bytes_saved || 0)) + '</span>'; } }
			],
			rowClick: function (s) {
				return deepLink('security', s.server_id, null, ctx.period);
			}
		}));

		/* Enforcement settings (read-only mirror of the org's DLP/FinOps policy).
		 * Flat group cards — no nested card-in-card border, loading and
		 * failure states per group so the card never renders half-empty. */
		wrap.appendChild(el('h3', 'vc-band-title', L('sec_settings_title', 'Enforcement policy in force')));
		wrap.appendChild(el('p', 'vc-band-hint', L('sec_settings_sub', 'Read-only mirror of the org-wide DLP/FinOps configuration. Edits live in the Vinkius cloud.')));

		var setWrap = el('div', 'vc-settings');

		wrap.appendChild(setWrap);

		function settingsRow(label, value) {
			var rowEl = el('div', 'vc-set-row');

			rowEl.appendChild(el('span', 'vc-set-label', label));
			rowEl.appendChild(el('span', 'vc-bold', value));
			return rowEl;
		}

		function settingsFail(rowsWrap) {
			rowsWrap.innerHTML = '';
			rowsWrap.appendChild(el('p', 'vc-set-fail', L('sec_load_err', 'The policy snapshot is unavailable right now.')));
		}

		function loadSettings(scope, title, fields) {
			var card = el('div', 'vc-table-card');
			var head = el('div', 'vc-tbl-head');

			head.appendChild(el('h3', 'vc-tbl-title', title));
			card.appendChild(head);

			var rowsWrap = el('div', 'vc-rows');
			var loadRow = el('div', 'vc-set-row vc-set-row--loading');

			loadRow.appendChild(el('span', 'vc-set-label', L('sec_loading', 'Reading policy…')));
			loadRow.appendChild(el('span', 'spinner is-active'));
			rowsWrap.appendChild(loadRow);
			card.appendChild(rowsWrap);
			setWrap.appendChild(card);

			vcRest('panel/settings?scope=' + encodeURIComponent(scope))
				.then(function (res) {
					if (!res || res.state !== 'ok' || !res.data || !res.data.data) {
						settingsFail(rowsWrap);
						return;
					}

					var cfg = res.data.data;
					var rows = [];

					fields.forEach(function (f) {
						if (cfg[f.key] === undefined) {
							return;
						}

						var v = cfg[f.key];
						var text;

						if (typeof v === 'boolean') {
							text = v ? L('yes', 'Yes') : L('no', 'No');
						} else if (Array.isArray(v)) {
							text = v.length ? String(v.length) : L('none_cap', 'None');
						} else {
							text = String(v);
						}

						rows.push(settingsRow(f.label, text));
					});

					if (!rows.length) {
						settingsFail(rowsWrap);
						return;
					}

					rowsWrap.innerHTML = '';
					rows.forEach(function (r0) {
						rowsWrap.appendChild(r0);
					});
				})
				.catch(function () {
					settingsFail(rowsWrap);
				});
		}

		loadSettings('dlp', L('sec_dlp_policy', 'Data-loss prevention'), [
			{ key: 'dlp_enabled', label: L('sec_dlp_enabled', 'DLP enabled') },
			{ key: 'dlp_patterns', label: L('sec_dlp_patterns', 'Active patterns') }
		]);
		loadSettings('finops', L('sec_finops_policy', 'Response hygiene (FinOps)'), [
			{ key: 'finops_enabled', label: L('sec_finops_enabled', 'FinOps enabled') },
			{ key: 'max_array_items', label: L('sec_finops_arrays', 'Array items cap') },
			{ key: 'max_payload_bytes', label: L('sec_finops_payload', 'Payload cap') },
			{ key: 'toon_compression', label: L('sec_finops_toon', 'TOON compression') },
			{ key: 'cost_per_million_tokens', label: L('sec_finops_cost', 'Cost per 1M tokens') }
		]);
	}

	function ringLegendRow(label, coverage) {
		var key = label === L('sec_coverage_approval', 'Approval') ? 'approval' : 'dlp';
		var active = key === 'dlp' ? (coverage.dlp_active || 0) : key === 'finops' ? (coverage.finops_active || 0) : (coverage.approval_active || 0);

		if (key === 'dlp' && label === L('legend_finops_truncations', 'FinOps Truncations')) {
			active = coverage.finops_active || 0;
		}
		var row = el('span', 'vc-ring-legend-row');

		row.appendChild(el('span', null, label));
		row.appendChild(el('span', 'vc-ring-legend-count', TT('sec_coverage_count', '{active}/{total}', { active: active, total: coverage.total_servers || 0 })));
		return row;
	}

	/* ── Request Failures (ErrorsView) ────────────────────────────── */

	function serverRate(count, requests) {
		return requests > 0 ? +((count / requests) * 100).toFixed(2) : 0;
	}

	function renderErrors(d, ctx, wrap) {
		wrap.appendChild(briefingCard('errors', ctx));
		var kpi = d.kpi || {};

		wrap.appendChild(kpiRow([
			kpiCard({
				label: L('err_kpi_total', 'Total Errors'),
				value: '<span class="' + ((kpi.total_errors || 0) > 0 ? 'vc-bad' : 'vc-emerald') + '">' + fmt(kpi.total_errors || 0) + '</span>',
				sub: TT('err_of_requests', 'of {count} requests', { count: fmt(kpi.total_requests || 0) })
			}),
			kpiCard({
				label: L('err_kpi_rate', 'Failure Rate'),
				value: '<span class="' + (C ? C.errorRateClass(kpi.error_rate || 0) : '') + '">' + (kpi.error_rate || 0) + '%</span>',
				progress: [{ cls: kpi.error_rate === 0 ? 'emerald' : (kpi.error_rate < 5 ? 'amber' : 'red'), pct: kpi.error_rate === 0 ? 100 : Math.min(kpi.error_rate * 2, 100) }],
				sub: TT('error_origin_breakdown', 'Agent: {agentCount} · Upstream: {upstreamCount} · Vinkius: {vinkiusCount}', {
					agentCount: kpi.client_errors || 0,
					upstreamCount: kpi.upstream_errors || 0,
					vinkiusCount: kpi.vinkius_errors || 0
				})
			}),
			kpiCard({
				label: L('err_kpi_worst_connector', 'Worst Connector'),
				sub: L('err_worst_connector_hint', 'Most erroring connector'),
				extra: function (head, card) {
					var v = el('span', 'vc-bold', kpi.worst_server || L('not_available', 'Not available'));

					if (kpi.worst_server) {
						v.title = kpi.worst_server;
					}
					card.insertBefore(v, card.querySelector('.vc-kpi-card-sub'));
				}
			}),
			kpiCard({
				label: L('err_kpi_worst_capability', 'Worst Capability'),
				sub: L('err_worst_capability_hint', 'Most erroring capability'),
				extra: function (head, card) {
					var v = el('span', 'vc-bold vc-muted', kpi.worst_tool || L('not_available', 'Not available'));

					if (kpi.worst_tool) {
						v.title = kpi.worst_tool;
					}
					card.insertBefore(v, card.querySelector('.vc-kpi-card-sub'));
				}
			})
		]));

		if (C && C.available) {
			wrap.appendChild(chartGrid([
				{ option: C.errorTimelineOption(d.timeline || []), title: L('err_timeline_title', 'Failure Timeline'), subtitle: L('err_timeline_sub', 'Errors (red) · Agent / Upstream / Vinkius rates'), height: 240 },
				{ option: C.transportOption(d.transport || []), title: L('err_transport_title', 'Transport Distribution'), subtitle: L('err_transport_sub', 'Connection protocol breakdown'), height: 240 },
				{ option: C.serverErrorsOption(d.by_server || []), title: L('err_server_title', 'Errors by Connector'), subtitle: L('err_server_sub', 'Stacked by Agent / Upstream / Vinkius'), height: 240 }
			], 'vc-chart-grid--3'));
		}

		function healthDot(row) {
			var rate = serverRate(row.vinkius_errors || 0, row.requests || 0);
			var dot = rate === 0 ? 'vc-dot-ok' : (rate < 1 ? 'vc-dot-warn' : 'vc-dot-violet');
			var word = rate === 0 ? L('health_healthy', 'Healthy') : (rate < 1 ? L('health_moderate', 'Moderate') : L('health_critical', 'Critical'));
			var s = el('span');

			s.appendChild(el('span', 'vc-dot ' + dot));
			s.appendChild(el('span', null, word));
			return s;
		}

		wrap.appendChild(tableCard({
			title: L('err_table_title', 'Error Distribution by Connector'),
			empty: L('errors_empty', 'No error data recorded in this period.'),
			rows: d.by_server || [],
			cols: [
				{ h: L('tb_connector', 'Connector'), cell: function (s) { return '<span class="vc-bold">' + String(s.server_name || '—').replace(/</g, '&lt;') + '</span>'; } },
				{ h: L('col_requests', 'Requests'), align: 'right', cell: function (s) { return fmt(s.requests || 0); } },
				{ h: L('col_errors', 'Errors'), align: 'right', cell: function (s) { return '<span class="' + ((s.errors || 0) > 0 ? 'vc-bad vc-bold' : 'vc-muted') + '">' + fmt(s.errors || 0) + '</span>'; } },
				{ h: L('col_rate', 'Rate'), align: 'right', cell: function (s) { return '<span class="' + (C ? C.errorRateClass(s.error_rate || 0) : '') + '">' + (s.error_rate || 0) + '%</span>'; } },
				{ h: L('col_health', 'Health'), align: 'right', cell: healthDot }
			],
			rowClick: function (s) {
				return deepLink('error', s.mcp_server_id, null, ctx.period);
			}
		}));
	}

	/* ── Compliance & Evidence (ComplianceView) ───────────────────── */

	function statusChip(status) {
		var styles = {
			satisfied: ['cp_status_satisfied', 'Satisfied', 'vc-emerald'],
			partial: ['cp_status_partial', 'Partial', 'vc-amber'],
			missing: ['cp_status_missing', 'Missing', 'vc-bad']
		};
		var s = styles[status] || styles.missing;
		var chip = el('span', 'vc-chip ' + s[2]);

		chip.textContent = L(s[0], s[1]);
		return chip;
	}

	function answerWord(answer) {
		var map = {
			satisfied: ['cp_answer_yes', 'Yes', 'vc-emerald'],
			partial: ['cp_answer_partly', 'Partly', 'vc-amber'],
			missing: ['cp_answer_missing', 'Not yet', 'vc-bad']
		};
		var m = map[answer] || map.missing;

		return [L(m[0], m[1]), m[2]];
	}

	function questionTitle(id) {
		var map = {
			data: ['cp_q_data', 'Is my data safe while the AI works?'],
			stop: ['cp_q_stop', 'Can I stop it if it misbehaves?'],
			prove: ['cp_q_prove', 'Can I prove what the AI did?']
		};
		var m = map[id] || [null, ''];

		return m[0] ? L(m[0], m[1]) : m[1];
	}

	function questionDetail(q) {
		var map = {
			data: ['cp_q_data_detail', 'Data masking on {dlp_on}/{connections} connections · spend limits on {finops_on}/{connections}.'],
			stop: ['cp_q_stop_detail', 'Two-person approval on {approval_on}/{connections} connections · {breaker_events} automatic stops in retention.'],
			prove: ['cp_q_prove_detail', '{sealed} sealed call receipts · {deploys} sealed deploys · chains {chains_valid}.']
		};
		var m = map[q.id];

		if (!m) {
			return '';
		}
		return TT(m[0], m[1], q.numbers || {});
	}

	function complianceStatusColor(status) {
		if (status === 'satisfied') {
			return '#10b981';
		}

		if (status === 'partial') {
			return '#f59e0b';
		}

		return '#ef4444';
	}

	function renderCompliance(d, ctx, wrap) {
		var health = d.health || {};
		var grade = health.grade || 'attention';
		var grades = {
			ready: ['cp_grade_ready', 'Ready, nothing blocking'],
			watch: ['cp_grade_watch', 'Almost there, a few settings to flip'],
			attention: ['cp_grade_attention', 'Needs attention, close the gaps below']
		};
		var gradePair = grades[grade] || grades.attention;

		/* Hero: readiness ring + the three questions. */
		var hero = el('div', 'vc-table-card');
		var heroGrid = el('div', 'vc-ring-hero');

		var ring = el('div', 'vc-ring');

		heroGrid.appendChild(ring);
		if (C && C.available) {
			C.render(ring, C.readinessRingOption(health.percent || 0, grade));
		}

		heroGrid.appendChild(el('div', 'vc-band-value', L('cp_health_label', 'Governance readiness')));
		heroGrid.appendChild(el('div', 'vc-kpi-card-value', (health.percent !== undefined ? health.percent : 0) + '%'));
		heroGrid.appendChild(el('div', 'vc-band-note', L(gradePair[0], gradePair[1])));
		hero.appendChild(heroGrid);

		var questions = el('div', 'vc-hero-questions vc-acc-band-grid');

		(health.questions || []).forEach(function (q) {
			var a = answerWord(q.answer);
			var col = el('div');

			col.appendChild(el('div', 'vc-band-label', questionTitle(q.id)));
			col.appendChild(el('div', 'vc-kpi-value-row ' + a[1], a[0]));
			var detail = questionDetail(q);

			if (detail) {
				col.appendChild(el('div', 'vc-band-note', detail));
			}
			questions.appendChild(col);
		});
		hero.appendChild(questions);
		wrap.appendChild(hero);

		/* Gaps. */
		wrap.appendChild(el('h3', 'vc-band-title', L('cp_gaps_title', 'What to do about it')));
		wrap.appendChild(el('p', 'vc-band-hint', L('cp_gaps_sub', 'Plain-language steps: flip a policy once and it covers every connection.')));

		var gaps = d.gaps || [];

		if (!gaps.length) {
			var ok = el('div', 'vc-empty');

			ok.textContent = L('cp_gaps_none', 'Nothing to fix. Every control is fully in force.');
			wrap.appendChild(ok);
		} else {
			var gapList = el('div', 'vc-acc');

			gaps.forEach(function (gap) {
				var row = el('div', 'vc-acc-row');
				var inner = el('div', 'vc-acc-band vc-acc-band--flush');
				var line = el('div', 'vc-band-row');
				var left = el('span');

				left.appendChild(el('span', 'vc-ref', String(gap.ref || '')));

				var title = el('span', 'vc-bold');

				title.textContent = String(gap.title || '').replace(/</g, '&lt;');
				left.appendChild(title);
				line.appendChild(left);
				line.appendChild(statusChip(gap.status));
				inner.appendChild(line);

				if (gap.reason) {
					inner.appendChild(el('p', 'vc-band-note vc-muted', String(gap.reason)));
				}
				row.appendChild(inner);
				gapList.appendChild(row);
			});
			wrap.appendChild(gapList);
		}

		/* Evidence packs: generate + verify, in-panel. */
		var packRow = el('div', 'vc-band-row');
		var packText = el('div');
		var packActions = el('div', 'vc-pack-actions');

		packText.appendChild(el('h3', 'vc-band-title', L('cp_pack_title', 'Evidence pack')));
		packText.appendChild(el('p', 'vc-band-hint', L('cp_pack_desc', 'A frozen, re-verifiable snapshot of your governance evidence. Share it with a board, regulator, or auditor.')));
		packRow.appendChild(packText);

		var genWrap = el('div', 'vc-pack-gen');

		function setPackMsg(text, kind) {
			genWrap.innerHTML = '';
			genWrap.appendChild(el('p', 'vc-band-hint' + (kind ? ' vc-pack-msg-' + kind : ''), text));
		}

		var genBtn = el('button', 'button button-primary', L('cp_pack_generate', 'Generate evidence pack'));

		genBtn.type = 'button';
		genBtn.addEventListener('click', function () {
			genBtn.disabled = true;
			setPackMsg(L('cp_pack_generating', 'Freezing the current evidence…'));

			vcRest('panel/evidence-pack/generate', { method: 'POST' })
				.then(function (res) {
					genBtn.disabled = false;

					if (res && res.state === 'ok' && res.data && res.data.pack) {
						setPackMsg(
							TT('cp_pack_done', 'Pack v{version} sealed ({sha}). It is listed below.', {
								version: res.data.pack.version || '',
								sha: String(res.data.pack.manifest_sha256 || '').slice(0, 12)
							}),
							'ok'
						);
						loadPacks();
						return;
					}

					if (res && res.code === 'vc_http_403') {
						setPackMsg(L('cp_pack_free', 'Evidence packs are available on paid plans.'), 'free');
						return;
					}

					setPackMsg(L('cp_pack_fail', 'The pack could not be generated. Try again.'), 'err');
				})
				.catch(function () {
					genBtn.disabled = false;
					setPackMsg(L('cp_pack_fail', 'The pack could not be generated. Try again.'), 'err');
				});
		});
		genWrap.appendChild(el('span', 'vc-muted vc-pack-hint', L('cp_pack_hint', 'Generation is one click; the pack is immutable once created.')));
		packActions.appendChild(genBtn);
		packActions.appendChild(genWrap);
		packRow.appendChild(packActions);
		wrap.appendChild(packRow);

		var packListWrap = el('div', 'vc-pack-list');

		wrap.appendChild(packListWrap);

		function loadPacks() {
			packListWrap.innerHTML = '';
			packListWrap.appendChild(vcLoader(L('cp_pack_loading_list', 'Reading pack history…')));

			vcRest('panel/evidence-packs')
			.then(function (res) {
				if (!res || res.state !== 'ok') {
					packListWrap.innerHTML = '';
					packListWrap.appendChild(loadErrorNotice(res));
					return;
				}

				var packs = (res.data && res.data.packs) || (res.data && res.data.data && res.data.data.packs) || [];

				packListWrap.innerHTML = '';

				if (!packs.length) {
					packListWrap.appendChild(el('p', 'vc-band-hint', L('cp_pack_list_none', 'No evidence pack generated yet.')));
					return;
				}

				var list = el('div', 'vc-acc');

				packs.forEach(function (pack) {
					var row = el('div', 'vc-acc-row');
					var inner = el('div', 'vc-pack-band');
					var left = el('span', 'vc-pack-band-left');

					left.appendChild(el('strong', null, 'v' + (pack.version || '')));

					var ref = el('span', 'vc-ref', pack.generated_at ? new Date(pack.generated_at).toLocaleString() : '\u2014');

					left.appendChild(ref);

					var right = el('span', 'vc-pack-band-right');
					var digest = el('span', null, String(pack.manifest_sha256 || '').slice(0, 12) + '…');

					right.appendChild(digest);

					var verifyBtn = el('button', 'button button-small', L('cp_pack_verify', 'Verify'));

					verifyBtn.type = 'button';
					right.appendChild(verifyBtn);

					var detailHost = el('div', 'vc-pack-detail');

					verifyBtn.addEventListener('click', function () {
						if (detailHost.dataset.open === '1') {
							detailHost.dataset.open = '';
							detailHost.innerHTML = '';
							return;
						}

						detailHost.dataset.open = '1';
						detailHost.appendChild(vcLoader(L('cp_pack_loading', 'Reading the manifest…')));

						vcRest('panel/evidence-pack?id=' + encodeURIComponent(pack.id))
							.then(function (pres) {
								detailHost.innerHTML = '';

								if (!pres || pres.state !== 'ok' || !pres.data || !pres.data.pack) {
									detailHost.appendChild(el('p', 'vc-band-hint', L('cp_pack_unavailable', 'The pack manifest could not be loaded.')));
									return;
								}

								var p = pres.data.pack;
								var manifest = (p.manifest && typeof p.manifest === 'object') ? p.manifest : {};
								var keys = Object.keys(manifest);

								detailHost.appendChild(el('div', 'vc-band-label', L('cp_pack_digest', 'Manifest digest (SHA-256)')));
								detailHost.appendChild(el('div', 'vc-band-row', String(p.manifest_sha256 || pack.manifest_sha256 || '')));

								detailHost.appendChild(el('div', 'vc-band-label', L('cp_pack_generated', 'Generated')));
								detailHost.appendChild(el('div', 'vc-band-row', p.generated_at ? new Date(p.generated_at).toLocaleString() : '\u2014'));

								detailHost.appendChild(el('div', 'vc-band-label', L('cp_pack_downloads', 'Downloads')));
								detailHost.appendChild(el('div', 'vc-band-row', String(pack.download_count || 0)));

								detailHost.appendChild(el('div', 'vc-band-label', L('cp_pack_sections', 'Manifest sections')));

								var chips = el('div', 'vc-acc-band-grid');

								keys.sort().forEach(function (k) {
									var sect = el('div', 'vc-band-label');

									sect.textContent = String(k) + (manifest[k] && typeof manifest[k] === 'object'
										? (Array.isArray(manifest[k]) ? ' (' + manifest[k].length + ')' : ' (' + Object.keys(manifest[k]).length + ')')
										: '');
									chips.appendChild(sect);
								});

								detailHost.appendChild(chips);
								detailHost.appendChild(el('p', 'vc-band-note vc-muted', L('cp_pack_note', 'Re-verify upstream by digest: the sealed manifest is immutable and auditable.')));
							})
							.catch(function () {
								detailHost.innerHTML = '';
								detailHost.appendChild(el('p', 'vc-band-hint', L('cp_pack_unavailable', 'The pack manifest could not be loaded.')));
							});
					});

					inner.appendChild(left);
					inner.appendChild(right);
					row.appendChild(inner);
					row.appendChild(detailHost);
					list.appendChild(row);
				});
				packListWrap.appendChild(list);
			})
			.catch(function () {
				packListWrap.innerHTML = '';
				packListWrap.appendChild(el('p', 'vc-band-hint', L('cp_pack_list_err', 'The pack history could not be read. Try again.')));
			});
		}

		loadPacks();

		/* Reference: frameworks + ledger counters. */
		wrap.appendChild(el('h3', 'vc-band-title', L('cp_adv_title', 'Reference: frameworks & ledgers')));
		var hint = el('p', 'vc-band-hint');

		hint.textContent = L('cp_adv_hint', 'The layer an auditor wants: control-by-control mapping and the raw counters behind it.');
		wrap.appendChild(hint);

		// The API keys frameworks by identifier (eu_ai_act, iso_42001…).
		Object.keys(d.frameworks || {}).forEach(function (fwKey) {
			var fw = d.frameworks[fwKey];
			var card = el('div', 'vc-table-card');
			var head = el('div', 'vc-tbl-head');

			head.appendChild(el('h3', 'vc-tbl-title', fw.name));
			var summary = (fw.summary && fw.summary.total > 0) ? Math.round(((fw.summary.satisfied || 0) / fw.summary.total) * 100) : 0;
			var bar = el('div', 'vc-segbar vc-fw-bar');
			var seg = el('div', 'vc-segbar-seg vc-seg--' + (summary >= 80 ? 'emerald' : summary >= 50 ? 'amber' : 'red'));

			seg.style.width = summary + '%';
			bar.appendChild(seg);
			head.appendChild(bar);
			card.appendChild(head);

			var controls = el('div', 'vc-fw-rows vc-rows');

			(fw.controls || []).forEach(function (c) {
				var row = el('div', 'vc-fw-row');

				row.appendChild(el('span', 'vc-muted vc-fw-ref vc-ref', String(c.ref || '')));
				row.appendChild(el('span', 'vc-fw-title', c.title || ''));
				row.appendChild(statusChip(c.status));
				controls.appendChild(row);
			});
			card.appendChild(controls);
			wrap.appendChild(card);
		});

		/* Ledger evidence counters. */
		wrap.appendChild(el('h3', 'vc-band-title', L('cp_evidence_title', 'Ledger evidence')));
		wrap.appendChild(el('p', 'vc-band-hint', L('cp_evidence_sub', 'Counters read straight from your live ledgers, the receipts behind the scorecards above.')));

		var evidenceKeys = [
			['connections', 'cp_ev_connections', 'Connections'],
			['sealed_requests_in_retention', 'cp_ev_sealed', 'Sealed request receipts'],
			['deploy_ledger_rows', 'cp_ev_deploy_rows', 'Deploy ledger rows'],
			['deploy_chains_valid', 'cp_ev_chains', 'Deploy chains valid'],
			['circuit_breaker_events', 'cp_ev_breaker', 'Circuit-breaker events'],
			['org_audit_events', 'cp_ev_org_audit', 'Org audit events']
		];
		var evGrid = el('div', 'vc-kpi-cells');

		evidenceKeys.forEach(function (k) {
			evGrid.appendChild(agentKpiCell(L(k[1], k[2]), String((d.evidence && d.evidence[k[0]]) !== undefined ? d.evidence[k[0]] : 0)));
		});
		wrap.appendChild(evGrid);
	}
	/* ── AI Connections (capabilities catalog) ──────────────────── */

	var capState = { q: '', page: 1, more: false, busy: false, category: '', observer: null };

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

	function capLogo(item, wrap, large) {
		var logo = el('span', 'vc-cap-logo' + (large ? ' vc-cap-logo-lg' : ''));
		var url = item.icon_url || item.google_favicon_url || (item.integration && item.integration.logo_url) || null;

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
		} else {
			/*
			 * No bundled icon: deterministic monogram. We deliberately do NOT
			 * fall back to a third-party favicon service — this plugin only
			 * talks to the Vinkius platform (readme: external communication).
			 */
			monogram(item, logo);
		}
		wrap.appendChild(logo);
	}

	function capRow(item, featured) {
		var card = el('div', 'postbox vc-cap-card' + (featured ? ' vc-cap-top-card' : ''));
		var inside = el('div', 'inside vc-cap-card-inner');

		var main = el('button', 'vc-cap-main');

		main.type = 'button';
		capLogo(item, main, featured);

		var text = el('div', 'vc-cap-text');

		text.appendChild(el('h3', 'vc-cap-title', item.title || item.slug));

		if (item.short_description) {
			text.appendChild(el('p', 'vc-cap-desc', item.short_description));
		}

		if (featured) {
			var meta = [];

			if (Number(item.tools_count) > 0) {
				meta.push(num(item.tools_count) + ' ' + L('cap_capabilities', 'capabilities'));
			}

			if (item.publisher_type) {
				meta.push(item.publisher_type);
			}

			if (meta.length) {
				text.appendChild(el('p', 'vc-cap-meta', meta.join(' · ')));
			}
		}

		main.appendChild(text);
		main.addEventListener('click', function () {
			openCapability(item.slug);
		});
		inside.appendChild(main);

		var side = el('div', 'vc-cap-side' + (featured ? ' vc-cap-side-col' : ''));

		if (item.connected === true) {
			side.appendChild(el('span', 'vc-pill vc-pill-ok', L('cap_connected', 'Connected')));
		}

		if (featured && item.is_new === true) {
			side.appendChild(el('span', 'vc-pill', L('cap_new', 'New')));
		}

		var open = el('a', 'button button-small');

		open.href = capBase() + encodeURIComponent(item.slug || '');
		open.target = '_blank';
		open.rel = 'noopener';
		open.textContent = L('cap_open', 'Open in Vinkius');
		side.appendChild(open);

		inside.appendChild(side);
		card.appendChild(inside);
		return card;
	}

	/* Featured strip: connected connectors first, then the catalog's
	 * own popular order. Only on the unfiltered landing view. */
	var TOP_COUNT = 8;

	function renderTopConnections(items) {
		var ranking = items.slice();

		ranking.sort(function (a, b) {
			return (b.connected === true) - (a.connected === true);
		});

		var top = ranking.slice(0, TOP_COUNT);
		var rest = items.filter(function (item) {
			return top.indexOf(item) < 0;
		});

		if (!top.length) {
			return { top: null, rest: rest };
		}

		var section = el('div', 'vc-cap-top');

		section.appendChild(el('h2', 'vc-section-title', L('cap_top', 'Top connections')));

		var grid = el('div', 'vc-cap-top-grid');

		top.forEach(function (item) {
			grid.appendChild(capRow(item, true));
		});
		section.appendChild(grid);

		return { top: section, rest: rest };
	}

	function bootCapabilities() {
		var root = document.getElementById('vc-cap-root');

		if (!root) {
			return;
		}

		var input = document.getElementById('vc-cap-input');
		var clearBtn = document.getElementById('vc-cap-clear');
		var results = document.getElementById('vc-cap-results');

		if (!input || !results) {
			return;
		}

		var timer = null;

		function reload() {
			capState.page = 1;
			loadCapabilities(true);
		}

		input.addEventListener('input', function () {
			capState.q = input.value;
			capState.category = '';

			if (timer) {
				clearTimeout(timer);
			}

			timer = setTimeout(reload, 250);
		});

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				capState.q = '';
				capState.category = '';
				reload();
				input.focus();
			});
		}

		function armSentinel() {
			var sentinel = document.getElementById('vc-cap-sentinel');

			if (!sentinel) {
				return;
			}

			sentinel.innerHTML = '';

			if (!capState.more) {
				return;
			}

			sentinel.appendChild(el('span', 'spinner is-active'));

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
				return;
			}

			wrap.innerHTML = '';

			var list = [{ slug: '', label: L('cap_all', 'All'), listing_count: null }];

			(cats || []).forEach(function (c) {
				if (c && c.slug && c.slug !== 'other') {
					list.push(c);
				}
			});

			list.forEach(function (c) {
				var li = el('li');
				var a = el('a', capState.category === (c.slug || '') ? 'current' : '');

				a.href = '#vc-cap-cats';

				if (c.is_featured && c.color) {
					var dot = el('span', 'vc-cap-dot');

					dot.style.background = c.color;
					a.appendChild(dot);
				}

				a.appendChild(document.createTextNode(c.label || c.slug || L('cap_all', 'All')));

				if (c.listing_count) {
					a.appendChild(el('span', 'count', '(' + c.listing_count + ')'));
				}

				a.addEventListener('click', function (e) {
					e.preventDefault();
					capState.category = c.slug || '';
					capState.q = '';
					input.value = '';
					reload();
				});
				li.appendChild(a);
				wrap.appendChild(li);
			});
		}

		function loadCapabilities(reset) {
			if (capState.busy) {
				return;
			}

			capState.busy = true;

			if (!reset) {
				var loadingSentinel = document.getElementById('vc-cap-sentinel');

				if (loadingSentinel) {
					loadingSentinel.innerHTML = '';
					loadingSentinel.appendChild(el('span', 'spinner is-active'));
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

					var stats = d.stats || {};
					var statsEl = document.getElementById('vc-cap-stats');

					if (statsEl && (stats.catalog_count || stats.connectors_count)) {
						statsEl.textContent = L('cap_catalog', '%1$s connectors \u00B7 %2$s capabilities')
							.replace('%1$s', num(stats.connectors_count || stats.catalog_count))
							.replace('%2$s', num(stats.capabilities_count || 0));
					}

					renderCategories(d.categories || []);

					var landing = !capState.q && !capState.category && capState.page === 1;
					var topRoot = document.getElementById('vc-cap-top');
					var catalogTitle = document.getElementById('vc-cap-catalog-title');

					if (topRoot) {
						topRoot.innerHTML = '';
					}

					if (landing) {
						var split = renderTopConnections(d.items || []);

						if (split.top && topRoot) {
							topRoot.appendChild(split.top);
						}

						d.items = split.rest;

						if (catalogTitle) {
							catalogTitle.hidden = false;
						}
					} else if (catalogTitle) {
						catalogTitle.hidden = true;
					}
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
					if (capState.q.length >= 2) {
						results.appendChild(stateNotice(
							'notice-info',
							L('cap_no_matches', 'No matches for "%s".').replace('%s', capState.q) + ' ' + L('cap_try', 'Try rephrasing your search.')
						));
					} else {
						results.appendChild(stateNotice('notice-info', L('cap_none', 'No connectors in the catalog yet.')));
					}
				}
				capState.more = false;
				capState.busy = false;
				armSentinel();
			}).catch(function () {
				if (reset) {
					results.innerHTML = '';
					results.appendChild(stateNotice('notice-error', L('cap_unavailable', 'Search is unavailable right now. Please try again.')));
				}
				capState.busy = false;
				armSentinel();
			});
		}

		loadCapabilities(true);
	}

	/* ── Section page boot (cloud view renderers, live data) ───────── */

	function bootSection() {
		var root = document.getElementById('vc-gov');

		if (!root) {
			return null;
		}

		var body = document.getElementById('vc-gov-body');

		if (!body) {
			return null;
		}

		var state = {
			section: root.dataset.section || 'overview',
			// All period-bearing sections open on Today, same default as
			// the cloud governance views.
			period: 'today',
			page: 1,
			drill: null,
			drillPage: 1
		};

		/* ── In-panel drill-down ───────────────────────────────────────
		 * Every cloud deep-link row can be drilled inside the panel: the
		 * hook parses the deep-link URL into {section,id,sub} and boots
		 * the per-entity request log read locally. Rows without a mapping
		 * keep their cloud hop (window.open). */
		var SECTION_BY_KIND = {
			clients: 'clients',
			connectors: 'connectors',
			cost: 'cost',
			'tool-performance': 'tool-performance',
			'security-posture': 'security',
			errors: 'errors',
			'token-fleet': 'token-fleet'
		};

		function parseDeepUrl(url) {
			var base = govBase();

			if (!base || base === '#' || String(url).indexOf(base) !== 0) {
				return null;
			}

			var segs = String(url)
				.slice(base.length)
				.replace(/^\/+/, '')
				.split('?')[0]
				.split('/')
				.map(decodeURIComponent);

			// /governance/requests/{id} — the single request record.
			if (segs[0] === 'requests' && segs[1]) {
				return { section: 'requests', id: segs[1], sub: null };
			}

			if (SECTION_BY_KIND[segs[0]] && segs[1]) {
				return { section: SECTION_BY_KIND[segs[0]], id: segs[1], sub: segs[2] || null };
			}

			return null;
		}

		window.__vcDrillHook = function (url) {
			var parsed = parseDeepUrl(url);

			if (!parsed) {
				return false;
			}

			state.drill = parsed;
			state.drillPage = 1;
			renderSection();
			return true;
		};

		/* Generic drill view: header + request-log table (+ pagination). */
		function renderDrill() {
			body.innerHTML = '';

			if (C) {
				C.disposeAll();
			}

			if (!state.drill) {
				return;
			}

			var head = postbox(
				L('drill_title', 'Record drill-down'),
				(function () {
					var bar = el('div', 'vc-drill-head');
					var back = el('button', 'button button-small');

					back.type = 'button';
					back.textContent = L('drill_back', 'Back to {section}').replace('{section}', state.section);
					back.addEventListener('click', function () {
						state.drill = null;
						renderSection();
					});
					bar.appendChild(back);
					return bar;
				})()
			);
			body.appendChild(head);

			var host = el('div');

			body.appendChild(host);
			host.appendChild(vcLoader(L('drill_loading', 'Reading the record trail…')));

			var query = 'section=' + encodeURIComponent(state.drill.section) +
				'&drill_id=' + encodeURIComponent(state.drill.id) +
				(state.drill.sub ? '&drill_sub=' + encodeURIComponent(state.drill.sub) : '') +
				'&period=' + encodeURIComponent(state.period) +
				'&page=' + state.drillPage;

			vcRest('panel/section?' + query).then(function (res) {
				host.innerHTML = '';

				if (!res || res.state !== 'ok') {
					host.appendChild(loadErrorNotice(res));
					return;
				}

				var d = res.data || {};

				if (state.drill.section === 'requests') {
					renderRequestDetail(d.request || (d.data && d.data.request) || {}, host);
					return;
				}

				// Per-entity payload: {server|client|tool|token, requests, pagination}.
				var rows = d.requests || (d.data && d.data.requests) || [];
				var entity = d.server || d.client || d.tool || d.token || null;

				if (entity && (entity.name || entity.external_id)) {
					host.appendChild(el('h3', 'vc-band-title', String(entity.name || entity.external_id)));
				}

				var cols = [];
				var candidates = {
					created_at_iso: [['created_at_iso', 'created_at'], L('col_when', 'When'), function (r0) {
						var v = r0.created_at_iso || r0.created_at;

						return v ? new Date(v).toLocaleString() : '\u2014';
					}],
					tool_name: [['tool_name'], L('col_tool', 'Capability'), null],
					client: [['client_name', 'client'], L('col_client', 'Client'), function (r0) {
						return typeof r0.client_name === 'string' ? r0.client_name : typeof r0.client === 'object' ? ((r0.client && r0.client.name) || '\u2014') : (r0.client || '\u2014');
					}],
					token: [['token_name'], L('col_token', 'Token'), null],
					status_code: [['status_code'], L('col_status', 'Status'), null],
					response_time_ms: [['response_time_ms'], L('col_latency', 'Latency'), function (r0) {
						var s = el('span', null, C ? C.formatLatency(r0.response_time_ms) : '\u2014');

						if (C) {
							s.className = C.latencyTextClass(r0.response_time_ms);
							s.title = C.latencyTooltip(r0.response_time_ms);
						}
						return s;
					}],
					error: [['error_message', 'vinkius_error'], L('col_error', 'Error'), null]
				};

				var first = rows[0] || {};

				Object.keys(candidates).forEach(function (k) {
					var spec = candidates[k];
					var key = (spec[0].filter(function (kk) { return first[kk] !== undefined; })[0]);

					if (key) {
						cols.push({ h: spec[1], align: k === 'response_time_ms' || k === 'status_code' ? 'right' : '', key: key, cell: spec[2] });
					}
				});

				function colCell(col, r0) {
					if (col.cell) {
						return col.cell(r0);
					}

					return td_text(r0, col.key);
				}

				if (!rows.length) {
					host.appendChild(el('p', 'vc-band-hint', L('no_data', 'No data in this period.')));
					return;
				}

				var t = el('table', 'vc-tbl');
				var thead = el('thead');
				var hrow = el('tr');

				cols.forEach(function (col) {
					hrow.appendChild(el('th', col.align ? 'vc-' + col.align : '', col.h));
				});
				thead.appendChild(hrow);
				t.appendChild(thead);

				var tbody = el('tbody');

				rows.forEach(function (r0) {
					var tr = el('tr');

					cols.forEach(function (col) {
						var td = el('td', col.align === 'right' ? 'vc-right vc-mono' : '');
						var out = colCell(col, r0);

						if (out !== null && out !== undefined) {
							if (out.nodeType) {
								td.appendChild(out);
							} else if (typeof out === 'string') {
								td.textContent = out;
							} else {
								td.textContent = String(out);
							}
						}
						tr.appendChild(td);
					});
					tbody.appendChild(tr);
				});
				t.appendChild(tbody);

				var card = el('div', 'vc-table-card');

				card.appendChild(t);
				host.appendChild(card);

				var pag = d.pagination || (d.data && d.data.pagination);

				if (pag) {
					var bar = paginationBar(pag, function (p) {
						state.drillPage = p;
						renderSection();
					});

					if (bar) {
						bar.classList.add('vc-drill-pagbar');
						host.appendChild(bar);
					}
				}
			}).catch(function () {
				host.innerHTML = '';
				host.appendChild(loadErrorNotice(null, null));
			});
		}

		function renderRequestDetail(r0, host) {
			host.innerHTML = '';

			if (!r0 || !Object.keys(r0).length) {
				host.appendChild(el('p', 'vc-band-hint', L('no_data', 'No data in this period.')));
				return;
			}

			var fields = [
				['tool_name', L('col_tool', 'Capability')],
				['server_name', L('tb_connector', 'Connector')],
				['token_name', L('col_token', 'Token')],
				['client_name', L('col_client', 'Client')],
				['status_code', L('col_status', 'Status')],
				['response_time_ms', L('col_latency', 'Latency')],
				['created_at_iso', L('col_when', 'When')],
				['request_hash', L('col_hash', 'Request hash')],
				['error_message', L('col_error', 'Error')]
			];
			var card = el('div', 'vc-table-card');

			fields.forEach(function (f) {
				var rowEl = el('div', 'vc-band-row');
				var v = r0[f[0]];

				if (v === null || v === undefined || v === '') {
					return;
				}

				rowEl.appendChild(el('span', 'vc-band-label', f[1]));
				rowEl.appendChild(el('span', null, typeof v === 'object' ? JSON.stringify(v) : String(v)));
				card.appendChild(rowEl);
			});
			host.appendChild(card);
		}

		function renderSection() {
			if (state.drill) {
				renderDrill();
				return;
			}

			body.innerHTML = '';

			if (C) {
				C.disposeAll();
			}

			var loading = el('p', 'vc-loading');

			loading.appendChild(el('span', 'spinner is-active'));
			body.appendChild(loading);

			vcRest(
				'panel/section?section=' + encodeURIComponent(state.section) +
				'&period=' + encodeURIComponent(state.period) +
				'&page=' + state.page
			).then(function (res) {
				body.innerHTML = '';

				if (C) {
					C.disposeAll();
				}

				if (!res || res.state !== 'ok') {
					body.appendChild(loadErrorNotice(res));
					return;
				}

				var data = res.data || {};
				var d = data.data || {};

				var ctx = {
					data: data,
					section: state.section,
					period: state.period,
					page: state.page,
					plan: data.plan || d.plan || null,
					knownFree: d.is_free === true || data.plan === 'free',
					isFree: d.is_free === true || !data.plan || String(data.plan || 'free') === 'free',
					goPage: function (p) {
						state.page = p;
						renderSection();
					}
				};

				var renderers = {
					overview: renderOverview,
					clients: renderClients,
					connectors: renderConnectors,
					'token-fleet': renderTokenFleet,
					agents: renderAgents,
					cost: renderCost,
					'tool-performance': renderToolPerf,
					security: renderSecurity,
					errors: renderErrors,
					compliance: renderCompliance
				};
				var renderer = renderers[state.section] || renderOverview;

				renderer(d, ctx, body);

				renderPeriods(d.allowed_periods, state.period, function (p) {
					state.period = p;
					state.page = 1;
					renderSection();
				});

				if (!Object.keys(d).length) {
					body.appendChild(stateNotice('notice-info', L('no_data', 'No data in this period.')));
				}
			}).catch(function (err) {
				body.innerHTML = '';

				if (C) {
					C.disposeAll();
				}
				body.appendChild(loadErrorNotice(null, err));
			});
		}

		renderSection();
		return {
			reload: renderSection
		};
	}

	bootSection();
	bootCapabilities();
})();
