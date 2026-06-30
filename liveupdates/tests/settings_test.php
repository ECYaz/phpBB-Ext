<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\tests;

class ecyaz_liveupdates_settings_test extends \phpbb_test_case
{
	private function make(array $overrides = [])
	{
		$defaults = [
			'ecyaz_liveupdates_enabled'           => 1,
			'ecyaz_liveupdates_posture'            => 'balanced',
			'ecyaz_liveupdates_interval_override'  => 0,
			'ecyaz_liveupdates_guest_enabled'      => 0,
			'ecyaz_liveupdates_guest_interval'     => 30,
			'ecyaz_liveupdates_min_interval'       => 3,
			'ecyaz_liveupdates_surface_topic'      => 1,
			'ecyaz_liveupdates_surface_notify'     => 1,
			'ecyaz_liveupdates_surface_index'      => 1,
			'ecyaz_liveupdates_surface_pm'         => 1,
			'ecyaz_liveupdates_surface_online'     => 1,
			'ecyaz_liveupdates_surface_stats'      => 1,
		];
		$config = new \phpbb\config\config(array_merge($defaults, $overrides));
		return new \ecyaz\liveupdates\settings($config);
	}

	public function test_balanced_posture_interval()
	{
		$this->assertSame(10, $this->make()->effective_interval(false));
	}

	public function test_snappy_posture_interval()
	{
		$s = $this->make(['ecyaz_liveupdates_posture' => 'snappy']);
		$this->assertSame(5, $s->effective_interval(false));
	}

	public function test_conservative_posture_interval()
	{
		$s = $this->make(['ecyaz_liveupdates_posture' => 'conservative']);
		$this->assertSame(25, $s->effective_interval(false));
	}

	public function test_override_takes_precedence_but_respects_min()
	{
		$s = $this->make(['ecyaz_liveupdates_interval_override' => 1, 'ecyaz_liveupdates_min_interval' => 3]);
		$this->assertSame(3, $s->effective_interval(false));
	}

	public function test_guest_interval_used_for_guests()
	{
		$s = $this->make(['ecyaz_liveupdates_guest_enabled' => 1, 'ecyaz_liveupdates_guest_interval' => 40]);
		$this->assertSame(40, $s->effective_interval(true));
	}

	public function test_surfaces_map()
	{
		$s = $this->make(['ecyaz_liveupdates_surface_index' => 0]);
		$this->assertSame(['topic' => true, 'notify' => true, 'index' => false, 'pm' => true, 'online' => true, 'stats' => true], $s->surfaces());
	}
}
