<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \ecyaz\liveupdates\settings */
	protected $settings;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\controller\helper */
	protected $helper;
	/** @var \phpbb\language\language */
	protected $language;

	public function __construct(
		\ecyaz\liveupdates\settings $settings,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language
	)
	{
		$this->settings = $settings;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->language = $language;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'        => 'load_language',
			'core.page_header_after' => 'inject_bootstrap',
		];
	}

	public function load_language($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = ['ext_name' => 'ecyaz/liveupdates', 'lang_set' => 'common'];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function inject_bootstrap($event)
	{
		$is_guest = (int) $this->user->data['user_id'] === ANONYMOUS;
		$active = $this->settings->is_enabled() && (!$is_guest || $this->settings->is_guest_allowed());

		$strings = [
			'newReplies' => $this->language->lang('LIVEUPDATES_NEW_REPLIES'),
			'newReply'   => $this->language->lang('LIVEUPDATES_NEW_REPLY'),
			'newTopics'  => $this->language->lang('LIVEUPDATES_NEW_TOPICS'),
			'newTopic'   => $this->language->lang('LIVEUPDATES_NEW_TOPIC'),
		];

		$this->template->assign_vars([
			'S_LIVEUPDATES_ACTIVE' => $active,
			'LIVEUPDATES_CONFIG'   => $active ? json_encode($this->settings->client_config($is_guest, $strings), JSON_HEX_TAG | JSON_HEX_AMP) : '',
			'U_LIVEUPDATES_POLL'   => $this->helper->route('ecyaz_liveupdates_poll'),
		]);
	}
}
