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

	if (document.getElementById('vc-incident-errors') && connected()) {
		rest('panel/incidents').then(function (res) {
			if (!res || res.state !== 'ok') {
				renderQuarantine(null);
				return;
			}

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

			renderQuarantine(d.quarantine || []);
		}).catch(function () {
			renderQuarantine(null);
		});

	function humanTtl(seconds) {
		seconds = Number(seconds) || 0;

		if (seconds <= 0) {
			return L('tb_expiry_soon', 'expiring now');
		}

		var m = Math.round(seconds / 60);

		return m <= 1 ? L('tb_expiry_min', '< 1 min') : L('tb_expiry_mins', '{n} min').replace('{n}', m);
	}

	function renderQuarantine(rows) {
		var box = document.getElementById('vc-incident-quarantine');

		if (!box) {
			return;
		}

		box.innerHTML = '';

		// null = state not readable (not connected / upstream failure);
		// [] = read fine, nothing quarantined.
		if (rows === null) {
			box.appendChild(el('p', 'description', L('quarantine_na', 'Quarantine state is unavailable right now.')));
			return;
		}

		rows = Array.isArray(rows) ? rows : [];
		var quarantined = rows.filter(function (r) { return r.quarantined; });

		if (!quarantined.length) {
			box.appendChild(el('p', 'description', L('quarantine_none', 'No connector session is quarantined. SOAR kills and their reasons show up here.')));
			return;
		}

		quarantined.forEach(function (row) {
			var item = el('div', 'vc-quar-item');
			var main = el('div', 'vc-quar-main');
			var line = el('div', 'vc-quar-line');
			var name = el('span', 'vc-quar-name', row.name || String(row.id || '').slice(0, 8));

			name.title = row.name || '';
			line.appendChild(name);
			item.appendChild(main);
			main.appendChild(line);

			if (row.reason) {
				main.appendChild(el('p', 'vc-quar-reason', '"' + row.reason + '"'));
			}

			var side = el('div', 'vc-quar-side');

			side.appendChild(el('span', 'vc-pill vc-pill-warn', L('quarantine_expires', 'expires in {ttl}').replace('{ttl}', humanTtl(row.ttl_seconds))));

			var actions = el('div', 'vc-incident-q-actions');

			var lift = document.createElement('button');

			lift.type = 'button';
			lift.className = 'button button-small';
			lift.textContent = L('quarantine_lift', 'Lift');
			lift.addEventListener('click', function () {
				rest('panel/connector', { method: 'POST', body: JSON.stringify({ action: 'lift', server_id: row.id }) })
					.then(function () { window.location.reload(); });
			});
			actions.appendChild(lift);

			var extend = document.createElement('button');

			extend.type = 'button';
			extend.className = 'button button-small';
			extend.textContent = L('quarantine_extend', 'Extend +1h');
			extend.addEventListener('click', function () {
				rest('panel/connector', { method: 'POST', body: JSON.stringify({ action: 'extend', server_id: row.id }) })
					.then(function () { window.location.reload(); });
			});
			actions.appendChild(extend);
			side.appendChild(actions);
			item.appendChild(side);
			box.appendChild(item);
		});
	}

	function escHtml(s) {
		return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (ch) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
		});
	}

	/* Canonical loading line (same markup family as the governance file):
	 * flexible spinner + caption, min-height keeps layout steady. */
	function loaderLine(text) {
		var p = el('p', 'vc-loading');

		p.appendChild(el('span', 'spinner is-active'));
		p.firstChild.setAttribute('aria-hidden', 'true');
		p.appendChild(el('span', null, text || L('loading', 'Loading…')));
		return p;
	}

	function renderAuditFeed(scope) {
		var feed = document.getElementById('vc-audit-feed');

		if (!feed) {
			return;
		}

		feed.innerHTML = '';
		feed.appendChild(loaderLine(L('audit_loading', 'Reading the trail…')));

		rest('panel/audit?scope=' + encodeURIComponent(scope)).then(function (res) {
			feed.innerHTML = '';

			if (!res || res.state !== 'ok') {
				feed.appendChild(el('p', 'description', L('audit_unavailable', 'This trail is unavailable right now.')));
				return;
			}

			var d = res.data || {};
			var rawRows = d.data || d;
			var rows = rawRows.rows || rawRows.data || (Array.isArray(rawRows) ? rawRows : []);

			if (!Array.isArray(rows) || !rows.length) {
				feed.appendChild(el('p', 'description', L('audit_none', 'No activity in this trail yet.')));
				return;
			}

			var t = document.createElement('table');

			t.className = 'vc-tbl widefat';

			if (scope === 'activity') {
				t.innerHTML = '<thead><tr>' +
					'<th>' + escHtml(L('col_when', 'When')) + '</th>' +
					'<th>' + escHtml(L('col_event', 'Event')) + '</th>' +
					'<th>' + escHtml(L('col_connector', 'Connector')) + '</th>' +
					'<th>' + escHtml(L('col_actor', 'Actor')) + '</th>' +
					'</tr></thead><tbody>' +
					rows.slice(0, 30).map(function (e) {
						return '<tr>' +
							'<td>' + escHtml(e.created_at ? String(e.created_at).replace('T', ' ').slice(0, 19) : '\u2014') + '</td>' +
							'<td>' + escHtml(e.event || '\u2014') + '</td>' +
							'<td>' + escHtml((e.server && e.server.name) || '\u2014') + '</td>' +
							'<td>' + escHtml((e.actor_user && e.actor_user.name) || '\u2014') + '</td>' +
							'</tr>';
					}).join('') + '</tbody>';
			} else {
				t.innerHTML = '<thead><tr>' +
					'<th>' + escHtml(L('col_when', 'When')) + '</th>' +
					'<th>' + escHtml(L('col_action', 'Action')) + '</th>' +
					'<th>' + escHtml(L('col_actor', 'Actor')) + '</th>' +
					'<th>' + escHtml(L('col_ip', 'IP')) + '</th>' +
					'</tr></thead><tbody>' +
					rows.slice(0, 30).map(function (e) {
						var actorName = e.actor ? (e.actor.name || e.actor.id) : e.actor_id;

						return '<tr>' +
							'<td>' + escHtml(e.created_at ? String(e.created_at).replace('T', ' ').slice(0, 19) : '\u2014') + '</td>' +
							'<td>' + escHtml(e.action || e.event || '\u2014') + '</td>' +
							'<td>' + escHtml(actorName || '\u2014') + '</td>' +
							'<td>' + escHtml(e.ip_address || e.ip || '\u2014') + '</td>' +
							'</tr>';
					}).join('') + '</tbody>';
			}

			feed.appendChild(t);
		}).catch(function () {
			feed.innerHTML = '';
			feed.appendChild(el('p', 'description', L('audit_unavailable', 'This trail is unavailable right now.')));
		});
	}

	var auditRoot = document.getElementById('vc-audit-root');

	if (auditRoot && connected()) {
		var tabs = auditRoot.querySelectorAll('#vc-audit-tabs .nav-tab');

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				tabs.forEach(function (t) { t.classList.remove('nav-tab-active'); });
				tab.classList.add('nav-tab-active');
				renderAuditFeed(tab.dataset.scope);
			});
		});

		var first = auditRoot.querySelector('#vc-audit-tabs .nav-tab[data-scope="activity"]');

		if (first) {
			first.classList.add('nav-tab-active');
			renderAuditFeed('activity');
		}
	}
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
	var devicePollFails = 0;
	/* Stop hammering the endpoint: a nonce that returns 401/403 will never
	 * recover on its own, and neither will a persistent network error. */
	var DEVICE_POLL_MAX_FAILS = 20; // ~100 s of consecutive failures

	function pollDevice() {
		rest('auth/device/poll', { method: 'POST', body: '{}' }).then(function (result) {
			devicePollFails = 0;

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
			devicePollFails += 1;

			if (devicePollFails >= DEVICE_POLL_MAX_FAILS) {
				devicePolling = false;
				showDeviceError(L('device_error', 'Could not start device login. Please try again.'));
				return;
			}

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

			var p = el('p');
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
