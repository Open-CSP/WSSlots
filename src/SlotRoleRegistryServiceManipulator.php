<?php

namespace WSSlots;

use Config;
use ConfigException;
use MediaWiki\Revision\SlotRoleRegistry;
use Psr\Log\LoggerInterface;

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
	private $config;

	/**
	 * @var LoggerInterface The logger to send log messages to
	 */
	private $logger;

	/**
	 * SlotRoleRegistryServiceManipulator constructor.
	 *
	 * @param Config $config The Config to use
	 * @param LoggerInterface $logger The logger to send log messages to
	 */
	public function __construct( Config $config, LoggerInterface $logger ) {
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Defines the roles for the given SlotRoleRegistry.
	 *
	 * @param SlotRoleRegistry $registry The registry to define the meta-data role for
	 * @return void
	 */
	public function defineRoles( SlotRoleRegistry $registry ) {
		try {
			$defined_slots = $this->config->get( "WSSlotsDefinedSlots" );
			if ( !is_array( $defined_slots ) ) {
				throw new ConfigException();
			}
			/** @var array $defined_slots */
		} catch ( ConfigException $exception ) {
			$this->logger->critical( wfMessage( "wsslots-invalid-defined-slots-config" ) );
			$defined_slots = [];
		}

		try {
			$default_content_model = $this->config->get( "WSSlotsDefaultContentModel" );
			if ( !is_string( $default_content_model ) ) {
				throw new ConfigException();
			}
			/** @var string $default_content_model */
		} catch ( ConfigException $exception ) {
			$this->logger->critical( wfMessage( "wsslots-invalid-default-content-model-config" ) );
			$default_content_model = "wikitext";
		}

		try {
			$default_slot_role_layout = $this->config->get( "WSSlotsDefaultSlotRoleLayout" );
			if ( !is_array( $default_slot_role_layout ) ) {
				throw new ConfigException();
			}
			/** @var array $default_slot_role_layout */
		} catch ( ConfigException $exception ) {
			$this->logger->critical( wfMessage( "wsslots-invalid-default-slot-role-config" ) );
			$default_slot_role_layout = [
				"display" => "none",
				"region" => "center",
				"placement" => "append"
			];
		}

		foreach ( $defined_slots as $key => $value ) {
			if ( is_string( $key ) && is_array( $value ) && $value !== [] ) {
				$slot_name = $key;
				$slot_settings = $value;
			} elseif ( is_int( $key ) && is_string( $value ) ) {
				$slot_name = $value;
				$slot_settings = [];
			} else {
				// This slot definition is invalid
				$this->logger->critical( wfMessage( "wsslots-invalid-slot-definition" ) );
				continue;
			}

			$slot_settings["content_model"] = $slot_settings["content_model"] ?? $default_content_model;
			$slot_settings["slot_role_layout"] = $slot_settings["slot_role_layout"] ?? $default_slot_role_layout;

			// Define the role with the given settings
			$this->defineRole(
				$registry,
				$slot_name,
				$slot_settings
			);
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
		if ( $registry->isDefinedRole( $slot_name ) ) {
			$this->logger->alert( wfMessage( "wsslots-duplicate-role-definition", $slot_name ) );
			return false;
		}

		$content_model = $slot_settings["content_model"];
		$slot_role_layout = $slot_settings["slot_role_layout"];

		$registry->defineRoleWithModel( $slot_name, $content_model, $slot_role_layout );

		return true;
	}
}
