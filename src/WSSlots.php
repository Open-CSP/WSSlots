<?php

namespace WSSlots;

use Content;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\SlotRecord;
use TextContent;
use User;
use WikiPage;
use WikiRevision;

/**
 * Class WSSlots
 *
 * This class contains static methods that may be used by WSSlots or other extensions for manipulating
 * slots.
 *
 * @package WSSlots
 */
abstract class WSSlots {
	/**
	 * @param User $user The user that performs the edit
	 * @param WikiPage $wikipage_object The page to edit
	 * @param string $text The text to insert/append
	 * @param string $slot_name The slot to edit
	 * @param string $summary The summary to use
	 * @param bool $append Whether to append to or replace the current text
	 *
	 * @return true|array True on success, and an error message with an error code otherwise
	 *
	 * @throws \MWContentSerializationException Should not happen
	 * @throws \MWException Should not happen
	 */
	public static function editSlot(
		User $user,
		WikiPage $wikipage_object,
		string $text,
		string $slot_name,
		string $summary,
		bool $append = false
	) {
		$title_object = $wikipage_object->getTitle();

		/** @var SlotRoleRegistry $slot_role_registery */
		$slot_role_registry = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		if ( !$slot_role_registry->isDefinedRole( $slot_name ) ) {
			return [wfMessage( "wsslots-apierror-unknownslot", $slot_name ), "unknownslot"];
		}

		if ( $append ) {
			// We want to append the given text to the current page, instead of replacing the content
			$content = self::getSlotContent( $wikipage_object, $slot_name );

			if ( $content !== null ) {
				if ( !( $content instanceof TextContent ) ) {
					$slot_content_handler = $content->getContentHandler();
					$model_id = $slot_content_handler->getModelID();
					return [wfMessage( "apierror-appendnotsupported" ), $model_id];
				}

				/** @var string $text */
				$content_text = $content->serialize();
				$text = $content_text . $text;
			}
		}

		$wiki_revision = new WikiRevision( MediaWikiServices::getInstance()->getMainConfig() );
		$revision_record = $wikipage_object->getRevisionRecord();

		// Set the main and other slots for this revision
		if ( $revision_record !== null ) {
			$main_content = $revision_record->getContent( SlotRecord::MAIN );
			$wiki_revision->setContent( SlotRecord::MAIN, $main_content );

			// Set the content for any other slots the page may have
			$additional_slots = $revision_record->getSlots()->getSlots();
			foreach ( $additional_slots as $slot ) {
				if ( !$slot_role_registry->isDefinedRole( $slot->getRole() ) ) {
					// Prevent "Undefined slot role" error when editing a page that has an undefined slot
					continue;
				}

				$wiki_revision->setContent( $slot->getRole(), $slot->getContent() );
			}
		} else {
			$main_content = ContentHandler::makeContent("", $title_object);
			$wiki_revision->setContent( SlotRecord::MAIN, $main_content );
		}

		// Set the content for the slot we want to edit
		if ( $revision_record !== null && $revision_record->hasSlot( $slot_name ) ) {
			$slot = $revision_record->getSlot( $slot_name );
			$slot_content_handler = $slot->getContent()->getContentHandler();
			$model_id = $slot_content_handler->getModelID();
			$slot_content = ContentHandler::makeContent( $text, $title_object, $model_id );
			$wiki_revision->setContent( $slot_name, $slot_content );
		} else {
			$role_handler = $slot_role_registry->getRoleHandler( $slot_name );
			$model_id = $role_handler->getDefaultModel( $title_object );
			$slot_content = ContentHandler::makeContent( $text, $title_object, $model_id );
			$wiki_revision->setContent( $slot_name, $slot_content );
		}

		$wiki_revision->setTitle( $title_object );
		$wiki_revision->setComment( $summary );
		$wiki_revision->setTimestamp( wfTimestampNow() );
		$wiki_revision->setUserObj( $user );

		MediaWikiServices::getInstance()
			->getWikiRevisionOldRevisionImporter()
			->import( $wiki_revision );

		return true;
	}

	/**
	 * @param WikiPage $wikipage
	 * @param string $slot
	 * @return Content|null The content in the given slot, or NULL if no content exists
	 */
	public static function getSlotContent( WikiPage $wikipage, string $slot ) {
		$revision_record = $wikipage->getRevisionRecord();

		if ( $revision_record === null ) {
			return null;
		}

		if ( !$revision_record->hasSlot( $slot ) ) {
			return null;
		}

		return $revision_record->getContent( $slot );
	}
}