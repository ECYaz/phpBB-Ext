<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\api;

/**
 * Immutable result of a successful authentication: the API credential row and
 * the phpBB user row whose permissions the request now runs with. Controllers
 * use it for credential-level restrictions (read-only, forum allow-list) that
 * sit on top of the user's native phpBB ACL.
 */
class auth_context
{
	/** @var array */
	protected $credential;

	/** @var array */
	protected $user_row;

	public function __construct(array $credential, array $user_row)
	{
		$this->credential = $credential;
		$this->user_row   = $user_row;
	}

	public function get_key_id()
	{
		return (int) $this->credential['key_id'];
	}

	public function get_user_id()
	{
		return (int) $this->credential['user_id'];
	}

	public function is_read_only()
	{
		return (bool) $this->credential['read_only'];
	}

	/**
	 * Forum ids this credential is restricted to.
	 *
	 * @return int[] Allowed forum ids, or an empty array meaning "all forums
	 *               the linked user can already see".
	 */
	public function get_allowed_forums()
	{
		$raw = trim((string) $this->credential['forum_ids']);

		if ($raw === '')
		{
			return [];
		}

		$ids = json_decode($raw, true);

		return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
	}

	/**
	 * Whether this credential's forum allow-list permits the given forum.
	 * This is the credential restriction only; the user's phpBB ACL is checked
	 * separately and independently.
	 *
	 * Fails closed: an allow-list that is present but cannot be parsed (corrupt
	 * JSON) denies every forum rather than silently granting access to all.
	 */
	public function can_access_forum($forum_id)
	{
		$raw = trim((string) $this->credential['forum_ids']);

		// No allow-list at all means "any forum the linked user can already see".
		if ($raw === '')
		{
			return true;
		}

		$ids = json_decode($raw, true);

		// Present but unparseable / empty -> deny, never fall back to "all".
		if (!is_array($ids) || empty($ids))
		{
			return false;
		}

		return in_array((int) $forum_id, array_map('intval', $ids), true);
	}

	/**
	 * Extensions whose endpoints this credential may call.
	 *
	 * @return string[] An empty array for none, ['*'] for every extension,
	 *                  otherwise a list of vendor/package names.
	 */
	public function get_allowed_extensions()
	{
		$raw = isset($this->credential['ext_access']) ? trim((string) $this->credential['ext_access']) : '';

		if ($raw === '')
		{
			return [];
		}

		if ($raw === '*')
		{
			return ['*'];
		}

		$names = json_decode($raw, true);

		return is_array($names) ? array_values(array_filter($names, 'is_string')) : [];
	}

	/**
	 * Whether this credential may call endpoints offered by the given
	 * extension. Fails closed: no stored choice, or one that cannot be read,
	 * means no access.
	 *
	 * @param string $extension vendor/package
	 * @return bool
	 */
	public function can_use_extension($extension)
	{
		$allowed = $this->get_allowed_extensions();

		if ($allowed === ['*'])
		{
			return true;
		}

		return in_array(strtolower((string) $extension), array_map('strtolower', $allowed), true);
	}

	/**
	 * Stop the request unless the credential's forum allow list permits the
	 * forum. Endpoints that act on a forum, topic or post call this in
	 * addition to their own permission checks.
	 *
	 * @param int $forum_id
	 * @throws exception With forum_not_allowed when the forum is outside the list.
	 */
	public function assert_forum($forum_id)
	{
		if (!$this->can_access_forum($forum_id))
		{
			throw new exception('forum_not_allowed', 403);
		}
	}
}
