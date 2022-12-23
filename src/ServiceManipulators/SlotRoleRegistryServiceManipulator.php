<?php

namespace WSSlots\ServiceManipulators;

use Config;
use ConfigException;
use MediaWiki\Revision\SlotRoleRegistry;
use Psr\Log\LoggerInterface;
use WSSlots\Logger;

/**
 * Class SlotRegistryServiceManipulator
 *
 * This class holds functions that manipulate the SlotRoleRegistry service.
 *
 * @package MetaDataSlot
 */
class SlotRoleRegistryServiceManipulator {
	/**
	 * @var Config The Config to use
	 */
	private Config $config;

	/**
	 * @var LoggerInterface The logger to send log messages to
	 */
	private LoggerInterface $logger;

	/**
	 * SlotRoleRegistryServiceManipulator constructor.
	 *
	 * @param Config $config The Config to use
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		$this->logger = Logger::getLogger();
	}

	/**
	 * Defines the roles for the given SlotRoleRegistry.
	 *
	 * @param SlotRoleRegistry $registry The registry to define the meta-data role for
	 * @return void
	 */
	public function defineRoles( SlotRoleRegistry $registry ) {
		try {
			$definedSlots = $this->config->get( "WSSlotsDefinedSlots" );

			if ( !is_array( $definedSlots ) ) {
				throw new ConfigException();
			}
		} catch ( ConfigException $exception ) {
			$this->logger->critical( "Missing or invalid value for \$wgWSSlotsDefinedSlots." );
			$definedSlots = [];
		}

		try {
			$defaultContentModel = $this->config->get( "WSSlotsDefaultContentModel" );

			if ( !is_string( $defaultContentModel ) ) {
				throw new ConfigException();
			}
		} catch ( ConfigException $exception ) {
			$this->logger->critical( "Missing or invalid value for \$wgWSSlotsDefaultContentModel." );
			$defaultContentModel = "wikitext";
		}

		try {
			$defaultSlotRoleLayout = $this->config->get( "WSSlotsDefaultSlotRoleLayout" );

			if ( !is_array( $defaultSlotRoleLayout ) ) {
				throw new ConfigException();
			}
		} catch ( ConfigException $exception ) {
			$this->logger->critical( "Missing or invalid value for \$wgWSSlotsDefaultSlotRoleLayout." );
			$defaultSlotRoleLayout = [
				"display" => "none",
				"region" => "center",
				"placement" => "append"
			];
		}

		foreach ( $definedSlots as $key => $value ) {
			if ( is_string( $key ) && is_array( $value ) && $value !== [] ) {
				$slotName = $key;
				$slotSettings = $value;
			} elseif ( is_int( $key ) && is_string( $value ) ) {
				$slotName = $value;
				$slotSettings = [];
			} else {
				// This slot definition is invalid
				$this->logger->critical( "Invalid slot definition!" );
				continue;
			}

			$slotSettings["content_model"] = $slotSettings["content_model"] ?? $defaultContentModel;
			$slotSettings["slot_role_layout"] = $slotSettings["slot_role_layout"] ?? $defaultSlotRoleLayout;

			if ( !$registry->isDefinedRole( $slotName ) ) {
				$registry->defineRoleWithModel( $slotName, $slotSettings["content_model"], $slotSettings["slot_role_layout"] );
			}
		}
	}

	/**
	 * Defines a slot role.
	 *
	 * @param SlotRoleRegistry $registry The slot role registry to register the slot
	 * @param string $slot_name The name of the slot to define
	 * @param array $slot_settings The settings of the slot to define
	 *
	 * @return bool True when a new role is defined, false when it already existed
	 */
	public function defineRole(
		SlotRoleRegistry $registry,
		string $slot_name,
		array $slot_settings
	): bool {
		return true;
	}
}
