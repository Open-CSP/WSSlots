<?php

namespace WSSlots;

use CommentStoreComment;
use Content;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\SlotRecord;
use MWException;
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
class WSSlots {
	/**
	 * @param User $user The user that performs the edit
	 * @param WikiPage $wikiPage The page to edit
	 * @param string $text The text to insert/append
	 * @param string $slotName The slot to edit
	 * @param string $summary The summary to use
	 * @param bool $append Whether to append to or replace the current text
	 * @param string $watchlist Set to "nochange" to suppress watchlist notifications
	 *
	 * @return true|array True on success, or an error message with an error code otherwise
	 *
	 * @throws \MWContentSerializationException Should not happen
	 * @throws MWException Should not happen
	 */
	final public static function editSlot(
		User $user,
		WikiPage $wikiPage,
		string $text,
		string $slotName,
		string $summary,
		bool $append = false,
		string $watchlist = ""
	) {
		return self::editSlots( $user, $wikiPage, [ $slotName => $text ], $summary, $append, $watchlist );
	}

	/**
	 * @param User $user The user that performs the edit
	 * @param WikiPage $wikiPage The page to edit
	 * @param array $slotUpdates Associative array with slotName as key, text as value
	 * @param string $summary The summary to use
	 * @param bool $append Whether to append to or replace the current text
	 * @param string $watchlist Set to "nochange" to suppress watchlist notifications
	 *
	 * @return true|array True on success, or an error message with an error code otherwise
	 *
	 * @throws \MWContentSerializationException Should not happen
	 * @throws MWException Should not happen
	 */
	final public static function editSlots(
		User $user,
		WikiPage $wikiPage,
		array $slotUpdates,
		string $summary,
		bool $append = false,
		string $watchlist = ""
	) {
		$logger = Logger::getLogger();

		$titleObject = $wikiPage->getTitle();
		$pageUpdater = $wikiPage->newPageUpdater( $user );
		$oldRevisionRecord = $wikiPage->getRevisionRecord();
		$slotRoleRegistry = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		if ( $titleObject === null ) {
			$logger->alert( 'The WikiPage object given to editSlot is not valid, since it does not contain a Title' );
			return [ wfMessage( "wsslots-error-invalid-wikipage-object" ) ];
		}

		foreach ( $slotUpdates as $slotName => $text ) {
			$logger->debug( 'Editing slot {slotName} on page {page}', [
				'slotName' => $slotName,
				'page' => $titleObject->getFullText()
			] );

			// Make sure the slot we are editing exists
			if ( !$slotRoleRegistry->isDefinedRole( $slotName ) ) {
				$logger->alert( 'Tried to edit non-existent slot {slotName} on page {page}', [
					'slotName' => $slotName,
					'page' => $titleObject->getFullText()
				] );

				return [ wfMessage( "wsslots-apierror-unknownslot", $slotName ), "unknownslot" ];
			}

			// Alter $text when the $append parameter is set
			if ( $append ) {
				// We want to append the given text to the current page, instead of replacing the content
				$content = self::getSlotContent( $wikiPage, $slotName );

				if ( $content !== null ) {
					if ( !( $content instanceof TextContent ) ) {
						$slotContentHandler = $content->getContentHandler();
						$modelId = $slotContentHandler->getModelID();

						$logger->alert( 'Tried to append to slot {slotName} with non-textual content model {modelId} while editing page {page}', [
							'slotName' => $slotName,
							'modelId' => $modelId,
							'page' => $titleObject->getFullText()
						] );

						return [ wfMessage( "apierror-appendnotsupported" ), $modelId ];
					}

					/** @var string $text */
					$contentText = $content->serialize();
					$text = $contentText . $text;
				}
			}

			if ( $text === "" && $slotName !== SlotRecord::MAIN ) {
				// Remove the slot if $text is empty and the slot name is not MAIN
				$logger->debug( 'Removing slot {slotName} since it is empty', [
					'slotName' => $slotName
				] );

				$pageUpdater->removeSlot( $slotName );
			} else {
				// Set the content for the slot we want to edit
				if ( $oldRevisionRecord !== null && $oldRevisionRecord->hasSlot( $slotName ) ) {
					$modelId = $oldRevisionRecord
						->getSlot( $slotName )
						->getContent()
						->getContentHandler()
						->getModelID();
				} else {
					$modelId = $slotRoleRegistry
						->getRoleHandler( $slotName )
						->getDefaultModel( $titleObject );
				}

				$logger->debug( 'Setting content in PageUpdater' );

				$slotContent = ContentHandler::makeContent( $text, $titleObject, $modelId );
				$pageUpdater->setContent( $slotName, $slotContent );
			}

			if ( $slotName !== SlotRecord::MAIN ) {
				// Note: An in_array check is not necessary because array_unique is called
				// in pageUpdater->computeEffectiveTags()
				$pageUpdater->addTag( 'wsslots-slot-edit' );
			}
		}

		if ( $oldRevisionRecord === null && !isset( $slotUpdates[SlotRecord::MAIN] ) ) {
			// The 'main' content slot MUST be set when creating a new page
			$logger->debug( 'Setting empty "main" slot' );

			$main_content = ContentHandler::makeContent( "", $titleObject );
			$pageUpdater->setContent( SlotRecord::MAIN, $main_content );
		}

		$flags = EDIT_INTERNAL;
		$comment = CommentStoreComment::newUnsavedComment( $summary );

		if ( $watchlist === "nochange" ) {
			$flags |= EDIT_SUPPRESS_RC;
		}

		$logger->debug( 'Calling saveRevision on PageUpdater' );
		$pageUpdater->saveRevision( $comment, $flags );
		$logger->debug( 'Finished calling saveRevision on PageUpdater' );

		if ( !$pageUpdater->isUnchanged() && MediaWikiServices::getInstance()->getMainConfig()->get( "WSSlotsDoPurge" ) ) {
			$logger->debug( 'Refreshing data for page {page}', [
				'page' => $titleObject->getFullText()
			] );

			// Perform an additional null-edit to make sure all page properties are up-to-date
			$comment = CommentStoreComment::newUnsavedComment( "" );
			$pageUpdater = $wikiPage->newPageUpdater( $user );
			$pageUpdater->saveRevision( $comment, EDIT_SUPPRESS_RC | EDIT_AUTOSUMMARY );
		}

		return true;
	}

	/**
	 * Returns the content of the given slot.
	 *
	 * @param WikiPage $wikiPage
	 * @param string $slot
	 * @return Content|null The content in the given slot, or NULL if no content exists
	 */
	final public static function getSlotContent( WikiPage $wikiPage, string $slot ): ?Content {
		$revisionRecord = $wikiPage->getRevisionRecord();

		if ( $revisionRecord === null || !$revisionRecord->hasSlot( $slot ) ) {
			return null;
		}

		return $revisionRecord->getContent( $slot );
	}
}
