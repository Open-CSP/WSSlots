<?php

namespace WSSlots;

use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * Class MediaWikiServicesHookHandler
 *
 * This class is the hook handler for the MediaWikiServices hook. The MediaWikiServices hook is called
 * when a global MediaWikiServices instance is initialized. Extensions may use this to define, replace,
 * or wrap services. However, the preferred way to define a new service is the $wgServiceWiringFiles
 * array.
 *
 * @package MetaDataSlot
 */
class MediaWikiServicesHookHandler implements MediaWikiServicesHook {
	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		$config = $services->getMainConfig();

		$service_manipulator = new SlotRoleRegistryServiceManipulator( $config );
		$manipulator = [ $service_manipulator, "defineRoles" ];
		$services->addServiceManipulator( "SlotRoleRegistry", $manipulator );
	}
}
