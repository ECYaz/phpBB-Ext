/**
 *
 * Force Post Preview. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		// The event template emits this element with the values the script
		// needs: which form to gate, whether a preview has been shown, and
		// the translated tooltip for the locked button.
		var data = document.getElementById('fpp-data');
		if (!data) {
			return;
		}

		var form = document.getElementById(data.getAttribute('data-fpp-form'));
		if (!form) {
			return;
		}

		// The main "Submit" button is the input named "post"; Preview, Save
		// draft and quick reply's "Full Editor & Preview" button stay usable
		// so the user can always reach a preview.
		var submit = form.querySelector('input[name="post"]');
		if (!submit) {
			return;
		}

		var previewed = data.getAttribute('data-fpp-previewed') === '1';
		var tooltip = data.getAttribute('data-fpp-message');
		var message = document.getElementById('message');
		var subject = form.querySelector('input[name="subject"]');

		function lock() {
			submit.disabled = true;
			submit.className += submit.className.indexOf('fpp-disabled') === -1 ? ' fpp-disabled' : '';
			submit.setAttribute('title', tooltip);
		}

		function unlock() {
			submit.disabled = false;
			submit.className = submit.className.replace(/\s*fpp-disabled/g, '');
			submit.removeAttribute('title');
		}

		// Editor insertions (BBCode toolbar, colour palette, smilies, inline
		// attachment placement) write textarea.value through the core
		// editor.js globals insert_text() / bbfontstyle() and fire no input
		// event, so relocking needs the globals wrapped as well as the
		// input listeners.
		function wrap_global(name) {
			var original = window[name];
			if (typeof original !== 'function') {
				return;
			}
			window[name] = function () {
				lock();
				return original.apply(this, arguments);
			};
		}

		if (previewed) {
			unlock();
			// Editing the message or subject after previewing requires a
			// fresh preview, so the user always submits what they previewed.
			if (message) {
				message.addEventListener('input', lock);
			}
			if (subject) {
				subject.addEventListener('input', lock);
			}
			wrap_global('insert_text');
			wrap_global('bbfontstyle');
		} else {
			lock();
		}
	});
})();
