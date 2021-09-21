<?php

namespace WSSlots;

use CommentStoreComment;
use Content;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\SlotRecord;
use TextContent;
use User;
use WikiPage;

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
     * @param string $watchlist Set to "nochange" to suppress watchlist notifications
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
		bool $append = false,
        string $watchlist = ""
	) {
		$title_object = $wikipage_object->getTitle();
		$page_updater = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();
		$slot_role_registry = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		// Make sure the slot we are editing exists
		if ( !$slot_role_registry->isDefinedRole( $slot_name ) ) {
			return [wfMessage( "wsslots-apierror-unknownslot", $slot_name ), "unknownslot"];
		}

		// Alter $text when the $append parameter is set
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

		if ( $text === "" && $slot_name !== SlotRecord::MAIN ) {
			// Remove the slot if $text is empty and the slot name is not MAIN
			$page_updater->removeSlot( $slot_name );
		} else {
			// Set the content for the slot we want to edit
			if ( $old_revision_record !== null && $old_revision_record->hasSlot( $slot_name ) ) {
				$model_id = $old_revision_record
					->getSlot( $slot_name )
					->getContent()
					->getContentHandler()
					->getModelID();
			} else {
				$model_id = $slot_role_registry
					->getRoleHandler( $slot_name )
					->getDefaultModel( $title_object );
			}

			$slot_content = ContentHandler::makeContent( $text, $title_object, $model_id );
			$page_updater->setContent( $slot_name, $slot_content );
		}

		if ( $old_revision_record === null ) {
			// The 'main' content slot MUST be set when creating a new page
			$main_content = ContentHandler::makeContent("", $title_object);
			$page_updater->setContent( SlotRecord::MAIN, $main_content );
		}

		if ( $slot_name !== SlotRecord::MAIN ) {
			$page_updater->addTag( 'wsslots-slot-edit' );
		}

        $flags = EDIT_INTERNAL;
		$comment = CommentStoreComment::newUnsavedComment( $summary );

        if ( $watchlist === "nochange" ) {
            $flags |= EDIT_SUPPRESS_RC;
        }

		$page_updater->saveRevision( $comment, $flags );

		if ( !$page_updater->isUnchanged() ) {
			self::refreshData( $wikipage_object, $user );
		}

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

	/**
	 * Performs a refresh if necessary.
	 *
	 * @param WikiPage $wikipage_object
	 * @param User $user
	 * @throws \MWException
	 */
	public static function refreshData( WikiPage $wikipage_object, User $user ) {
		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( !$config->get( "WSSlotsDoPurge" ) ) {
			return;
		}

		// Perform an additional null-edit to make sure all page properties are up-to-date
		$comment = CommentStoreComment::newUnsavedComment( "");
		$page_updater = $wikipage_object->newPageUpdater( $user );
		$page_updater->saveRevision( $comment, EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY );
	}
}