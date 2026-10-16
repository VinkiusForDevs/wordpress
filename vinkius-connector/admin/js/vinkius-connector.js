/**
 * Vinkius AI Governance panel.
 *
 * Observability + response. Data is read live from the Vinkius API;
 * management deep-links to the Vinkius cloud (VCConfig.links).
 *
 * @license GPLv2 or later
 */
(function () {
	'use strict';

	if (typeof VCConfig === 'undefined') {
		return;
	}

	var wrap = document.querySelector('.vc-wrap');

	if (!wrap) {
		return;
	}

	function rest(path, options) {
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
		return wrap.dataset.status === 'connected';
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

	function L(key, fallback) {
		var labels = (VCConfig && VCConfig.labels) || {};

		return labels[key] || fallback;
	}

	function setKpi(id, value) {
		var node = document.getElementById(id);

		if (node) {
			node.textContent = value;
		}
	}

	function renderOperations(data) {
		var k = data.kpis || {};

		setKpi('vc-kpi-agents', k.active_agents != null ? k.active_agents : 0);
		setKpi('vc-kpi-executions', k.executions_today != null ? k.executions_today : 0);
		setKpi('vc-kpi-blocked', k.blocked_events != null ? k.blocked_events : 0);
		setKpi('vc-kpi-connectors', k.active_connectors != null ? k.active_connectors : 0);

		var live = document.getElementById('vc-live');

		if (live) {
			live.innerHTML = '';
			var rows = data.live || [];

			if (!rows.length) {
				live.appendChild(el('li', 'vc-feed-empty', L('live_empty', 'No activity in this period.')));
			}

			rows.forEach(function (ev) {
				var code = parseInt(ev.status_code, 10) || 0;
				var row = el('li', 'vc-feed-row');

				row.appendChild(el('span', 'vc-feed-time', ev.created_at || ''));
				row.appendChild(el('span', 'vc-feed-tool', (ev.server_name || '') + ' \u00B7 ' + (ev.tool_name || '')));
				row.appendChild(el('span', 'pill pill-' + (code >= 400 ? 'err' : 'ok'), String(code || '\u00B7')));

				var meta = [];

				if (ev.token_name) {
					meta.push(ev.token_name);
				}

				if (ev.response_time_ms != null) {
					meta.push(ev.response_time_ms + ' ms');
				}

				if (ev.dlp_redactions > 0) {
					meta.push('DLP ' + ev.dlp_redactions);
				}

				row.appendChild(el('span', 'vc-feed-meta', meta.join(' \u00B7 ')));
				live.appendChild(row);
			});
		}

		var usage = data.usage || {};
	var usageCard = document.getElementById('vc-usage-card');
	if (usageCard) {
		usageCard.hidden = false;
		var plan = String(data.plan || 'free');
		document.getElementById('vc-usage-plan').textContent = plan.charAt(0).toUpperCase() + plan.slice(1);
		var used = usage.used || 0;
		var limit = usage.effective_limit != null ? usage.effective_limit : (usage.limit || 0);
		var fill = document.getElementById('vc-usage-fill');
		fill.style.width = limit > 0 ? Math.min(100, Math.round((used / limit) * 100)) + '%' : '0%';
		var labels = (VCConfig && VCConfig.labels) || {};
		var txt = (labels.usage || 'Used %1$s of %2$s this cycle.')
			.replace('%1$s', String(used)).replace('%2$s', String(limit));
		document.getElementById('vc-usage-text').textContent = txt;
		var showCta = data.free === true || usage.overage_blocked === true;
		document.getElementById('vc-usage-cta').hidden = !showCta;
	}

	var circuit = data.circuit || {};
		var pill = document.getElementById('vc-circuit-pill');

		if (pill) {
			pill.hidden = !circuit.is_tripped;
		}

		document.querySelectorAll('.vc-circuit-reset').forEach(function (b) {
			b.hidden = !circuit.is_tripped;
		});

		var halts = document.getElementById('vc-connector-halts');

		if (halts) {
			halts.innerHTML = '';

			(data.mcps || []).forEach(function (mcp) {
				if ((mcp.total_requests || 0) > 0) {
					var row = el('div', 'vc-connector-halt-row');
					var btn = el('button', 'button vc-halt-connector', L('halt_connector', 'Halt this connector'));

					btn.dataset.serverId = mcp.id;
					btn.dataset.name = mcp.name;
					row.appendChild(el('span', null, mcp.name));
					row.appendChild(btn);
					halts.appendChild(row);
				}
			});
		}
	}


	function loadOperations() {
		if (!connected()) { return; }
		rest('panel/operations').then(function (res) {
			if (res.state === 'ok') {
				renderOperations(res.data);
			} else {
				var live = document.getElementById('vc-live');
				var labels = (VCConfig && VCConfig.labels) || {};
				var msg = labels.session_expired || 'Session expired. Log in again from Settings to see live data.';
				if (live) {
					live.innerHTML = '';
					var li = document.createElement('li');
					li.className = 'vc-feed-empty';
					li.textContent = msg;
					live.appendChild(li);
				}
			}
		}).catch(function () {});
	}

	// The dashboard is now the governance workbench (vc-governance.js owns it).
	// Only run the legacy operations poll when its targets exist.
	if (!document.getElementById('vc-gov-body')) {
		loadOperations();
		setInterval(loadOperations, 30000);
	}

	var fleetRows = document.getElementById('vc-fleet-rows');

	if (fleetRows && connected()) {
		rest('panel/agents').then(function (res) {
			var mcps = (res.data || {}).mcps || [];

			fleetRows.innerHTML = '';

			if (!mcps.length) {
				var tr0 = el('tr');

				tr0.appendChild(el('td', 'vc-muted', L('fleet_empty', 'No connectors with activity.')));
				fleetRows.appendChild(tr0);
				return;
			}

			mcps.forEach(function (m) {
				var tr = el('tr');

				tr.appendChild(el('td', null, m.name || m.id));
				tr.appendChild(el('td', null, String(m.token_count || 0)));
				tr.appendChild(el('td', null, String(m.total_requests || 0)));
				tr.appendChild(el('td', null, (m.avg_latency_ms != null ? m.avg_latency_ms : '\u2014') + ' ms'));
				tr.appendChild(el('td', null, m.status || '\u2014'));
				fleetRows.appendChild(tr);
			});
		}).catch(function () {});
	}

	if (document.getElementById('vc-incident-errors') && connected()) {
		rest('panel/incidents').then(function (res) {
			var d = res.data || {};
			var ek = d.errors_kpi || {};
			var sk = d.security_kpi || {};
			var circuit = d.circuit || {};

			setKpi('vc-incident-errors', ek.total_errors != null ? ek.total_errors : (ek.errors != null ? ek.errors : '\u2014'));
			setKpi('vc-incident-dlp', sk.dlp_events != null ? sk.dlp_events : '\u2014');
			setKpi('vc-incident-circuit', circuit.is_tripped ? L('circuit_tripped', 'Tripped') : L('circuit_open', 'Open'));

			var pill = document.getElementById('vc-incident-circuit-pill');

			if (pill) {
				pill.hidden = !circuit.is_tripped;
			}

			document.querySelectorAll('.vc-circuit-reset').forEach(function (b) {
				b.hidden = !circuit.is_tripped;
			});

			var brief = document.getElementById('vc-briefing');

			if (brief && d.briefing) {
				brief.textContent = typeof d.briefing === 'string' ? d.briefing : JSON.stringify(d.briefing);
			}
		}).catch(function () {});
	}

	var picker = document.getElementById('vc-evidence-picker');

	if (picker && connected()) {
		rest('panel/connectors').then(function (res) {
			var servers = res.data || [];

			if (servers && servers.servers) {
				servers = servers.servers;
			}

			if (!Array.isArray(servers)) {
				return;
			}

			servers.forEach(function (s) {
				var opt = el('option', null, s.name || s.id);

				opt.value = s.id || s.server_id;
				picker.appendChild(opt);
			});
		}).catch(function () {});

		picker.addEventListener('change', function () {
			var sid = picker.value;

			if (!sid) {
				return;
			}

			rest('panel/evidence?server_id=' + encodeURIComponent(sid)).then(function (res) {
				var rows = (res.data || {}).logs || [];
				var table = document.getElementById('vc-evidence');
				var tbody = document.getElementById('vc-evidence-rows');

				if (table) {
					table.hidden = false;
				}

				tbody.innerHTML = '';

				if (!rows.length) {
					var tr = el('tr');
					var td = el('td', 'vc-muted', L('evidence_empty', 'No executions recorded for this connector.'));

					td.colSpan = 6;
					tr.appendChild(td);
					tbody.appendChild(tr);
					return;
				}

				rows.forEach(function (r) {
					var tr = el('tr');

					tr.appendChild(el('td', null, r.created_at || ''));
					tr.appendChild(el('td', null, r.tool_name || ''));
					tr.appendChild(el('td', null, r.token_name || ''));
					tr.appendChild(el('td', null, String(r.status_code != null ? r.status_code : '')));
					tr.appendChild(el('td', null, r.response_time_ms != null ? r.response_time_ms + ' ms' : ''));
					tr.appendChild(el('td', null, r.dlp_redactions > 0 ? String(r.dlp_redactions) : ''));
					tbody.appendChild(tr);
				});
			}).catch(function () {});
		});
	}

	document.querySelectorAll('.vc-halt-all').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm(L('confirm_halt_all', 'Halt all agents on this site?'))) {
				return;
			}

			rest('panel/halt', { method: 'POST', body: JSON.stringify({ scope: 'all', reason: 'operator' }) })
				.then(function () {
					window.location.reload();
				});
		});
	});

	var haltsRoot = document.getElementById('vc-connector-halts');

	if (haltsRoot) {
		haltsRoot.addEventListener('click', function (e) {
			var btn = e.target.closest ? e.target.closest('.vc-halt-connector') : null;

			if (!btn || !btn.dataset.serverId) {
				return;
			}

			rest('panel/halt', {
				method: 'POST',
				body: JSON.stringify({ scope: 'connector', server_id: btn.dataset.serverId, reason: btn.dataset.name })
			}).then(function () {
				window.location.reload();
			});
		});
	}

	var resume = document.querySelector('.vc-halt-resume');

	if (resume) {
		resume.addEventListener('click', function () {
			rest('panel/resume', { method: 'POST', body: '{}' }).then(function () {
				window.location.reload();
			});
		});
	}

	document.querySelectorAll('.vc-circuit-reset').forEach(function (btn) {
		btn.addEventListener('click', function () {
			rest('panel/circuit-reset', { method: 'POST', body: '{}' }).then(function () {
				window.location.reload();
			});
		});
	});

	var kill = document.querySelector('.vc-kill-switch');

	if (kill) {
		kill.addEventListener('change', function () {
			rest(kill.checked ? 'panel/halt' : 'panel/resume', { method: 'POST', body: JSON.stringify({ scope: 'local' }) })
				.then(function () {
					window.location.reload();
				});
		});
	}

	function deviceRoots() {
		return Array.prototype.slice.call(document.querySelectorAll('#vc-device'));
	}

	function showDeviceError(msg) {
		deviceRoots().forEach(function (root) {
			if (root.querySelector('.vc-device-error')) {
				return;
			}

			root.appendChild(el('p', 'vc-device-error', msg));
		});
	}

	var devicePolling = false;

	function pollDevice() {
		rest('auth/device/poll', { method: 'POST', body: '{}' }).then(function (result) {
			if (result.state === 'ok' && result.data && result.data.status === 'success') {
				window.location.reload();
				return;
			}

			if (result.state === 'error' && (result.code === 'vc_device_expired' || result.code === 'vc_device_no_session')) {
				devicePolling = false;
				document.querySelectorAll('.vc-device-waiting').forEach(function (n) {
					n.textContent = L('device_expired', 'Session expired. Start again.');
				});
				return;
			}

			setTimeout(pollDevice, 5000);
		}).catch(function () {
			setTimeout(pollDevice, 5000);
		});
	}

	function startDevicePolling() {
		if (devicePolling) {
			return;
		}

		devicePolling = true;
		pollDevice();
	}

	function renderDevicePending(bundle) {
		deviceRoots().forEach(function (root) {
			root.querySelectorAll('.vc-device-box, .vc-device-error').forEach(function (n) {
				n.remove();
			});

			var box = el('div', 'vc-device-box');

			box.appendChild(el('p', null, L('device_code', 'Device code')));
			box.appendChild(el('code', 'vc-device-code', bundle.user_code || '\u2026'));

			var p = el('p', null);
			var link = el('a', 'button', L('device_open', 'Open approval page'));

			link.href = bundle.verification_url || (VCConfig.links && VCConfig.links.device ? VCConfig.links.device : '#');
			link.target = '_blank';
			link.rel = 'noopener';
			p.appendChild(link);
			p.appendChild(el('span', 'vc-muted vc-device-waiting', L('device_waiting', 'Waiting for approval\u2026')));
			box.appendChild(p);
			root.appendChild(box);
		});
	}

	document.querySelectorAll('.vc-device-connect').forEach(function (btn) {
		btn.addEventListener('click', function () {
			btn.disabled = true;

			rest('auth/device/init', { method: 'POST', body: '{}' }).then(function (result) {
				if (result.state === 'ok' && result.data) {
					renderDevicePending(result.data);
					startDevicePolling();
					return;
				}

				showDeviceError(L('device_error', 'Could not start device login. Please try again.'));
				btn.disabled = false;
			}).catch(function () {
				showDeviceError(L('device_error', 'Could not start device login. Please try again.'));
				btn.disabled = false;
			});
		});
	});

	var logout = document.querySelector('.vc-device-logout');

	if (logout) {
		logout.addEventListener('click', function () {
			rest('auth/logout', { method: 'POST', body: '{}' }).then(function () {
				window.location.reload();
			});
		});
	}

	deviceRoots().forEach(function (root) {
		if (root.dataset.pending === '1') {
			startDevicePolling();
		}
	});

	var secretInput = document.getElementById('vc-site-secret');

	if (secretInput) {
			document.querySelectorAll('.vc-secret-copy').forEach(function (btn) {
				btn.addEventListener('click', function () {
					secretInput.select();
					document.execCommand('copy');
					btn.textContent = L('secret_copied', 'Copied');
					setTimeout(function () {
						btn.textContent = L('secret_copy', 'Copy');
					}, 2000);
				});
			});

		document.querySelectorAll('.vc-secret-regen').forEach(function (btn) {
			btn.addEventListener('click', function () {
				rest('panel/regen-secret', { method: 'POST', body: '{}' }).then(function (res) {
					secretInput.value = res.secret;
				});
			});
		});
	}
})();
