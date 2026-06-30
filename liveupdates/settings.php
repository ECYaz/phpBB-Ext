<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates;

class settings
{
	/** @var \phpbb\config\config */
	protected $config;

	/** Focused-tab interval (seconds) per posture preset. */
	const POSTURES = [
		'balanced'     => 10,
		'snappy'       => 5,
		'conservative' => 25,
	];

	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	public function is_enabled()
	{
		return (bool) $this->config['ecyaz_liveupdates_enabled'];
	}

	public function is_guest_allowed()
	{
		return (bool) $this->config['ecyaz_liveupdates_guest_enabled'];
	}

	public function min_interval()
	{
		return max(1, (int) $this->config['ecyaz_liveupdates_min_interval']);
	}

	public function effective_interval($is_guest)
	{
		if ($is_guest)
		{
			$interval = (int) $this->config['ecyaz_liveupdates_guest_interval'];
		}
		else
		{
			$override = (int) $this->config['ecyaz_liveupdates_interval_override'];
			$posture  = (string) $this->config['ecyaz_liveupdates_posture'];
			$interval = $override > 0
				? $override
				: (self::POSTURES[$posture] ?? self::POSTURES['balanced']);
		}

		return max($this->min_interval(), $interval);
	}

	public function surfaces()
	{
		return [
			'topic'  => (bool) $this->config['ecyaz_liveupdates_surface_topic'],
			'notify' => (bool) $this->config['ecyaz_liveupdates_surface_notify'],
			'index'  => (bool) $this->config['ecyaz_liveupdates_surface_index'],
			'pm'     => (bool) $this->config['ecyaz_liveupdates_surface_pm'],
			'online' => (bool) $this->config['ecyaz_liveupdates_surface_online'],
			'stats'  => (bool) $this->config['ecyaz_liveupdates_surface_stats'],
		];
	}

	public function client_config($is_guest, array $strings = [])
	{
		return [
			'enabled'     => $this->is_enabled(),
			'interval'    => $this->effective_interval($is_guest),
			'minInterval' => $this->min_interval(),
			'surfaces'    => $this->surfaces(),
			'strings'     => $strings,
		];
	}
}
