<?php

namespace WSSlots;

use Content;
use ContentHandler;
use DeferredUpdates;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\SlotRecord;
use SMW\ApplicationFactory;
use SMW\Maintenance\DataRebuilder;
use SMW\Options;
use SMW\Store;
use SMW\StoreFactory;
use TextContent;
use Title;
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

		$page_updater = $wikipage_object->newPageUpdater( $user );
		$old_revision_record = $wikipage_object->getRevisionRecord();

		if ( $old_revision_record === null ) {
			// The 'main' content slot MUST be set when creating a new page
			$main_content = ContentHandler::makeContent("", $title_object);
			$page_updater->setContent( SlotRecord::MAIN, $main_content );
		}

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

		if ( $slot_name !== SlotRecord::MAIN ) {
			$page_updater->addTag( 'wsslots-slot-edit' );
		}

		$page_updater->setContent( $slot_name, $slot_content );
		$page_updater->saveRevision( \CommentStoreComment::newUnsavedComment( $summary ) );

		$config = MediaWikiServices::getInstance()->getMainConfig();

		if ( $config->get( "WSSlotsDoPurge" ) ) {
			$wikipage_object->doPurge();
			$wikipage_object->updateParserCache( [
				'causeAction' => 'slot-purge',
				'causeAgent' => $user->getName()
			] );
			$wikipage_object->doSecondaryDataUpdates( [
				'recursive' => false,
				'causeAction' => 'slot-purge',
				'causeAgent' => $user->getName(),
				'defer' => DeferredUpdates::PRESEND
			] );
		}

		return true;
	}

	/**
	 * Performs a data rebuild for the given WikiPage object, if SemanticMediaWiki is installed.
	 *
	 * @param Title $title
	 */
	private static function performSemanticDataRebuild( Title $title ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SemanticMediaWiki' ) ) {
			return;
		}

		$store = StoreFactory::getStore();
		$store->setOption( Store::OPT_CREATE_UPDATE_JOB, false );

		$rebuilder = new DataRebuilder(
			$store,
			ApplicationFactory::getInstance()->newTitleFactory()
		);

		$rebuilder->setOptions(
		// Tell SMW to only rebuild the current page
			new Options( [ "page" => $title->getText() ] )
		);

		$rebuilder->rebuild();
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