/**
 * Vinkius AI Governance — chart layer.
 *
 * 1:1 port of the cloudfrontend ECharts vocabulary
 * (src/components/charts/chart-tokens.ts, base-option.ts, sparkline-option.ts,
 * tick-density.ts, src/lib/latency.ts, src/features/governance/lib/formatters.ts
 * and every src/features/governance/charts/* builder) so the WordPress panel
 * renders the exact same charts, colours, thresholds and tooltips as the
 * Vinkius cloud.
 *
 * Requires the vendored ECharts (admin/js/vendor/echarts.min.js, Apache-2.0).
 * Exposes window.VcCharts; the section renderers (vc-governance.js) build
 * their data on top of it.
 *
 * @license GPLv2 or later
 */
(function () {
	'use strict';

	var ECharts = (typeof window !== 'undefined' && window.echarts) ? window.echarts : null;

	/* ── Labels: VCConfig.labels with the exact cloudfrontend en.json
	 * values as fallbacks, so the display matches the cloud even when the
	 * PHP side ships no entry for a key. ──────────────────────────── */

	function L(key, fallback) {
		var labels = (typeof VCConfig !== 'undefined' && VCConfig && VCConfig.labels) || {};
		var v = labels[key];

		return (v === undefined || v === null || v === '') ? fallback : v;
	}

	/** "API: {api} · Vinkius: {vinkius}" interpolation. */
	function T(str, vars) {
		return String(str).replace(/\{(\w+)\}/g, function (m, k) {
			return vars && vars[k] !== undefined ? String(vars[k]) : m;
		});
	}

	/* ── Latency: THE single source of truth (port of lib/latency.ts).
	 * A call is healthy under 15s, degraded 15–30s, broken past 30s. ── */

	var LATENCY_OK_MS = 15000;
	var LATENCY_SLOW_MS = 30000;

	function latencyTone(ms) {
		if (ms === null || ms === undefined || typeof ms !== 'number' || isNaN(ms)) {
			return 'unknown';
		}

		if (ms < LATENCY_OK_MS) {
			return 'green';
		}

		if (ms < LATENCY_SLOW_MS) {
			return 'amber';
		}

		return 'red';
	}

	/** CSS class per tone — text colour (cloud hex values). */
	function latencyTextClass(ms) {
		return 'vc-lat-' + latencyTone(ms);
	}

	/** CSS class per tone — bar fill. */
	function latencyBarClass(ms) {
		return 'vc-latbar-' + latencyTone(ms);
	}

	/** Bar width percent vs the 30s slow threshold. */
	function latencyBarWidth(ms) {
		if (ms === null || ms === undefined || typeof ms !== 'number' || isNaN(ms)) {
			return 0;
		}

		return Math.min(Math.round((ms / LATENCY_SLOW_MS) * 100), 100);
	}

	/** Latency → chart-ready rgba fill (bar/scatter item colours). */
	function latencyBarColor(ms) {
		switch (latencyTone(ms)) {
			case 'green':
				return 'rgba(16,185,129,0.85)';
			case 'amber':
				return 'rgba(245,158,11,0.85)';
			case 'red':
				return 'rgba(239,68,68,0.85)';
			default:
				return 'rgba(113,113,122,0.5)';
		}
	}

	function latencyToneLabel(ms) {
		var words = {
			green: L('health_healthy', 'Healthy'),
			amber: L('health_moderate', 'Moderate'),
			red: L('health_slow', 'Slow'),
			unknown: L('health_unknown', 'Unknown')
		};

		return words[latencyTone(ms)];
	}

	/** 3603 → "3s 603ms"; sub-second stays in ms ("412ms"). */
	function formatLatency(ms) {
		if (ms === null || ms === undefined || typeof ms !== 'number' || isNaN(ms)) {
			return L('not_available', 'Not available');
		}

		var value = Math.round(ms);

		if (value < 1000) {
			return value + 'ms';
		}

		var secs = Math.floor(value / 1000);

		return secs + 's ' + (value % 1000) + 'ms';
	}

	/** total − upstream, clamped at 0; null when either side is unknown. */
	function overheadMs(totalMs, apiMs) {
		if (totalMs === null || totalMs === undefined) {
			return null;
		}

		if (apiMs === null || apiMs === undefined) {
			return null;
		}

		return Math.max(0, totalMs - apiMs);
	}

	function latencyTooltip(totalMs, apiMs) {
		if (totalMs === null || totalMs === undefined) {
			return '';
		}

		if (apiMs === null || apiMs === undefined) {
			return L('latency_tooltip_total', 'Total: {total}').replace('{total}', formatLatency(totalMs));
		}

		var oh = overheadMs(totalMs, apiMs) || 0;

		return L('latency_tooltip_breakdown', 'API: {api} | Vinkius: {vinkius} | Total: {total}')
			.replace('{api}', formatLatency(apiMs))
			.replace('{vinkius}', formatLatency(oh))
			.replace('{total}', formatLatency(totalMs));
	}

	/* ── Formatters (port of governance/lib/formatters.ts). ────────── */

	/** K/M abbreviation (1234 → "1.2K", 2400000 → "2.4M"). */
	function fmt(n) {
		if (n < 1000) {
			return String(n);
		}

		if (n < 1000000) {
			return (n / 1000).toFixed(1) + 'K';
		}

		return (n / 1000000).toFixed(1) + 'M';
	}

	function fmtInt(n) {
		return (n === null || n === undefined ? 0 : n).toLocaleString();
	}

	/** USD with cent/micro precision for small amounts. */
	function usd(n) {
		if (n >= 1000) {
			return '$' + (n / 1000).toFixed(1) + 'K';
		}

		if (n >= 1) {
			return '$' + n.toFixed(2);
		}

		if (n >= 0.01) {
			return '$' + n.toFixed(4);
		}

		return '$' + n.toFixed(6);
	}

	/** Byte abbreviation (1048576 → "1.0 MB"). */
	function formatBytes(bytes) {
		if (bytes >= 1073741824) {
			return (bytes / 1073741824).toFixed(1) + ' GB';
		}

		if (bytes >= 1048576) {
			return (bytes / 1048576).toFixed(1) + ' MB';
		}

		if (bytes >= 1024) {
			return (bytes / 1024).toFixed(1) + ' KB';
		}

		return bytes + ' B';
	}

	/** Human duration (1260 → "21m", 3720 → "1h 2m"). */
	function formatDuration(seconds) {
		if (seconds === 0) {
			return L('not_available', 'Not available');
		}

		if (seconds < 60) {
			return seconds + 's';
		}

		var mins = Math.floor(seconds / 60);
		var secs = seconds % 60;

		if (mins < 60) {
			return secs > 0 ? mins + 'm ' + secs + 's' : mins + 'm';
		}

		var hours = Math.floor(mins / 60);
		var remain = mins % 60;

		return remain > 0 ? hours + 'h ' + remain + 'm' : hours + 'h';
	}

	/** "just now" / "5 minutes ago" / "3 hours ago" / "2 days ago". */
	function timeAgo(dateStr, neverLabel) {
		if (!dateStr) {
			return neverLabel || L('never', 'Never');
		}

		var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);

		if (diff < 60) {
			return L('just_now', 'just now');
		}

		if (diff < 3600) {
			return Math.floor(diff / 60) + ' ' + L('minutes_ago', 'minutes ago');
		}

		if (diff < 86400) {
			return Math.floor(diff / 3600) + ' ' + L('hours_ago', 'hours ago');
		}

		return Math.floor(diff / 86400) + ' ' + L('days_ago', 'days ago');
	}

	/** Error rate (%) → CSS class (emerald / amber / red). */
	function errorRateClass(rate) {
		if (rate === 0) {
			return 'vc-rate-0';
		}

		if (rate < 5) {
			return 'vc-rate-mid';
		}

		return 'vc-rate-high';
	}

	/** HTTP status code → CSS class. */
	function statusColorClass(code) {
		if (!code) {
			return 'vc-mut';
		}

		if (code < 400) {
			return 'vc-ok';
		}

		return 'vc-bad';
	}

	/** FinOps trimmed share of the pre-trim response, or null. */
	function trimmedSharePercent(saved, produced) {
		if (saved <= 0) {
			return null;
		}

		if (produced === null || produced === undefined || produced <= 0) {
			return null;
		}

		return Math.round((saved / produced) * 100);
	}

	/* ── Chart tokens (port of chart-tokens.ts). THE styling vocabulary:
	 * dark tooltip shell, palettes, latency-coded fills, axis/grid styles,
	 * empty states and date-axis formatters. ───────────────────────── */

	var PALETTE = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#14b8a6'];
	var ERROR_SERIES = {
		agent: '#f59e0b',
		upstream: '#ef4444',
		vinkius: '#a855f7'
	};

	/* Compact dashboard typography: 12 base / 11 dense axes / 28 hero metric. */
	var CHART_FONT_SIZE = 12;
	var AXIS_FONT_SIZE = 11;

	var DENSE_CATEGORY_LABEL = { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE };
	var CHART_NAME_TEXT_STYLE = { color: '#71717a', fontSize: AXIS_FONT_SIZE };
	var DENSE_TRUNCATED_CATEGORY_LABEL = { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, width: 90, overflow: 'truncate' };

	var TOOLTIP_SHELL = {
		backgroundColor: '#0a0a0a',
		borderColor: 'rgba(255,255,255,0.08)',
		textStyle: { color: '#e4e4e7', fontSize: CHART_FONT_SIZE },
		borderRadius: 8,
		padding: [10, 14]
	};

	var DASHED_SPLIT_LINE = { lineStyle: { color: 'rgba(255,255,255,0.04)', type: 'dashed' } };
	var FAINT_AXIS_LINE = { lineStyle: { color: 'rgba(255,255,255,0.06)' } };
	var MUTED_AXIS_LABEL = { color: '#71717a', fontSize: AXIS_FONT_SIZE };

	function tooltipShell(extra) {
		var o = {};

		for (var k in TOOLTIP_SHELL) {
			if (Object.prototype.hasOwnProperty.call(TOOLTIP_SHELL, k)) {
				o[k] = TOOLTIP_SHELL[k];
			}
		}

		if (extra) {
			for (var e in extra) {
				if (Object.prototype.hasOwnProperty.call(extra, e)) {
					o[e] = extra[e];
				}
			}
		}

		return o;
	}

	function axisLabel(formatter) {
		return Object.assign({}, MUTED_AXIS_LABEL, formatter !== undefined ? { formatter: formatter } : null);
	}

	/** "No data available" centered on top of the chart. */
	function emptyGraphic(isEmpty) {
		if (!isEmpty) {
			return undefined;
		}

		return [{
			type: 'text',
			left: 'center',
			top: 'middle',
			style: {
				text: L('no_data_available', 'No data available'),
				fill: '#52525b',
				fontSize: CHART_FONT_SIZE,
				fontWeight: 500,
				fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
			}
		}];
	}

	function emptyHorizontalBar(axisLabelSuffix) {
		return {
			graphic: emptyGraphic(true),
			grid: { top: 4, left: 4, right: 16, bottom: 0, containLabel: true },
			xAxis: {
				type: 'value',
				min: 0,
				max: 100,
				axisLabel: axisLabel('{value}' + (axisLabelSuffix || 'ms')),
				splitLine: DASHED_SPLIT_LINE
			},
			yAxis: { type: 'category', data: [], axisLabel: { show: false }, axisLine: { show: false }, axisTick: { show: false } },
			series: [{ type: 'bar', data: [] }]
		};
	}

	function emptyScatter() {
		return {
			graphic: emptyGraphic(true),
			grid: { top: 24, right: 16, bottom: 0, left: 8, containLabel: true },
			xAxis: {
				name: L('axis_avg_latency', 'Avg Latency'),
				nameLocation: 'center',
				nameGap: 28,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				min: 0,
				max: 100,
				axisLabel: axisLabel(function (v) {
					return formatLatency(v);
				}),
				splitLine: DASHED_SPLIT_LINE
			},
			yAxis: {
				name: L('axis_failure_rate', 'Failure Rate (%)'),
				nameLocation: 'center',
				nameGap: 32,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				min: 0,
				max: 10,
				axisLabel: axisLabel('{value}%'),
				splitLine: DASHED_SPLIT_LINE
			},
			series: [{ type: 'scatter', data: [] }]
		};
	}

	function emptyPie() {
		return {
			graphic: emptyGraphic(true),
			series: [{
				type: 'pie',
				radius: ['40%', '70%'],
				center: ['50%', '50%'],
				data: [],
				silent: true,
				label: { show: false },
				itemStyle: { color: 'rgba(255,255,255,0.03)', borderColor: 'rgba(255,255,255,0.06)', borderWidth: 1 }
			}]
		};
	}

	/** Hourly "2026-04-28 14:00" → "14h" · daily "2026-04-28" → "28 Apr". */
	function formatAxisLabel(raw) {
		var parts = String(raw).split(' ');

		if (parts.length > 1) {
			return parts[1] ? parts[1].replace(':00', 'h') : raw;
		}

		return new Date(raw + 'T00:00:00').toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
	}

	/** Hourly → "Apr 28, 14:00" · daily → "Apr 28". */
	function formatTooltipHeader(raw) {
		var parts = String(raw).split(' ');

		function day(iso) {
			return new Date(iso + 'T00:00:00').toLocaleDateString('en-US', { day: '2-digit', month: 'short' });
		}

		if (parts.length > 1) {
			return day(parts[0]) + ', ' + parts[1];
		}

		return day(raw);
	}

	/** Deterministic tick thinning: ≤14 all · ≤31 every 2nd · ≤48 every 4th · beyond every 6th. */
	function categoryTickInterval(count) {
		if (count <= 14) {
			return function () {
				return true;
			};
		}

		if (count <= 31) {
			return function (index) {
				return index % 2 === 0;
			};
		}

		if (count <= 48) {
			return function (index) {
				return index % 4 === 0;
			};
		}

		return function (index) {
			return index % 6 === 0;
		};
	}

	/* ── Sparkline recipe (port of sparkline-option.ts): the minimal
	 * line+area micro-chart for KPI cards and token-fleet rows. ─────── */

	function hexToRgba(hex, alpha) {
		if (hex.charAt(0) === '#' && hex.length === 7) {
			var r = parseInt(hex.slice(1, 3), 16);
			var g = parseInt(hex.slice(3, 5), 16);
			var b = parseInt(hex.slice(5, 7), 16);

			return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
		}

		return hex;
	}

	function sparklineOption(input) {
		var values = input.values || [];
		var color = input.color;
		var smooth = input.smooth === undefined ? true : input.smooth;
		var floor = input.floor;
		var baseAtZero = input.baseAtZero;
		var headroom = input.headroom;
		var gutter = input.gutter === undefined ? 2 : input.gutter;

		if (!values.length) {
			return {
				grid: { top: 0, right: 0, bottom: 0, left: 0 },
				xAxis: { show: false, data: [] },
				yAxis: { show: false },
				series: [{ type: 'line', data: [] }]
			};
		}

		var min = Math.min.apply(null, values);
		var yAxis = { type: 'value', show: false };

		if (baseAtZero) {
			yAxis.min = 0;
		} else if (floor !== undefined) {
			yAxis.min = min * floor;
		}

		if (headroom !== undefined) {
			var max = Math.max.apply(null, values.concat([1]));
			yAxis.max = max * headroom;
		}

		return {
			grid: { top: 2, right: gutter, bottom: 2, left: gutter },
			xAxis: { type: 'category', show: false, data: values.map(function (_, i) { return i; }) },
			yAxis: yAxis,
			series: [{
				type: 'line',
				data: values.slice(),
				smooth: smooth,
				showSymbol: false,
				lineStyle: { width: 1.5, color: color },
				areaStyle: { color: hexToRgba(color, 0.18) }
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Overview (Mission Control) builders — port of overview-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	/** Bar: AI agent activity per hour/day. */
	function heatmapOption(entries) {
		var rawDates = (entries || []).map(function (d) {
			return d.date;
		});
		var labels = rawDates.map(formatAxisLabel);
		var values = rawDates.map(function (_, i) {
			return entries[i].value;
		});

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var item = p[0];
					var header = formatTooltipHeader(rawDates[item.dataIndex] !== undefined ? rawDates[item.dataIndex] : item.axisValue);
					var many = item.value !== 1;

					return '<div style="font-weight:600;margin-bottom:4px;color:#e4e4e7">' + header + '</div>'
						+ '<div style="color:#a1a1aa;font-size:' + CHART_FONT_SIZE + 'px">' + T(many ? L('interaction_many', '{count} interactions') : L('interaction_one', '{count} interaction'), { count: item.value }) + '</div>';
				}
			}),
			grid: { top: 8, right: 16, bottom: 0, left: 4, containLabel: true },
			xAxis: {
				type: 'category',
				data: labels,
				axisLabel: Object.assign({}, DENSE_CATEGORY_LABEL, { interval: categoryTickInterval(labels.length) }),
				axisLine: FAINT_AXIS_LINE,
				axisTick: { show: false }
			},
			yAxis: { type: 'value', min: 0, axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			series: [{
				type: 'bar',
				data: values,
				barWidth: labels.length > 48 ? '70%' : '60%',
				itemStyle: { borderRadius: [3, 3, 0, 0], color: 'rgba(59,130,246,0.72)' }
			}]
		};
	}

	/** Dual-axis bar + line: request volume vs total/API latency. */
	function requestVolumeOption(data) {
		var REQUESTS = L('col_requests', 'Requests');
		var TOTAL_LATENCY = L('total_latency', 'Total Latency');
		var API_RESPONSE = L('api_response', 'API Response');
		var rows = data || [];
		var rawDates = rows.map(function (t) {
			return t.label;
		});
		var labels = rawDates.map(formatAxisLabel);

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				formatter: function (p) {
					var req = null, lat = null, api = null;

					p.forEach(function (x) {
						if (x.seriesName === REQUESTS) {
							req = x;
						}

						if (x.seriesName === TOTAL_LATENCY) {
							lat = x;
						}

						if (x.seriesName === API_RESPONSE) {
							api = x;
						}
					});
					var idx = (p[0] && p[0].dataIndex !== undefined) ? p[0].dataIndex : 0;
					var entry = rows[idx];
					var header = formatTooltipHeader(rawDates[idx] !== undefined ? rawDates[idx] : (p[0] ? p[0].axisValue : ''));
					var apiLine = api
						? '<div><span style="color:#71717a">' + API_RESPONSE + '</span><br/><span style="color:#34d399;font-weight:700">' + formatLatency((api.value !== null && api.value !== undefined) ? api.value : 0) + '</span></div>'
						: '';
					var overhead = entry && entry.avg_api_latency_ms != null
						? '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + L('vinkius_overhead', 'Vinkius Overhead') + ': <span style="color:#71717a;font-weight:600">' + formatLatency(Math.max(0, ((lat && lat.value) || 0) - entry.avg_api_latency_ms)) + '</span></div>'
						: '';

					return '<div style="font-weight:600;margin-bottom:6px;color:#e4e4e7">' + header + '</div>'
						+ '<div style="display:flex;gap:16px">'
						+ '<div><span style="color:#71717a">' + REQUESTS + '</span><br/><span style="color:#3b82f6;font-weight:700">' + ((req && req.value) !== null && (req && req.value) !== undefined ? Number(req.value).toLocaleString() : 0) + '</span></div>'
						+ '<div><span style="color:#71717a">' + TOTAL_LATENCY + '</span><br/><span style="color:#f59e0b;font-weight:700">' + formatLatency((lat && lat.value) || 0) + '</span></div>'
						+ apiLine + '</div>' + overhead;
				}
			}),
			legend: {
				show: true,
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8,
				itemGap: 16
			},
			grid: { top: 24, right: 44, bottom: 0, left: 4, containLabel: true },
			xAxis: { type: 'category', data: labels, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: [
				{ type: 'value', name: REQUESTS, nameTextStyle: MUTED_AXIS_LABEL, axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
				{
					type: 'value',
					name: L('total_latency', 'Total Latency'),
					nameTextStyle: MUTED_AXIS_LABEL,
					axisLabel: axisLabel(function (value) {
						return formatLatency(value);
					}),
					splitLine: { show: false }
				}
			],
			series: (function () {
				var series = [
					{
						name: REQUESTS,
						type: 'bar',
						yAxisIndex: 0,
						data: rows.map(function (t) {
							return t.total_requests;
						}),
						barWidth: '60%',
						itemStyle: { borderRadius: [3, 3, 0, 0], color: 'rgba(59,130,246,0.72)' }
					},
					{
						name: TOTAL_LATENCY,
						type: 'line',
						yAxisIndex: 1,
						data: rows.map(function (t) {
							return t.avg_latency_ms;
						}),
						smooth: 0.4,
						showSymbol: false,
						lineStyle: { width: 2.5, color: '#f59e0b' },
						itemStyle: { color: '#f59e0b' }
					}
				];

				if (rows.some(function (t) {
					return t.avg_api_latency_ms != null;
				})) {
					series.push({
						name: API_RESPONSE,
						type: 'line',
						yAxisIndex: 1,
						data: rows.map(function (t) {
							return t.avg_api_latency_ms != null ? t.avg_api_latency_ms : null;
						}),
						smooth: 0.4,
						showSymbol: false,
						lineStyle: { width: 2, color: '#34d399', type: 'dashed' },
						itemStyle: { color: '#34d399' }
					});
				}

				return series;
			})()
		};
	}

	/** Horizontal bar: top connectors, latency-coded. */
	function topConnectorsOption(data) {
		var rows = data || [];
		var sorted = rows.slice().sort(function (a, b) {
			return a.total_calls - b.total_calls;
		});

		if (!sorted.length) {
			return emptyHorizontalBar();
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var d = p[0];
					var c = sorted[d.dataIndex];

					if (!c) {
						return '';
					}

					var apiLine = c.avg_api_latency_ms != null
						? '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + T(L('chart_api_overhead', 'API: {api} · Overhead: {overhead}'), { api: formatLatency(c.avg_api_latency_ms), overhead: formatLatency(Math.max(0, c.avg_latency_ms - c.avg_api_latency_ms)) }) + '</div>'
						: '';

					return '<div style="font-weight:600;margin-bottom:4px">' + d.name + '</div>'
						+ '<div style="color:#71717a">' + L('col_requests', 'Requests') + ': <span style="color:#3b82f6;font-weight:700">' + d.value.toLocaleString() + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_total', 'Total') + ': <span style="color:' + latencyBarColor(c.avg_latency_ms) + ';font-weight:700">' + formatLatency(c.avg_latency_ms) + '</span></div>' + apiLine;
				}
			}),
			grid: { top: 4, right: 16, bottom: 0, left: 4, containLabel: true },
			xAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			yAxis: { type: 'category', data: sorted.map(function (c) { return c.name; }), axisLabel: DENSE_TRUNCATED_CATEGORY_LABEL, axisLine: { show: false }, axisTick: { show: false } },
			series: [{
				type: 'bar',
				barWidth: 14,
				data: sorted.map(function (c) {
					return {
						value: c.total_calls,
						itemStyle: { borderRadius: [0, 4, 4, 0], color: latencyBarColor(c.avg_latency_ms) }
					};
				})
			}]
		};
	}

	/** Stacked bar: DLP + FinOps enforcement actions over time. */
	function securityTimelineOption(data) {
		var DLP = L('legend_dlp_redactions', 'DLP Redactions');
		var FINOPS = L('legend_finops_truncations', 'FinOps Truncations');
		var rows = data || [];
		var rawDates = rows.map(function (t) {
			return t.date;
		});
		var labels = rawDates.map(formatAxisLabel);

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var dlp = null, fin = null;

					p.forEach(function (x) {
						if (x.seriesName === DLP) {
							dlp = x;
						}

						if (x.seriesName === FINOPS) {
							fin = x;
						}
					});
					var header = formatTooltipHeader(rawDates[p[0].dataIndex] !== undefined ? rawDates[p[0].dataIndex] : p[0].axisValue);

					return '<div style="font-weight:600;margin-bottom:6px;color:#e4e4e7">' + header + '</div>'
						+ '<div style="display:flex;gap:16px">'
						+ '<div><span style="color:#71717a">' + DLP + '</span><br/><span style="color:#3b82f6;font-weight:700">' + ((dlp && dlp.value) || 0) + '</span></div>'
						+ '<div><span style="color:#71717a">' + FINOPS + '</span><br/><span style="color:#f59e0b;font-weight:700">' + ((fin && fin.value) || 0) + '</span></div>'
						+ '</div>';
				}
			}),
			legend: {
				show: true,
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8,
				itemGap: 12
			},
			grid: { top: 28, right: 8, bottom: 0, left: 8, containLabel: true },
			xAxis: { type: 'category', data: labels, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			series: [
				{
					name: DLP,
					type: 'bar',
					stack: 'enforcement',
					data: rows.map(function (t) {
						return t.dlp_redactions;
					}),
					barWidth: '50%',
					itemStyle: { color: 'rgba(59,130,246,0.72)' }
				},
				{
					name: FINOPS,
					type: 'bar',
					stack: 'enforcement',
					data: rows.map(function (t) {
						return t.finops_truncations;
					}),
					barWidth: '50%',
					itemStyle: { borderRadius: [3, 3, 0, 0], color: 'rgba(168,85,247,0.72)' }
				}
			]
		};
	}

	/** Dual area: cost vs savings over time. */
	function costSummaryOption(data) {
		var COST = L('legend_cost', 'Cost');
		var SAVINGS = L('legend_savings', 'Savings');
		var rows = data || [];
		var rawDates = rows.map(function (t) {
			return t.date;
		});
		var labels = rawDates.map(formatAxisLabel);

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				formatter: function (p) {
					var header = formatTooltipHeader(rawDates[p[0].dataIndex] !== undefined ? rawDates[p[0].dataIndex] : p[0].axisValue);
					var html = '<div style="font-weight:600;margin-bottom:4px">' + header + '</div>';

					p.forEach(function (s) {
						var color = s.seriesName === COST ? '#f97316' : '#10b981';

						html += '<div style="color:#71717a">' + s.seriesName + ': <span style="color:' + color + ';font-weight:700">$' + Number(s.value).toFixed(4) + '</span></div>';
					});

					return html;
				}
			}),
			legend: {
				show: true,
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8,
				itemGap: 12
			},
			grid: { top: 28, right: 8, bottom: 0, left: 8, containLabel: true },
			xAxis: { type: 'category', boundaryGap: false, data: labels, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: { type: 'value', axisLabel: axisLabel('${value}'), splitLine: DASHED_SPLIT_LINE },
			series: [
				{
					name: COST,
					type: 'line',
					smooth: 0.4,
					showSymbol: false,
					data: rows.map(function (t) {
						return +t.cost.toFixed(4);
					}),
					lineStyle: { width: 2, color: '#f97316' },
					areaStyle: { color: 'rgba(249,115,22,0.16)' }
				},
				{
					name: SAVINGS,
					type: 'line',
					smooth: 0.4,
					showSymbol: false,
					data: rows.map(function (t) {
						return +t.savings.toFixed(4);
					}),
					lineStyle: { width: 2, color: '#10b981' },
					areaStyle: { color: 'rgba(16,185,129,0.16)' }
				}
			]
		};
	}

	/** Bar + 3 rate lines: failures by requests / agent / upstream / vinkius. */
	function errorSummaryOption(data) {
		var REQUESTS = L('col_requests', 'Requests');
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var rows = data || [];
		var rawDates = rows.map(function (t) {
			return t.time_bucket;
		});
		var labels = rawDates.map(formatAxisLabel);
		var reqs = rows.map(function (t) {
			return t.requests;
		});

		function lineSeries(name, color, field) {
			return {
				name: name,
				type: 'line',
				yAxisIndex: 1,
				smooth: 0.4,
				showSymbol: false,
				data: rows.map(function (t) {
					return t[field] !== undefined && t[field] !== null ? t[field] : 0;
				}),
				lineStyle: { width: 2, color: color },
				itemStyle: { color: color }
			};
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				formatter: function (p) {
					var idx = p[0].dataIndex;
					var t = rows[idx];
					var header = formatTooltipHeader(rawDates[idx] !== undefined ? rawDates[idx] : p[0].axisValue);
					var html = '<div style="font-weight:600;margin-bottom:4px">' + header + '</div>';

					html += '<div style="color:#71717a">' + REQUESTS + ': <span style="color:#3b82f6;font-weight:700">' + ((t && t.requests) || 0).toLocaleString() + '</span></div>';

					if (t) {
						html += '<div style="color:#71717a">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), {
							agent: t.client_errors + ' · ' + t.client_error_rate + '%',
							upstream: t.upstream_errors + ' · ' + t.upstream_error_rate + '%',
							vinkius: t.vinkius_errors + ' · ' + t.vinkius_error_rate + '%'
						}) + '</div>';
					}

					return html;
				}
			}),
			legend: {
				show: true,
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8,
				itemGap: 12,
				data: [REQUESTS, AGENT, UPSTREAM, VINKIUS]
			},
			grid: { top: 28, right: 40, bottom: 0, left: 8, containLabel: true },
			xAxis: { type: 'category', data: labels, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: [
				{ type: 'value', name: REQUESTS, nameTextStyle: MUTED_AXIS_LABEL, axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
				{ type: 'value', name: '%', nameTextStyle: MUTED_AXIS_LABEL, axisLabel: axisLabel('{value}%'), splitLine: { show: false } }
			],
			series: [
				{
					name: REQUESTS,
					type: 'bar',
					yAxisIndex: 0,
					data: reqs,
					barWidth: '50%',
					itemStyle: { borderRadius: [2, 2, 0, 0], color: 'rgba(59,130,246,0.2)' }
				},
				lineSeries(AGENT, ERROR_SERIES.agent, 'client_error_rate'),
				lineSeries(UPSTREAM, ERROR_SERIES.upstream, 'upstream_error_rate'),
				lineSeries(VINKIUS, ERROR_SERIES.vinkius, 'vinkius_error_rate')
			]
		};
	}

	/** Scatter: latency × failure rate, bubble size = call volume. */
	function toolRankingOption(slowest, failing) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var map = {};

		(slowest || []).concat(failing || []).forEach(function (t) {
			map[t.tool_name] = t;
		});
		var tools = Object.keys(map).map(function (k) {
			return map[k];
		});

		if (!tools.length) {
			return emptyScatter();
		}

		var maxCalls = Math.max.apply(null, tools.map(function (t) {
			return t.calls;
		}).concat([1]));
		var maxLatency = Math.max.apply(null, tools.map(function (t) {
			return t.avg_latency_ms;
		}).concat([100]));
		var maxError = Math.max.apply(null, tools.map(function (t) {
			return t.error_rate;
		}).concat([1]));

		return {
			tooltip: tooltipShell({
				trigger: 'item',
				formatter: function (p) {
					var t = tools[p.dataIndex];

					if (!t) {
						return '';
					}

					var apiLine = t.avg_api_latency_ms != null
						? '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:4px">' + T(L('chart_api_overhead', 'API: {api} · Overhead: {overhead}'), { api: formatLatency(t.avg_api_latency_ms), overhead: formatLatency(Math.max(0, t.avg_latency_ms - t.avg_api_latency_ms)) }) + '</div>'
						: '';
					var breakdownLine = '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:4px">'
						+ T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), { agent: t.client_errors, upstream: t.upstream_errors, vinkius: t.vinkius_errors })
						+ '</div>';

					return '<div style="font-weight:600;margin-bottom:4px;font-family:monospace">' + t.tool_name + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-bottom:4px">' + t.server_name + '</div>'
						+ '<div style="display:flex;gap:12px">'
						+ '<div><span style="color:#71717a">' + L('col_avg_latency', 'Avg Latency') + '</span><br/><span style="color:#f59e0b;font-weight:700">' + formatLatency(t.avg_latency_ms) + '</span></div>'
						+ '<div><span style="color:#71717a">' + L('col_fail_rate', 'Fail Rate') + '</span><br/><span style="color:#ef4444;font-weight:700">' + t.error_rate + '%</span></div>'
						+ '<div><span style="color:#71717a">' + L('col_requests', 'Requests') + '</span><br/><span style="color:#3b82f6;font-weight:700">' + t.calls.toLocaleString() + '</span></div>'
						+ '</div>' + apiLine + breakdownLine;
				}
			}),
			grid: { top: 16, right: 16, bottom: 0, left: 8, containLabel: true },
			xAxis: {
				name: L('axis_avg_latency', 'Avg Latency'),
				nameLocation: 'center',
				nameGap: 28,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				min: 0,
				max: Math.ceil(maxLatency * 1.2),
				axisLabel: axisLabel(function (value) {
					return formatLatency(value);
				}),
				splitLine: DASHED_SPLIT_LINE
			},
			yAxis: {
				name: L('axis_failure_rate', 'Failure Rate (%)'),
				nameLocation: 'center',
				nameGap: 32,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				min: 0,
				max: Math.max(Math.ceil(maxError * 1.2), 1),
				axisLabel: axisLabel('{value}%'),
				splitLine: DASHED_SPLIT_LINE
			},
			series: [{
				type: 'scatter',
				data: tools.map(function (t) {
					return [t.avg_latency_ms, t.error_rate, t.calls];
				}),
				symbolSize: function (val) {
					return Math.max(12, Math.min(40, ((val[2] || 0) / maxCalls) * 40));
				},
				itemStyle: {
					color: function (params) {
						var e = (params.data[1] !== undefined && params.data[1] !== null) ? params.data[1] : 0;

						if (e === 0) {
							return 'rgba(16,185,129,0.7)';
						}

						if (e < 5) {
							return 'rgba(245,158,11,0.7)';
						}

						return 'rgba(239,68,68,0.7)';
					},
					borderColor: 'rgba(255,255,255,0.15)',
					borderWidth: 1
				}
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Agent Activity builders — port of clients-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	/** Stacked bar: connections per client, stacked by token. */
	function clientStackedOption(byClient) {
		var rows = byClient || [];

		if (!rows.length) {
			return emptyHorizontalBar();
		}

		var tokenNames = [];

		rows.forEach(function (c) {
			(c.tokens || []).forEach(function (t) {
				if (tokenNames.indexOf(t.token_name) < 0) {
					tokenNames.push(t.token_name);
				}
			});
		});

		return {
			tooltip: tooltipShell({ trigger: 'axis', axisPointer: { type: 'shadow' } }),
			legend: {
				data: tokenNames,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				bottom: 0,
				icon: 'circle',
				itemWidth: 8,
				itemHeight: 8,
				itemGap: 12
			},
			grid: { left: 8, right: 8, bottom: 32, top: 8, containLabel: true },
			xAxis: {
				type: 'category',
				data: rows.map(function (c) {
					return c.client_name;
				}),
				axisLabel: { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, interval: 0 },
				axisLine: FAINT_AXIS_LINE,
				axisTick: { show: false }
			},
			yAxis: { type: 'value', splitLine: DASHED_SPLIT_LINE, axisLabel: MUTED_AXIS_LABEL },
			series: tokenNames.map(function (tokenName, index) {
				return {
					name: tokenName,
					type: 'bar',
					stack: 'total',
					barWidth: '50%',
					emphasis: { focus: 'series' },
					itemStyle: {
						color: PALETTE[index % PALETTE.length],
						borderRadius: index === tokenNames.length - 1 ? [3, 3, 0, 0] : [0, 0, 0, 0]
					},
					data: rows.map(function (client) {
						var found = (client.tokens || []).filter(function (t) {
							return t.token_name === tokenName;
						})[0];

						return found ? found.count : 0;
					})
				};
			})
		};
	}

	/** Horizontal bar: capability requests per client, latency-coded. */
	function toolCallsBarOption(clients) {
		var rows = clients || [];
		var sorted = rows.length
			? rows.slice().sort(function (a, b) {
					return a.tool_calls - b.tool_calls;
				}).slice(-8)
			: [];

		if (!sorted.length) {
			return emptyHorizontalBar();
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var d = p[0];
					var c = sorted[d.dataIndex];

					if (!c) {
						return '';
					}

					return '<div style="font-weight:600;margin-bottom:4px">' + d.name + '</div>'
						+ '<div style="color:#71717a">' + L('col_capabilities', 'Capabilities') + ': <span style="color:#3b82f6;font-weight:700">' + d.value.toLocaleString() + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_avg_latency', 'Avg Latency') + ': <span style="color:' + latencyBarColor(c.avg_latency_ms) + ';font-weight:700">' + formatLatency(c.avg_latency_ms) + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_errors', 'Errors') + ': <span style="color:' + (c.errors > 0 ? '#ef4444' : '#71717a') + ';font-weight:700">' + c.errors + '</span></div>';
				}
			}),
			grid: { top: 4, right: 16, bottom: 0, left: 4, containLabel: true },
			xAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			yAxis: {
				type: 'category',
				data: sorted.map(function (c) {
					return c.name;
				}),
				axisLabel: { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, width: 100, overflow: 'truncate' },
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [{
				type: 'bar',
				barWidth: 14,
				data: sorted.map(function (c) {
					return {
						value: c.tool_calls,
						itemStyle: { borderRadius: [0, 4, 4, 0], color: latencyBarColor(c.avg_latency_ms) }
					};
				})
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Connector Traffic builders — port of connectors-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	var HOUR_LABELS = ['12a', '1a', '2a', '3a', '4a', '5a', '6a', '7a', '8a', '9a', '10a', '11a', '12p', '1p', '2p', '3p', '4p', '5p', '6p', '7p', '8p', '9p', '10p', '11p'];
	var DAY_LABELS = [
		L('day_sat', 'Saturday'), L('day_fri', 'Friday'), L('day_thu', 'Thursday'), L('day_wed', 'Wednesday'),
		L('day_tue', 'Tuesday'), L('day_mon', 'Monday'), L('day_sun', 'Sunday')
	];
	var DAY_TO_INDEX = { 7: 0, 6: 1, 5: 2, 4: 3, 3: 4, 2: 5, 1: 6 };
	var ROW_COLORS = ['#7388f4', '#c1e05d', '#6f6c85', '#ffa569', '#32c5e9', '#ffd93b', '#ff839b'];

	/** Punch card scatter: requests by day-of-week × hour (cartesian grid;
	 *  the vendored ECharts build does not support the singleAxis system). */
	function punchCardOption(volumeData) {
		var rows = (volumeData || []).filter(function (entry) {
			return DAY_TO_INDEX[entry.day] !== undefined;
		});

		if (!rows.length) {
			return emptyScatter();
		}

		var max = rows.reduce(function (m, entry) {
			return Math.max(m, Number(entry.value) || 0);
		}, 0) || 1;

		var data = rows.map(function (entry) {
			return {
				value: [entry.hour, DAY_TO_INDEX[entry.day], Number(entry.value) || 0],
				itemStyle: { color: ROW_COLORS[DAY_TO_INDEX[entry.day]], opacity: 0.85 }
			};
		});

		return {
			tooltip: tooltipShell({
				position: 'top',
				formatter: function (p) {
					return '<div style="font-weight:600;margin-bottom:2px">' + T(L('punch_card_at', '{day} at {hour}'), { day: DAY_LABELS[p.seriesIndex], hour: HOUR_LABELS[p.data[0]] }) + '</div>'
						+ '<div style="color:#71717a">' + L('col_requests', 'Requests') + ': <span style="color:' + ROW_COLORS[p.seriesIndex] + ';font-weight:700">' + p.data[1] + '</span></div>';
				}
			}),

			xAxis: {
				type: 'category',
				data: HOUR_LABELS,
				boundaryGap: false,
				axisLabel: { interval: 2, color: '#71717a', fontSize: AXIS_FONT_SIZE },
				axisLine: { lineStyle: { color: 'rgba(255,255,255,0.08)' } },
				axisTick: { show: false },
				splitLine: { show: true, lineStyle: { color: 'rgba(255,255,255,0.03)', type: 'dashed' } }
			},
			yAxis: {
				type: 'category',
				data: DAY_LABELS,
				axisLabel: DENSE_CATEGORY_LABEL,
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [{
				type: 'scatter',
				data: data,
				symbolSize: function (d) {
					return 6 + (Math.min(d[2], max) / max) * 24;
				}
			}]
		};
	}

	/** Horizontal bar: total requests by connector, latency-coded. */
	function connectorBarOption(connectors) {
		var rows = connectors || [];
		var sorted = rows.length
			? rows.slice().sort(function (a, b) {
					return a.total_calls - b.total_calls;
				}).slice(-10)
			: [];

		if (!sorted.length) {
			return emptyHorizontalBar();
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var d = p[0];
					var c = sorted[d.dataIndex];

					if (!c) {
						return '';
					}

					var apiLine = c.avg_api_latency_ms != null
						? '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + T(L('chart_api_vinkius', 'API: {api} · Vinkius: {vinkius}'), { api: formatLatency(c.avg_api_latency_ms), vinkius: formatLatency(Math.max(0, c.avg_latency_ms - c.avg_api_latency_ms)) }) + '</div>'
						: '';

					return '<div style="font-weight:600;margin-bottom:4px">' + d.name + '</div>'
						+ '<div style="color:#71717a">' + L('col_requests', 'Requests') + ': <span style="color:#3b82f6;font-weight:700">' + d.value.toLocaleString() + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_avg_latency', 'Avg Latency') + ': <span style="color:' + latencyBarColor(c.avg_latency_ms) + ';font-weight:700">' + formatLatency(c.avg_latency_ms) + '</span></div>' + apiLine;
				}
			}),
			grid: { top: 4, right: 16, bottom: 0, left: 4, containLabel: true },
			xAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			yAxis: {
				type: 'category',
				data: sorted.map(function (c) {
					return c.name;
				}),
				axisLabel: DENSE_TRUNCATED_CATEGORY_LABEL,
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [{
				type: 'bar',
				barWidth: 14,
				data: sorted.map(function (c) {
					return {
						value: c.total_calls,
						itemStyle: { borderRadius: [0, 4, 4, 0], color: latencyBarColor(c.avg_latency_ms) }
					};
				})
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | AI Spend builders — port of cost-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	/** Stacked-area trend: cost vs savings over time. */
	function costTrendOption(trend) {
		var COST = L('legend_cost', 'Cost');
		var SAVINGS = L('legend_savings', 'Savings');
		var rows = trend || [];
		var rawDates = rows.map(function (t) {
			return t.date;
		});
		var dates = rawDates.map(formatAxisLabel);

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				formatter: function (params) {
					var idx = (params[0] && params[0].dataIndex !== undefined) ? params[0].dataIndex : 0;
					var header = formatTooltipHeader(rawDates[idx] !== undefined ? rawDates[idx] : (params[0] ? params[0].name : ''));
					var lines = params.map(function (p) {
						return '<div style="color:#71717a">' + p.seriesName + ': <span style="color:' + p.color + ';font-weight:700">$' + Number(p.value).toFixed(4) + '</span></div>';
					}).join('');

					return '<div style="font-weight:600;margin-bottom:4px">' + header + '</div>' + lines;
				}
			}),
			legend: {
				data: [COST, SAVINGS],
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8
			},
			grid: { top: 28, left: 8, right: 8, bottom: 0, containLabel: true },
			xAxis: { type: 'category', boundaryGap: false, data: dates, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: { type: 'value', axisLabel: axisLabel('${value}'), splitLine: DASHED_SPLIT_LINE },
			series: [
				{
					name: COST,
					data: rows.map(function (t) {
						return t.cost;
					}),
					type: 'line',
					smooth: 0.3,
					showSymbol: false,
					stack: 'total',
					lineStyle: { width: 2, color: '#f59e0b' },
					itemStyle: { color: '#f59e0b' },
					areaStyle: { color: 'rgba(245,158,11,0.16)' }
				},
				{
					name: SAVINGS,
					data: rows.map(function (t) {
						return t.savings;
					}),
					type: 'line',
					smooth: 0.3,
					showSymbol: false,
					stack: 'total',
					lineStyle: { width: 2, color: '#34d399' },
					itemStyle: { color: '#34d399' },
					areaStyle: { color: 'rgba(52,211,153,0.16)' }
				}
			]
		};
	}

	/** Donut: cost distribution by connector (top 8). */
	function costPieOption(byServer) {
		var rows = byServer || [];

		if (!rows.length) {
			return emptyPie();
		}

		return {
			tooltip: tooltipShell({
				formatter: function (p) {
					return '<div style="font-weight:600;margin-bottom:4px">' + p.name + '</div>'
						+ '<div style="color:#71717a">' + L('legend_cost', 'Cost') + ': <span style="color:#f59e0b;font-weight:700">$' + p.value.toFixed(4) + '</span> (' + p.percent + '%)</div>';
				}
			}),
			series: [{
				type: 'pie',
				radius: ['40%', '70%'],
				center: ['50%', '50%'],
				avoidLabelOverlap: true,
				padAngle: 2,
				itemStyle: { borderRadius: 4 },
				label: { show: true, position: 'outside', color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, formatter: '{b}' },
				labelLine: { lineStyle: { color: 'rgba(255,255,255,0.15)' } },
				emphasis: { label: { show: true, fontSize: CHART_FONT_SIZE, fontWeight: 'bold', color: '#e4e4e7' } },
				data: rows.slice(0, 8).map(function (s, i) {
					return {
						name: s.server_name,
						value: s.cost,
						itemStyle: { color: PALETTE[i % PALETTE.length] }
					};
				})
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Security Posture builders — port of security-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	/** Stacked bar: DLP + FinOps enforcement actions over time. */
	function firewallOption(timeline) {
		var DLP = L('legend_dlp_redactions', 'DLP Redactions');
		var FINOPS = L('legend_finops_truncations', 'FinOps Truncations');
		var rows = timeline || [];
		var rawDates = rows.map(function (t) {
			return t.date;
		});
		var dates = rawDates.map(formatAxisLabel);

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (params) {
					var idx = (params[0] && params[0].dataIndex !== undefined) ? params[0].dataIndex : 0;
					var t = rows[idx];
					var saved = t ? formatBytes(t.bytes_saved) : '0 B';
					var header = formatTooltipHeader(rawDates[idx] !== undefined ? rawDates[idx] : (params[0] ? params[0].name : ''));
					var lines = params.map(function (p) {
						return '<div style="color:#71717a">' + p.seriesName + ': <span style="color:' + p.color + ';font-weight:700">' + fmt(p.value) + '</span></div>';
					}).join('');

					return '<div style="font-weight:600;margin-bottom:4px">' + header + '</div>' + lines
						+ '<div style="color:#71717a;margin-top:4px">' + L('data_protected', 'Data Protected') + ': <span style="color:#34d399;font-weight:700">' + saved + '</span></div>';
				}
			}),
			legend: {
				data: [DLP, FINOPS],
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8
			},
			grid: { top: 28, left: 8, right: 8, bottom: 0, containLabel: true },
			xAxis: { type: 'category', data: dates, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			series: [
				{
					name: DLP,
					type: 'bar',
					stack: 'enforcement',
					barWidth: '55%',
					data: rows.map(function (t) {
						return t.dlp_redactions;
					}),
					itemStyle: { color: 'rgba(59,130,246,0.72)' }
				},
				{
					name: FINOPS,
					type: 'bar',
					stack: 'enforcement',
					barWidth: '55%',
					data: rows.map(function (t) {
						return t.finops_truncations;
					}),
					itemStyle: { color: 'rgba(168,85,247,0.72)', borderRadius: [3, 3, 0, 0] }
				}
			]
		};
	}

	/** Donut compliance ring: % of active governance policies. */
	function complianceRingOption(coverage) {
		var c = coverage || {};
		var totalPossible = (c.total_servers || 0) * 3;
		var totalActive = (c.dlp_active || 0) + (c.finops_active || 0) + (c.approval_active || 0);
		var score = totalPossible > 0 ? Math.round((totalActive / totalPossible) * 100) : 0;
		var ringColor = score >= 90 ? '#34d399' : (score >= 60 ? '#fbbf24' : '#f87171');

		return {
			series: [{
				type: 'pie',
				radius: ['72%', '88%'],
				center: ['50%', '50%'],
				silent: true,
				label: { show: false },
				data: [
					{ value: score, itemStyle: { color: ringColor } },
					{ value: 100 - score, itemStyle: { color: 'rgba(255,255,255,0.06)' } }
				]
			}],
			graphic: [{
				type: 'text',
				left: 'center',
				top: 'center',
				style: {
					text: score + '%',
					fill: ringColor,
					fontSize: 28,
					fontWeight: 'bold',
					fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
				}
			}]
		};
	}

	/** Readiness ring (Compliance & Evidence): a raw percent, grade-coloured. */
	function readinessRingOption(percent, grade) {
		var score = Math.max(0, Math.min(100, percent || 0));
		var ringColor = grade === 'ready' ? '#10b981' : (grade === 'watch' ? '#f59e0b' : '#ef4444');

		return {
			series: [{
				type: 'pie',
				radius: ['72%', '88%'],
				center: ['50%', '50%'],
				silent: true,
				label: { show: false },
				data: [
					{ value: score, itemStyle: { color: ringColor } },
					{ value: 100 - score, itemStyle: { color: 'rgba(113,113,122,0.15)' } }
				]
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Request Failures builders — port of errors-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	/** Dual-axis: errors area + agent/upstream/vinkius rate lines. */
	function errorTimelineOption(timeline) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var rows = timeline || [];
		var rawDates = rows.map(function (t) {
			return t.time_bucket;
		});
		var labels = rawDates.map(formatAxisLabel);

		function lineSeries(name, color, field) {
			return {
				name: name,
				data: rows.map(function (t) {
					return t[field] !== undefined && t[field] !== null ? t[field] : 0;
				}),
				type: 'line',
				yAxisIndex: 1,
				smooth: 0.3,
				showSymbol: false,
				lineStyle: { width: 2, color: color },
				itemStyle: { color: color }
			};
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				formatter: function (params) {
					var idx = (params[0] && params[0].dataIndex !== undefined) ? params[0].dataIndex : 0;
					var t = rows[idx];

					if (!t) {
						return '';
					}

					var header = formatTooltipHeader(rawDates[idx] !== undefined ? rawDates[idx] : (params[0] ? params[0].name : ''));

					return '<div style="font-weight:600;margin-bottom:4px">' + header + '</div>'
						+ '<div style="color:#71717a">' + L('col_requests', 'Requests') + ': <span style="color:#e4e4e7;font-weight:700">' + fmt(t.requests) + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_errors', 'Errors') + ': <span style="color:#ef4444;font-weight:700">' + fmt(t.errors) + '</span></div>'
						+ '<div style="color:#71717a">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), {
							agent: t.client_errors + ' · ' + t.client_error_rate + '%',
							upstream: t.upstream_errors + ' · ' + t.upstream_error_rate + '%',
							vinkius: t.vinkius_errors + ' · ' + t.vinkius_error_rate + '%'
						}) + '</div>';
				}
			}),
			legend: {
				data: [L('legend_errors', 'Errors'), AGENT, UPSTREAM, VINKIUS],
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 12,
				itemHeight: 8
			},
			grid: { top: 28, left: 8, right: 8, bottom: 0, containLabel: true },
			xAxis: { type: 'category', boundaryGap: false, data: labels, axisLabel: DENSE_CATEGORY_LABEL, axisLine: FAINT_AXIS_LINE, axisTick: { show: false } },
			yAxis: [
				{ type: 'value', name: L('axis_errors', 'Errors'), nameTextStyle: MUTED_AXIS_LABEL, axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
				{ type: 'value', name: L('axis_rate_percent', 'Rate %'), nameTextStyle: MUTED_AXIS_LABEL, max: 100, axisLabel: axisLabel('{value}%'), splitLine: { show: false } }
			],
			series: [
				{
					name: L('legend_errors', 'Errors'),
					data: rows.map(function (t) {
						return t.errors;
					}),
					type: 'line',
					yAxisIndex: 0,
					smooth: 0.3,
					showSymbol: false,
					lineStyle: { width: 2, color: '#ef4444' },
					itemStyle: { color: '#ef4444' },
					areaStyle: { color: 'rgba(239,68,68,0.16)' }
				},
				lineSeries(AGENT, ERROR_SERIES.agent, 'client_error_rate'),
				lineSeries(UPSTREAM, ERROR_SERIES.upstream, 'upstream_error_rate'),
				lineSeries(VINKIUS, ERROR_SERIES.vinkius, 'vinkius_error_rate')
			]
		};
	}

	/** Donut: transport distribution. */
	var TRANSPORT_PALETTE = {
		'streamable-http': '#a855f7',
		streamable_http: '#a855f7',
		sse: '#3b82f6',
		stdio: '#f59e0b',
		websocket: '#10b981'
	};

	function normalizeTransportKey(name) {
		return String(name).toLowerCase().replace(/_/g, '-');
	}

	function transportOption(transport) {
		var rows = transport || [];

		if (!rows.length) {
			return emptyPie();
		}

		return {
			tooltip: tooltipShell({
				formatter: function (p) {
					return '<div style="font-weight:600;margin-bottom:2px">' + p.name + '</div>'
						+ '<div style="color:#71717a">' + T(L('chart_connections', '{count} connections ({pct}%)'), { count: p.value.toLocaleString(), pct: p.percent }) + '</div>';
				}
			}),
			series: [{
				type: 'pie',
				radius: ['40%', '70%'],
				center: ['50%', '50%'],
				padAngle: 3,
				itemStyle: { borderRadius: 4 },
				label: { show: true, position: 'outside', color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, formatter: '{b}\n{d}%' },
				labelLine: { lineStyle: { color: 'rgba(255,255,255,0.15)' } },
				emphasis: { label: { show: true, fontSize: CHART_FONT_SIZE, fontWeight: 'bold', color: '#e4e4e7' } },
				data: rows.map(function (t) {
					var key = String(t.transport).toLowerCase();
					var normalized = normalizeTransportKey(t.transport);

					return {
						name: normalized,
						value: t.count,
						itemStyle: {
							color: TRANSPORT_PALETTE[key] !== undefined ? TRANSPORT_PALETTE[key] : (TRANSPORT_PALETTE[normalized] !== undefined ? TRANSPORT_PALETTE[normalized] : '#71717a')
						}
					};
				})
			}]
		};
	}

	/** Horizontal stacked bar: errors by connector, by origin. */
	function serverErrorsOption(byServer) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var rows = byServer || [];
		var sorted = rows.length
			? rows.slice().sort(function (a, b) {
					return a.errors - b.errors;
				}).slice(-10)
			: [];

		if (!sorted.length) {
			return emptyHorizontalBar();
		}

		function stackSeries(name, color, field) {
			return {
				name: name,
				type: 'bar',
				stack: 'errors',
				data: sorted.map(function (s) {
					return s[field] !== undefined && s[field] !== null ? s[field] : 0;
				}),
				itemStyle: { color: color, borderRadius: 0 }
			};
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (p) {
					var d = p[0];
					var s = sorted[d.dataIndex];

					if (!s) {
						return '';
					}

					return '<div style="font-weight:600;margin-bottom:4px">' + d.name + '</div>'
						+ '<div style="color:#71717a">' + L('col_errors', 'Errors') + ': <span style="color:#ef4444;font-weight:700">' + fmt(s.errors) + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_requests', 'Requests') + ': <span style="color:#e4e4e7;font-weight:700">' + fmt(s.requests) + '</span></div>'
						+ '<div style="color:#71717a">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), { agent: s.client_errors, upstream: s.upstream_errors, vinkius: s.vinkius_errors }) + '</div>';
				}
			}),
			legend: {
				data: [AGENT, UPSTREAM, VINKIUS],
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 10,
				itemHeight: 8
			},
			grid: { top: 28, right: 16, bottom: 0, left: 4, containLabel: true },
			xAxis: { type: 'value', axisLabel: MUTED_AXIS_LABEL, splitLine: DASHED_SPLIT_LINE },
			yAxis: {
				type: 'category',
				data: sorted.map(function (s) {
					return s.server_name;
				}),
				axisLabel: DENSE_TRUNCATED_CATEGORY_LABEL,
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [
				stackSeries(AGENT, ERROR_SERIES.agent, 'client_errors'),
				stackSeries(UPSTREAM, ERROR_SERIES.upstream, 'upstream_errors'),
				stackSeries(VINKIUS, ERROR_SERIES.vinkius, 'vinkius_errors')
			]
		};
	}

	/** Per-MCP latency sparkline (Access Tokens rows): red above 500ms. */
	function tokenSparklineOption(points) {
		var values = (points || []).map(function (p) {
			return p.avg_latency_ms;
		});
		var isHigh = values.some(function (v) {
			return v > 500;
		});
		var color = isHigh ? '#f87171' : '#34d399';

		var option = sparklineOption({ values: values, color: color, baseAtZero: true, headroom: 1.15 });

		option.tooltip = tooltipShell({
			trigger: 'axis',
			appendToBody: true,
			padding: [6, 10],
			formatter: function (params) {
				var p = Array.isArray(params) ? params[0] : params;

				if (!p) {
					return '';
				}

				var point = points[p.dataIndex];

				if (!point) {
					return '';
				}

				var raw = point.bucket;
				var timeMatch = String(raw).match(/[\sT](\d{2}:\d{2})/);
				var label = timeMatch
					? timeMatch[1]
					: new Date(raw).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

				return '<div style="font-weight:600;margin-bottom:2px">' + label + '</div>'
					+ '<div style="color:#71717a">' + L('col_avg_latency', 'Avg Latency') + ': <span style="color:' + color + ';font-weight:700">' + formatLatency(p.value) + '</span></div>';
			}
		});

		return option;
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Capability Reliability builders — port of tool-performance-charts.ts
	 * ═══════════════════════════════════════════════════════════ */

	function truncName(name) {
		return name.length > 18 ? name.slice(0, 16) + '…' : name;
	}

	/** Horizontal bar: slowest capabilities (avg amber + max red overlay). */
	function slowestToolsOption(slowest) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var rows = slowest || [];
		var tools = rows.length ? rows.slice().reverse() : [];

		if (!tools.length) {
			return emptyHorizontalBar('ms');
		}

		var names = tools.map(function (t) {
			return truncName(t.tool_name);
		});

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (params) {
					var t = tools[(params[0] && params[0].dataIndex !== undefined) ? params[0].dataIndex : 0];

					if (!t) {
						return '';
					}

					var apiLine = t.avg_api_latency_ms != null
						? '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + T(L('chart_api_vinkius', 'API: {api} · Vinkius: {vinkius}'), { api: formatLatency(t.avg_api_latency_ms), vinkius: formatLatency(Math.max(0, t.avg_latency_ms - t.avg_api_latency_ms)) }) + '</div>'
						: '';

					return '<div style="font-weight:600;margin-bottom:4px;font-family:monospace">' + t.tool_name + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-bottom:4px">' + t.server_name + '</div>'
						+ '<div style="color:#71717a">' + L('col_avg', 'Avg') + ': <span style="color:#f59e0b;font-weight:700">' + formatLatency(t.avg_latency_ms) + '</span></div>'
						+ '<div style="color:#71717a">' + L('col_max', 'Max') + ': <span style="color:#ef4444;font-weight:700">' + formatLatency(t.max_latency_ms) + '</span></div>' + apiLine
						+ '<div style="color:#71717a">' + T(L('chart_requests_rate', 'Requests: {requests} · Rate: {rate}%'), { requests: fmt(t.calls), rate: t.error_rate }) + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), { agent: t.client_errors, upstream: t.upstream_errors, vinkius: t.vinkius_errors }) + '</div>';
				}
			}),
			grid: { top: 4, left: 4, right: 16, bottom: 0, containLabel: true },
			xAxis: {
				type: 'value',
				axisLabel: axisLabel(function (value) {
					return formatLatency(value);
				}),
				splitLine: DASHED_SPLIT_LINE
			},
			yAxis: {
				type: 'category',
				data: names,
				axisLabel: { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, width: 110, overflow: 'truncate' },
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [
				{
					name: L('legend_avg_latency', 'Avg Latency'),
					type: 'bar',
					data: tools.map(function (t) {
						return { value: t.avg_latency_ms, tool: t };
					}),
					barWidth: 12,
					itemStyle: { color: 'rgba(245,158,11,0.72)', borderRadius: [0, 4, 4, 0] }
				},
				{
					name: L('legend_max_latency', 'Max Latency'),
					type: 'bar',
					data: tools.map(function (t) {
						return { value: t.max_latency_ms, tool: t };
					}),
					barWidth: 6,
					barGap: '-60%',
					itemStyle: { color: 'rgba(239,68,68,0.25)', borderRadius: [0, 3, 3, 0] }
				}
			]
		};
	}

	/** Horizontal stacked bar: most failing capabilities by origin (% of calls). */
	function failingToolsOption(mostFailing) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var rows = mostFailing || [];
		var tools = rows.length ? rows.slice().reverse() : [];

		if (!tools.length) {
			return emptyHorizontalBar('%');
		}

		var names = tools.map(function (t) {
			return truncName(t.tool_name);
		});

		function stackSeries(name, color, field) {
			return {
				name: name,
				type: 'bar',
				stack: 'failures',
				data: tools.map(function (t) {
					var v = t[field] !== undefined && t[field] !== null ? t[field] : 0;
					var pct = t.calls > 0 ? +((v / t.calls) * 100).toFixed(2) : 0;

					return { value: pct, tool: t };
				}),
				itemStyle: { color: color, borderRadius: 0 }
			};
		}

		return {
			tooltip: tooltipShell({
				trigger: 'axis',
				axisPointer: { type: 'shadow' },
				formatter: function (params) {
					var t = tools[(params[0] && params[0].dataIndex !== undefined) ? params[0].dataIndex : 0];

					if (!t) {
						return '';
					}

					return '<div style="font-weight:600;margin-bottom:4px;font-family:monospace">' + t.tool_name + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-bottom:4px">' + t.server_name + '</div>'
						+ '<div style="color:#71717a">' + T(L('chart_errors_of_requests', 'Errors: {errors} of {requests} requests · {rate}%'), { errors: fmt(t.errors), requests: fmt(t.calls), rate: t.error_rate }) + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:2px">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), { agent: t.client_errors, upstream: t.upstream_errors, vinkius: t.vinkius_errors }) + '</div>';
				}
			}),
			legend: {
				data: [AGENT, UPSTREAM, VINKIUS],
				top: 0,
				right: 0,
				textStyle: { color: '#71717a', fontSize: CHART_FONT_SIZE },
				itemWidth: 10,
				itemHeight: 8
			},
			grid: { top: 28, left: 4, right: 16, bottom: 0, containLabel: true },
			xAxis: { type: 'value', max: 100, axisLabel: axisLabel('{value}%'), splitLine: DASHED_SPLIT_LINE },
			yAxis: {
				type: 'category',
				data: names,
				axisLabel: { color: '#a1a1aa', fontSize: AXIS_FONT_SIZE, width: 110, overflow: 'truncate' },
				axisLine: { show: false },
				axisTick: { show: false }
			},
			series: [
				stackSeries(AGENT, ERROR_SERIES.agent, 'client_errors'),
				stackSeries(UPSTREAM, ERROR_SERIES.upstream, 'upstream_errors'),
				stackSeries(VINKIUS, ERROR_SERIES.vinkius, 'vinkius_errors')
			]
		};
	}

	/** Scatter: latency × failure rate, bubble = request volume. */
	function toolScatterOption(slowest, mostFailing) {
		var AGENT = L('agent', 'Agent');
		var UPSTREAM = L('upstream', 'Upstream');
		var VINKIUS = L('vinkius', 'Vinkius');
		var all = {};

		(slowest || []).concat(mostFailing || []).forEach(function (t) {
			if (!all[t.tool_name]) {
				all[t.tool_name] = t;
			}
		});
		var tools = Object.keys(all).map(function (k) {
			return all[k];
		});

		if (!tools.length) {
			return emptyScatter();
		}

		var maxCalls = Math.max.apply(null, tools.map(function (t) {
			return t.calls;
		}).concat([1]));

		return {
			tooltip: tooltipShell({
				trigger: 'item',
				formatter: function (p) {
					var t = tools[p.dataIndex];

					if (!t) {
						return '';
					}

					return '<div style="font-weight:600;margin-bottom:4px;font-family:monospace">' + t.tool_name + '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-bottom:4px">' + t.server_name + '</div>'
						+ '<div style="display:flex;gap:12px">'
						+ '<div><span style="color:#71717a">' + L('col_avg_latency', 'Avg Latency') + '</span><br/><span style="color:#f59e0b;font-weight:700">' + formatLatency(t.avg_latency_ms) + '</span></div>'
						+ '<div><span style="color:#71717a">' + L('col_fail_rate', 'Fail Rate') + '</span><br/><span style="color:#ef4444;font-weight:700">' + t.error_rate + '%</span></div>'
						+ '<div><span style="color:#71717a">' + L('col_requests', 'Requests') + '</span><br/><span style="color:#3b82f6;font-weight:700">' + t.calls.toLocaleString() + '</span></div>'
						+ '</div>'
						+ '<div style="color:#71717a;font-size:' + CHART_FONT_SIZE + 'px;margin-top:4px">' + T(L('chart_agent_upstream_vinkius', 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}'), { agent: t.client_errors, upstream: t.upstream_errors, vinkius: t.vinkius_errors }) + '</div>';
				}
			}),
			grid: { top: 24, right: 16, bottom: 0, left: 8, containLabel: true },
			xAxis: {
				name: L('axis_avg_latency', 'Avg Latency'),
				nameLocation: 'center',
				nameGap: 28,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				axisLabel: axisLabel(function (value) {
					return formatLatency(value);
				}),
				splitLine: DASHED_SPLIT_LINE
			},
			yAxis: {
				name: L('axis_failure_rate', 'Failure Rate (%)'),
				nameLocation: 'center',
				nameGap: 32,
				nameTextStyle: CHART_NAME_TEXT_STYLE,
				type: 'value',
				axisLabel: axisLabel('{value}%'),
				splitLine: DASHED_SPLIT_LINE
			},
			series: [{
				type: 'scatter',
				data: tools.map(function (t) {
					return { value: [t.avg_latency_ms, t.error_rate, t.calls], tool: t };
				}),
				symbolSize: function (val) {
					var v = Array.isArray(val) ? val : [0, 0, Number(val) || 0];

					return Math.max(8, Math.min(40, ((v[2] || 0) / maxCalls) * 40));
				},
				itemStyle: {
					color: function (params) {
						var e = (params.value && params.value[1] !== undefined && params.value[1] !== null) ? params.value[1] : 0;

						if (e === 0) {
							return 'rgba(16,185,129,0.7)';
						}

						if (e < 5) {
							return 'rgba(245,158,11,0.7)';
						}

						return 'rgba(239,68,68,0.7)';
					},
					borderColor: 'rgba(255,255,255,0.15)',
					borderWidth: 1
				}
			}]
		};
	}

	/* ═══════════════════════════════════════════════════════════════
	 | Chart manager: init / resize / expand / dispose
	 | (equivalent of the EChartsHost + ChartExpandModal pair)
	 * ═══════════════════════════════════════════════════════════ */

	var instances = {};

	// Keyed registry (works even where Map is available; plain object
	// fallback keeps the module dependency-free).
	var registry = {};
	var registrySeq = 0;

	/**
	 * Render an option into a container element. Replaces any chart
	 * already on that element. Returns the ECharts instance.
	 */
	function render(el, option) {
		if (!ECharts || !el) {
			return null;
		}

		if (registry[el.__vcChartId]) {
			var prev = instances[registry[el.__vcChartId]];

			if (prev) {
				prev.dispose();
				delete instances[registry[el.__vcChartId]];
				delete registry[el.__vcChartId];
			}
		}

		var id = 'vcchart-' + (registrySeq++);

		instances[id] = ECharts.init(el);
		instances[id].setOption(option);
		registry[el.__vcChartId || (el.__vcChartId = 'vcel-' + registrySeq)] = id;

		// Section renderers build their markup offscreen and insert it
		// afterwards, so the container can still have zero width here.
		// Re-fit the chart once it is actually laid out.
		if (!el.clientWidth) {
			var tries = 0;

			var fit = function () {
				if (!instances[id] || ++tries > 60) {
					return;
				}

				if (el.clientWidth) {
					instances[id].resize();
				} else {
					setTimeout(fit, 50);
				}
			};

			setTimeout(fit, 50);
		}

		return instances[id];
	}

	/** Dispose the chart on a container (e.g. before it leaves the DOM). */
	function dispose(el) {
		if (!el || !el.__vcChartId) {
			return;
		}

		var id = registry[el.__vcChartId];

		if (id && instances[id]) {
			instances[id].dispose();
			delete instances[id];
			delete registry[el.__vcChartId];
		}
	}

	/** Dispose every chart owned by the module (page teardown). */
	function disposeAll() {
		Object.keys(instances).forEach(function (id) {
			instances[id].dispose();
			delete instances[id];
		});

		registry = {};
	}

	var resizeTimer = null;

	function resizeAll() {
		Object.keys(instances).forEach(function (id) {
			instances[id].resize();
		});
	}

	if (typeof window !== 'undefined' && window.addEventListener) {
		window.addEventListener('resize', function () {
			if (resizeTimer) {
				clearTimeout(resizeTimer);
			}

			resizeTimer = setTimeout(resizeAll, 120);
		});
	}

	/* ── Chart card: bordered card, title + subtitle, expand button. ── */

	function makeEl(tag, className, text) {
		var n = document.createElement(tag);

		if (className) {
			n.className = className;
		}

		if (text !== undefined && text !== null) {
			n.textContent = text;
		}

		return n;
	}

	/**
	 * One chart card (port of ChartCard.tsx): header (title + subtitle,
	 * expand button on the right), ECharts body at the given height
	 * (default 160px, like the cloud). `onExpand` re-renders the same
	 * option in a larger overlay (port of ChartExpandModal).
	 */
	function chartCard(option, title, subtitle, height, detailsHref, detailsLabel) {
		var card = makeEl('div', 'vc-chart-card');


		var head = makeEl('div', 'vc-chart-head');
		var headText = makeEl('div');

		headText.appendChild(makeEl('h3', 'vc-chart-title', title));

		if (subtitle) {
			headText.appendChild(makeEl('p', 'vc-chart-subtitle', subtitle));
		}

		head.appendChild(headText);

		var actions = makeEl('div', 'vc-chart-actions');

		if (detailsHref) {
			var details = makeEl('a', 'vc-chart-details', detailsLabel || L('details', 'Details'));

			details.href = detailsHref;
			details.target = '_blank';
			details.rel = 'noopener';
			actions.appendChild(details);
		}

		var expand = makeEl('button', 'vc-chart-expand');

		expand.type = 'button';
		expand.setAttribute('aria-label', L('expand_chart', 'Expand chart'));
		expand.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"></path><path d="M9 21H3v-6"></path><path d="M21 3l-7 7"></path><path d="M3 21l7-7"></path></svg>';
		expand.addEventListener('click', function () {
			expandChart(option, title, subtitle);
		});
		actions.appendChild(expand);
		head.appendChild(actions);
		card.appendChild(head);

		var body = makeEl('div', 'vc-chart-body');

		body.style.height = (height || 160) + 'px';
		render(body, option);
		card.appendChild(body);
		return card;
	}

	/** Expand overlay (port of ChartExpandModal): 60vh re-render of the option. */
	var overlay = null;

	function closeOverlay() {
		if (!overlay) {
			return;
		}

		var chartEl = overlay.querySelector('.vc-chart-overlay-body');

		if (chartEl) {
			dispose(chartEl);
		}

		overlay.remove();
		overlay = null;
	}

	function expandChart(option, title, subtitle) {
		if (overlay) {
			closeOverlay();
		}

		var wrap = makeEl('div', 'vc-chart-overlay');

		wrap.addEventListener('click', function (e) {
			if (e.target === wrap) {
				closeOverlay();
			}
		});
		document.addEventListener('keydown', function esc(e) {
			if (e.key === 'Escape') {
				document.removeEventListener('keydown', esc);
				closeOverlay();
			}
		});

		var box = makeEl('div', 'vc-chart-overlay-box');
		var head = makeEl('div', 'vc-chart-head');
		var headText = makeEl('div');

		headText.appendChild(makeEl('h3', 'vc-chart-title', title || L('chart', 'Chart')));

		if (subtitle) {
			headText.appendChild(makeEl('p', 'vc-chart-subtitle', subtitle));
		}

		var closeBtn = makeEl('button', 'vc-chart-overlay-close');

		closeBtn.type = 'button';
		closeBtn.setAttribute('aria-label', L('close', 'Close'));
		closeBtn.textContent = '×';
		closeBtn.addEventListener('click', closeOverlay);
		head.appendChild(headText);
		head.appendChild(closeBtn);

		var body = makeEl('div', 'vc-chart-overlay-body');
		box.appendChild(head);
		box.appendChild(body);
		wrap.appendChild(box);
		document.body.appendChild(wrap);
		overlay = wrap;
		render(body, option);
	}

	/* ── Public API ──────────────────────────────────────────────── */

	window.VcCharts = {
		available: Boolean(ECharts),

		/* formatters + latency */
		formatLatency: formatLatency,
		latencyTone: latencyTone,
		latencyTextClass: latencyTextClass,
		latencyBarClass: latencyBarClass,
		latencyBarWidth: latencyBarWidth,
		latencyBarColor: latencyBarColor,
		latencyToneLabel: latencyToneLabel,
		overheadMs: overheadMs,
		latencyTooltip: latencyTooltip,
		fmt: fmt,
		fmtInt: fmtInt,
		usd: usd,
		formatBytes: formatBytes,
		formatDuration: formatDuration,
		timeAgo: timeAgo,
		errorRateClass: errorRateClass,
		statusColorClass: statusColorClass,
		trimmedSharePercent: trimmedSharePercent,

		/* options builders (port of the cloud chart files) */
		heatmapOption: heatmapOption,
		requestVolumeOption: requestVolumeOption,
		topConnectorsOption: topConnectorsOption,
		securityTimelineOption: securityTimelineOption,
		costSummaryOption: costSummaryOption,
		errorSummaryOption: errorSummaryOption,
		toolRankingOption: toolRankingOption,
		clientStackedOption: clientStackedOption,
		toolCallsBarOption: toolCallsBarOption,
		punchCardOption: punchCardOption,
		connectorBarOption: connectorBarOption,
		costTrendOption: costTrendOption,
		costPieOption: costPieOption,
		firewallOption: firewallOption,
			complianceRingOption: complianceRingOption,
		readinessRingOption: readinessRingOption,
		errorTimelineOption: errorTimelineOption,
		transportOption: transportOption,
		serverErrorsOption: serverErrorsOption,
		tokenSparklineOption: tokenSparklineOption,
		slowestToolsOption: slowestToolsOption,
		failingToolsOption: failingToolsOption,
		toolScatterOption: toolScatterOption,
		sparklineOption: sparklineOption,

		/* chart manager + card shell */
		render: render,
		dispose: dispose,
		disposeAll: disposeAll,
		chartCard: chartCard,
		expandChart: expandChart,

		/* constants shared with the section renderers */
		palette: PALETTE,
		errorSeries: ERROR_SERIES
	};
})();
