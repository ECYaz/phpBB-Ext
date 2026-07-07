<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\service;

/**
 * Small collection of pure, dependency-free text helpers shared across
 * outbound feed/sync formats (ICS, Google Calendar, ...). No DB, no
 * service, no phpBB global state — safe to call statically from anywhere,
 * including plain unit tests.
 *
 * html_to_plain_text() lived only in service/ics.php through task 7; it is
 * extracted here (task 8) so service/gcal_client.php can reuse the exact
 * same BBCode-rendered-HTML -> plain-text conversion instead of
 * re-implementing it — see gcal_client's class docblock for how it is used.
 * ics.php now delegates to this class rather than keeping its own copy.
 */
class text_util
{
	/**
	 * BBCode-rendered HTML -> plain text, preserving line breaks.
	 *
	 * generate_text_for_display()'s legacy (non-s9e-XML) rendering path
	 * runs the stored text through bbcode_nl2br() (includes/functions_content.php),
	 * which turns every raw newline into a literal "<br />" tag -- a naive
	 * strip_tags() then deletes that tag outright, silently JOINING what
	 * were separate lines into one run-on string with no separator at all.
	 * Block-level closing tags (</p>, </div>, </li>, headings, table rows)
	 * get the same treatment for the same reason -- the s9e-XML rendering
	 * path (real posts made through the extension's own add/edit form)
	 * produces the same kind of HTML for these. Both are converted to "\n"
	 * BEFORE strip_tags() runs so callers can re-encode them however their
	 * own output format requires (e.g. ics::escape_text()'s RFC 5545
	 * literal "\n" escape).
	 */
	public static function html_to_plain_text(string $html): string
	{
		$html = preg_replace('#<br\s*/?>#i', "\n", $html);
		$html = preg_replace('#</(p|div|li|h[1-6]|tr)>#i', "\n", $html);

		return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * Collapses a (possibly multi-line, possibly huge) HTTP response body
	 * into a single-line, length-capped snippet suitable for quoting inside
	 * an admin-facing \RuntimeException message. Shared by gcal_auth and
	 * gcal_client so both report Google API errors the same way.
	 */
	public static function error_snippet(string $body, int $limit = 300): string
	{
		$snippet = trim(preg_replace('/\s+/', ' ', $body));

		if (strlen($snippet) > $limit)
		{
			$snippet = substr($snippet, 0, $limit) . '...';
		}

		return $snippet;
	}
}
