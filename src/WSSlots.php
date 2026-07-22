<?php

namespace WSSlots;

use ChangeTags;
use CommentStoreComment;
use Content;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
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
class WSSlots {
	/**
	 * @param User $user The user that performs the edit.
	 * @param WikiPage $wikiPage The page to edit.
	 * @param string $text The text to insert/append.
	 * @param string $slotName The slot to edit.
	 * @param SlotEditOptions $options The options to use.
	 *
	 * @return true|array True on success, or an error message with an error code otherwise.
	 */
	final public static function performSlotEdit(
		User $user,
		WikiPage $wikiPage,
		string $text,
		string $slotName,
		SlotEditOptions $options,
	) {
		return self::performSlotEdits(
			$user,
			$wikiPage,
			[ $slotName => $text ],
			$options
		);
	}

	/**
	 * @param User $user The user that performs the edit.
	 * @param WikiPage $wikiPage The page to edit.
	 * @param array $updates Associative array with slotName as key, text as value.
	 * @param SlotEditOptions $options The options to use.
	 *
	 * @return true|array True on success, or an error message with an error code otherwise.
	 */
	final public static function performSlotEdits(
		User $user,
		WikiPage $wikiPage,
		array $updates,
		SlotEditOptions $options,
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

		if (
			( $titleObject->exists() && $options->createonly )
			|| ( !$titleObject->exists() && $options->nocreate )
		) {
			return true;
		}

		foreach ( $updates as $slotName => $text ) {
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

			// Alter $text when the $append or $prepend (or both) is true
			if ( $options->append || $options->prepend ) {
				// We want to append the given text to the current page, instead of replacing the content
				$content = self::getSlotContent( $wikiPage, $slotName );

				if ( $content !== null ) {
					if ( !( $content instanceof TextContent ) ) {
						$slotContentHandler = $content->getContentHandler();
						$modelId = $slotContentHandler->getModelID();

						$logger->alert(
							'Tried to append/prepend to slot {slotName} with non-textual ' .
							'content model {modelId} while editing page {page}',
							[
								'slotName' => $slotName,
								'modelId' => $modelId,
								'page' => $titleObject->getFullText()
							]
						);

						return [ wfMessage( "apierror-appendnotsupported" ), $modelId ];
					}

					$contentText = $content->serialize();

					if ( $options->append ) {
						$contentText = $contentText . $text;
					}

					if ( $options->prepend ) {
						$contentText = $text . $contentText;
					}

					$text = $contentText;
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

		// Add custom tags
		if ( !empty( $options->tags ) ) {
			$tagStatus = ChangeTags::canAddTagsAccompanyingChange( $options->tags, $user );

			if ( !$tagStatus->isOK() ) {
				$logger->alert( $tagStatus->getMessage()->text() );
				return [ $tagStatus->getMessage() ];
			}

			$pageUpdater->addTags( $options->tags );
		}

		$flags = EDIT_INTERNAL;
		$comment = CommentStoreComment::newUnsavedComment( $options->summary );

		if ( $options->bot ) {
			$flags |= EDIT_FORCE_BOT;
		}

		if ( $options->minor ) {
			$flags |= EDIT_MINOR;
		}

		if ( $options->suppress ) {
			$flags |= EDIT_SUPPRESS_RC;
		}

		$logger->debug( 'Calling saveRevision on PageUpdater' );
		$pageUpdater->saveRevision( $comment, $flags );
		$logger->debug( 'Finished calling saveRevision on PageUpdater' );

		// Add the page to the watchlist
		$watch = self::getWatchlistValue( $options->watchlist, $titleObject, $user );
		if ( method_exists( MediaWikiServices::class, 'getWatchlistManager' ) ) {
			// >1.37
			MediaWikiServices::getInstance()->getWatchlistManager()->setWatch( $watch, $user, $titleObject );
		} else {
			// <=1.36
			$watchedItemStore = MediaWikiServices::getInstance()->getWatchedItemStore();

			if ( $watch ) {
				$watchedItemStore->addWatch( $user, $titleObject );
			} else {
				$watchedItemStore->removeWatch( $user, $titleObject );
			}

			$user->invalidateCache();
		}

		if (
			!$pageUpdater->isUnchanged()
			&& MediaWikiServices::getInstance()->getMainConfig()->get( "WSSlotsDoPurge" )
		) {
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
	 * @param User $user The user that performs the edit
	 * @param WikiPage $wikiPage The page to edit
	 * @param string $text The text to insert/append
	 * @param string $slotName The slot to edit
	 * @param string $summary The summary to use
	 * @param bool $append Whether to append to or replace the current text
	 * @param string $watchlist Unconditionally add (watch) or remove (unwatch) the page from the current user's
	 *                           watchlist, use preferences (preferences), or do not change watch (nochange, default).
	 *                           Must be one of the following values: watch, unwatch, preferences, nochange.
	 * @param bool $prepend Whether to prepend to the current text.
	 * @param bool $bot Whether this edit should be marked as a bot edit
	 * @param bool $minor Whether this edit should be marked as minor
	 * @param bool $createonly Don't edit the page if it exists already.
	 * @param bool $nocreate Don't create the page if it doesn't exist already.
	 * @param bool $suppress Whether to suppress the edit in recent changes.
	 * @param string[] $tags Change tags to apply to the revision.
	 * @return true|array True on success, or an error message with an error code otherwise
	 *
	 * @deprecated Use WSSlots::performSlotEdit() instead
	 * @see WSSlots::performSlotEdit()
	 */
	final public static function editSlot(
		User $user,
		WikiPage $wikiPage,
		string $text,
		string $slotName,
		string $summary,
		bool $append = false,
		string $watchlist = "nochange",
		bool $prepend = false,
		bool $bot = false,
		bool $minor = false,
		bool $createonly = false,
		bool $nocreate = false,
		bool $suppress = false,
		array $tags = [],
	) {
		return self::editSlots(
			$user,
			$wikiPage,
			[ $slotName => $text ],
			$summary,
			$append,
			$watchlist,
			$prepend,
			$bot,
			$minor,
			$createonly,
			$nocreate,
			$suppress,
			$tags,
		);
	}

	/**
	 * @param User $user The user that performs the edit.
	 * @param WikiPage $wikiPage The page to edit.
	 * @param array $slotUpdates Associative array with slotName as key, text as value.
	 * @param string $summary The summary to use.
	 * @param bool $append Whether to append to the current text.
	 * @param string $watchlist Unconditionally add (watch) or remove (unwatch) the page from the current user's
	 *                          watchlist, use preferences (preferences), or do not change watch (nochange, default).
	 *                          Must be one of the following values: watch, unwatch, preferences, nochange.
	 * @param bool $prepend Whether to prepend to the current text.
	 * @param bool $bot Whether this edit should be marked as a bot edit.
	 * @param bool $minor Whether this edit should be marked as minor.
	 * @param bool $createonly Don't edit the page if it exists already.
	 * @param bool $nocreate Don't create the page if it doesn't exist already.
	 * @param bool $suppress Whether to suppress the edit in recent changes.\
	 * @param string[] $tags Change tags to apply to the revision.
	 *
	 * @return true|array True on success, or an error message with an error code otherwise.
	 *
	 * @deprecated Use WSSlots::performSlotEdits() instead
	 * @see WSSlots::performSlotEdits()
	 */
	final public static function editSlots(
		User $user,
		WikiPage $wikiPage,
		array $slotUpdates,
		string $summary = '',
		bool $append = false,
		string $watchlist = "",
		bool $prepend = false,
		bool $bot = false,
		bool $minor = false,
		bool $createonly = false,
		bool $nocreate = false,
		bool $suppress = false,
		array $tags = [],
	) {
		$options = new SlotEditOptions();
		$options->summary = $summary;
		$options->append = $append;
		$options->watchlist = $watchlist;
		$options->prepend = $prepend;
		$options->bot = $bot;
		$options->minor = $minor;
		$options->createonly = $createonly;
		$options->nocreate = $nocreate;
		$options->suppress = $suppress;
		$options->tags = $tags;

		return self::performSlotEdits( $user, $wikiPage, $slotUpdates, $options );
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

	/**
	 * Return true if the page should be watched, false otherwise.
	 *
	 * @param string $watchlist Valid values: 'watch', 'unwatch', 'preferences', 'nochange'
	 * @param Title $title The page under consideration
	 * @param User $user The user get the value for
	 * @return bool
	 */
	protected static function getWatchlistValue(
		string $watchlist,
		Title $title,
		User $user
	): bool {
		$services = MediaWikiServices::getInstance();

		if ( method_exists( MediaWikiServices::class, 'getWatchlistManager' ) ) {
			// >=1.37
			$userWatching = $services->getWatchlistManager()->isWatchedIgnoringRights( $user, $title );
		} else {
			$userWatching = $services->getWatchedItemStore()->isWatched( $user, $title );
		}

		$userOptionsLookup = $services->getUserOptionsLookup();

		switch ( $watchlist ) {
			case 'watch':
				return true;

			case 'unwatch':
				return false;

			case 'preferences':
				// If the user is already watching, don't bother checking
				if ( $userWatching ) {
					return true;
				}
				// If the user is a bot, act as 'nochange' to avoid big watchlists on single users
				if ( $user->isBot() ) {
					return false;
				}
				return $userOptionsLookup->getBoolOption( $user, 'watchdefault' ) ||
					( $userOptionsLookup->getBoolOption( $user, 'watchcreations' ) &&
					!$title->exists() );

			// case 'nochange':
			default:
				return $userWatching;
		}
	}
}
