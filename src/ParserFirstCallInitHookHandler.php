<?php

namespace WSSlots;

use MediaWiki\Hook\ParserFirstCallInitHook;
use MWException;
use Parser;
use RequestContext;
use TextContent;

/**
 * Class ParserFirstCallInitHookHandler
 *
 * This class is the hook handler for the ParserFirstCallInit hook. The
 * ParserFirstCallInit hook is called when the parser initializes for the first
 * time.
 *
 * @package WSSlots
 */
class ParserFirstCallInitHookHandler implements ParserFirstCallInitHook {
	/**
	 * @inheritDoc
	 * @throws MWException
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'slot', [ self::class, 'getSlotContent' ] );
	}

	/**
	 * Hook handler for the #slot parser hook.
	 *
	 * @param Parser $parser
	 * @param string $slot_name
	 * @return string
	 */
	public static function getSlotContent( Parser $parser, string $slot_name ): string {
		try {
			$wikipage = RequestContext::getMain()->getWikiPage();
		} catch (MWException $exception) {
			return "";
		}

		$content_object = WSSlots::getSlotContent($wikipage, $slot_name);

		if ( $content_object === null ) {
			return "";
		}

		if ( !( $content_object instanceof TextContent ) ) {
			return "";
		}

		return $content_object->serialize();
	}
}
