/* Treasure Hunt — spawn display + collect (modal / icon / hybrid)
 * @package ecyaz/treasurehunt
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only */
(function () {
	'use strict';

	var cfg = {};
	try {
		var el = document.getElementById('treasurehunt-bootstrap');
		cfg = el ? JSON.parse(el.textContent || el.innerText || '{}') : {};
	} catch (e) { cfg = {}; }

	if (!cfg.enabled || !cfg.spawn || !cfg.spawn.token) { return; }

	var S         = cfg.strings || {};
	var spawn     = cfg.spawn;
	var style     = cfg.style || 'modal';
	var expiry    = (cfg.expiry || 60) * 1000;
	var claimed   = false;
	var spawnTime = Date.now();

	function t(key, fallback) { return (S[key] != null) ? S[key] : fallback; }

	function postCollect() {
		var body = 'token=' + encodeURIComponent(spawn.token) +
			'&hash=' + encodeURIComponent(cfg.csrf || '');
		return fetch(cfg.collectUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) { return r.json(); });
	}

	/* ---------- DOM builders ---------- */

	function buildModal() {
		var overlay = document.createElement('div');
		overlay.className = 'th-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');

		var box = document.createElement('div');
		box.className = 'th-modal th-rarity-' + (spawn.rarity || 1);

		if (spawn.itemImage) {
			var img = document.createElement('img');
			img.className = 'th-item-img';
			img.src = spawn.itemImage;
			img.alt = spawn.itemName || '';
			box.appendChild(img);
		}

		var title = document.createElement('p');
		title.className = 'th-title';
		title.textContent = t('found', 'You found a') + ' ' + (spawn.itemName || '') +
			'  +' + (spawn.points || 0) + ' ' + t('points', 'pts');
		box.appendChild(title);

		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'th-collect-btn';
		btn.textContent = t('collect', 'Collect');
		btn.addEventListener('click', function () { doCollect(btn, overlay); });
		box.appendChild(btn);

		overlay.appendChild(box);
		return overlay;
	}

	function buildIcon(onClick) {
		var icon = document.createElement('button');
		icon.type = 'button';
		icon.className = 'th-icon th-rarity-' + (spawn.rarity || 1);
		icon.setAttribute('aria-label', t('collect', 'Collect'));
		if (spawn.itemImage) {
			var img = document.createElement('img');
			img.src = spawn.itemImage;
			img.alt = spawn.itemName || '';
			icon.appendChild(img);
		}
		icon.addEventListener('click', onClick);
		return icon;
	}

	/* ---------- collect action ---------- */

	function doCollect(triggerEl, rootEl) {
		if (claimed) { return; }
		claimed = true;
		if (triggerEl) { triggerEl.disabled = true; }
		postCollect().then(function (res) {
			if (res && res.success) {
				showResult(res);
				if (rootEl && rootEl.parentNode) { rootEl.parentNode.removeChild(rootEl); }
			} else {
				// quiet failure — remove the spawn UI, no points
				if (rootEl && rootEl.parentNode) { rootEl.parentNode.removeChild(rootEl); }
			}
		}).catch(function () {
			if (rootEl && rootEl.parentNode) { rootEl.parentNode.removeChild(rootEl); }
		});
	}

	function showResult(res) {
		toast('+' + (res.delta || 0) + ' ' + t('points', 'pts'), 'th-toast-points');
		var badges = res.newBadges || [];
		badges.forEach(function (b) {
			toast((b.name || '') + ' — ' + t('badgeUnlocked', 'Badge unlocked!'), 'th-toast-badge', b.image);
		});
	}

	// Lazily create (or retrieve) the shared toast container that stacks toasts upward.
	function getToastContainer() {
		var c = document.getElementById('th-toast-container');
		if (!c) {
			c = document.createElement('div');
			c.id = 'th-toast-container';
			c.className = 'th-toast-container';
			document.body.appendChild(c);
		}
		return c;
	}

	function toast(text, cls, imgSrc) {
		var d = document.createElement('div');
		d.className = 'th-toast ' + (cls || '');
		if (imgSrc) {
			var im = document.createElement('img');
			im.src = imgSrc; im.alt = '';
			d.appendChild(im);
		}
		var span = document.createElement('span');
		span.textContent = text;
		d.appendChild(span);
		getToastContainer().appendChild(d);
		setTimeout(function () { d.classList.add('th-toast-in'); }, 20);
		setTimeout(function () {
			d.classList.remove('th-toast-in');
			setTimeout(function () { if (d.parentNode) { d.parentNode.removeChild(d); } }, 400);
		}, 4000);
	}

	/* ---------- style dispatch ---------- */

	function render() {
		if (style === 'icon' || style === 'hybrid') {
			var icon = buildIcon(function () {
				if (style === 'hybrid') {
					// icon → reveal modal; the modal inherits the remaining expiry window
					if (icon.parentNode) { icon.parentNode.removeChild(icon); }
					var modal = buildModal();
					document.body.appendChild(modal);
					// Auto-dismiss the hybrid-opened modal after the remaining expiry time
					// so it never lingers forever if the user ignores it.
					var remaining = expiry - (Date.now() - spawnTime);
					if (remaining > 0) {
						setTimeout(function () {
							if (!claimed && modal.parentNode) {
								modal.parentNode.removeChild(modal);
							}
						}, remaining);
					}
				} else {
					// pure icon → clicking collects directly
					doCollect(icon, icon);
				}
			});
			document.body.appendChild(icon);
			// Icon expires with a fade-out rather than an instant removal.
			setTimeout(function () {
				if (!claimed && icon.parentNode) {
					icon.classList.add('th-icon-fadeout');
					setTimeout(function () {
						if (!claimed && icon.parentNode) { icon.parentNode.removeChild(icon); }
					}, 300);
				}
			}, expiry);
		} else {
			// default: modal (no auto-dismiss — user must collect or navigate away)
			document.body.appendChild(buildModal());
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', render);
	} else {
		render();
	}
}());
