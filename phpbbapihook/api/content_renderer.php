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
 * Renders a stored phpBB post row into display HTML and human-readable (uid-stripped) BBCode.
 */
class content_renderer
{
	/** @var string */
	protected $root_path;
	/** @var string */
	protected $php_ext;
	/** @var bool */
	protected $loaded = false;

	public function __construct($root_path, $php_ext)
	{
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	protected function ensure_loaded()
	{
		if (!$this->loaded)
		{
			if (!function_exists('generate_text_for_display'))
			{
				$path = $this->root_path . 'includes/functions_content.' . $this->php_ext;
				include $path;
			}
			$this->loaded = true;
		}
	}

	/**
	 * @param array $row post row containing post_text, bbcode_uid, bbcode_bitfield
	 * @return array{content_html:string, content_bbcode:string}
	 */
	public function render(array $row)
	{
		$this->ensure_loaded();

		$text     = (string) $row['post_text'];
		$uid      = (string) $row['bbcode_uid'];
		$bitfield = (string) $row['bbcode_bitfield'];
		$flags    = ($bitfield !== '' ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;

		$html = generate_text_for_display($text, $uid, $bitfield, $flags, true);
		$edit = generate_text_for_edit($text, $uid, $flags);

		return [
			'content_html'   => (string) $html,
			'content_bbcode' => (string) $edit['text'],
		];
	}
}
