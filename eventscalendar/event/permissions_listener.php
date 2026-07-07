<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class permissions_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.permissions' => 'add_permissions',
		];
	}

	public function add_permissions($event)
	{
		$categories = $event['categories'];
		$categories['ecal'] = 'ACL_CAT_ECAL';
		$event['categories'] = $categories;

		$permissions = $event['permissions'];
		$permissions['u_ecal_view']   = ['lang' => 'ACL_U_ECAL_VIEW', 'cat' => 'ecal'];
		$permissions['u_ecal_post']   = ['lang' => 'ACL_U_ECAL_POST', 'cat' => 'ecal'];
		$permissions['u_ecal_attend'] = ['lang' => 'ACL_U_ECAL_ATTEND', 'cat' => 'ecal'];
		$permissions['m_ecal_manage'] = ['lang' => 'ACL_M_ECAL_MANAGE', 'cat' => 'ecal'];
		$permissions['a_ecal_manage'] = ['lang' => 'ACL_A_ECAL_MANAGE', 'cat' => 'ecal'];
		$event['permissions'] = $permissions;
	}
}
