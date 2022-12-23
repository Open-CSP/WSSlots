<?php

namespace WSSlots\Actions;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use ParserOptions;
use RawAction;
use TextContent;

/**
 * A simple method to retrieve the plain source of an article,
 * using "action=rawslot" in the GET request string.
 *
 * @ingroup Actions
 */
class SlotAwareRawAction extends RawAction {
	public function getName() {
		return 'rawslot';
	}

	/**
	 * Get the text that should be returned, or false if the page or revision
	 * was not found. Unlike its parent, this function is slot-aware.
	 *
	 * @return string|bool
	 */
	public function getRawText() {
		$text = false;
		$title = $this->getTitle();
		$request = $this->getRequest();

		// Get it from the DB
		$rev = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $title, $this->getOldId() );
		if ( $rev ) {
			$lastmod = wfTimestamp( TS_RFC2822, $rev->getTimestamp() );
			$request->response()->header( "Last-modified: $lastmod" );

			// Public-only due to cache headers
			// Fetch specific slot if defined
			$slot = $this->getRequest()->getText( 'slot', SlotRecord::MAIN );

			if ( $rev->hasSlot( $slot ) ) {
				$content = $rev->getContent( $slot );
			} else {
				$content = null;
			}

			if ( $content === null ) {
				// revision not found (or suppressed)
				$text = false;
			} elseif ( !$content instanceof TextContent ) {
				// non-text content
				wfHttpError( 415, "Unsupported Media Type", "The requested page uses the content model `"
					. $content->getModel() . "` which is not supported via this interface." );
				die();
			} else {
				// want a section?
				$section = $request->getIntOrNull( 'section' );
				if ( $section !== null ) {
					$content = $content->getSection( $section );
				}

				if ( $content === null || $content === false ) {
					// section not found (or section not supported, e.g. for JS, JSON, and CSS)
					$text = false;
				} else {
					$text = $content->getText();
				}
			}
		}

		if ( $text !== false && $text !== '' && $request->getRawVal( 'templates' ) === 'expand' ) {
			$text = MediaWikiServices::getInstance()->getParser()->preprocess(
				$text,
				$title,
				ParserOptions::newFromContext( $this->getContext() )
			);
		}

		return $text;
	}
}
