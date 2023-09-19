<?php

namespace WSSlots\ParserFunctions;

use MediaWiki\MediaWikiServices;
use MWException;
use Parser;
use TextContent;
use WSSlots\UserCanTrait;
use WSSlots\WikiPageTrait;
use WSSlots\WSSlots;

/**
 * Handles the #slot parser function.
 */
class SlotParserFunction {
    use UserCanTrait;
	use WikiPageTrait;

	/**
	 * Execute the parser function.
	 *
	 * @param Parser $parser
	 * @param string $slotName
	 * @param string|null $pageName
	 * @param string|null $parse
	 * @return string|array
	 * @throws MWException
	 */
	public function execute( Parser $parser, string $slotName, string $pageName = null, string $parse = null ) {
		if ( !$pageName ) {
			return '';
		}

		$wikiPage = $this->getWikiPage( $pageName );

		if ( $wikiPage === null ) {
			return '';
		}

		if ( !$this->userCan( $wikiPage ) ) {
			// The user is not allowed to read the page
			return '';
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !( $contentObject instanceof TextContent ) ) {
			return '';
		}

		return $parse ?
			[ $contentObject->serialize(), 'noparse' => false ] :
			$contentObject->serialize();
	}
}
