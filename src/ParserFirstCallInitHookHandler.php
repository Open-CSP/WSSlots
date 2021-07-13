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
		$parser->setFunctionHook( 'slottemplates', [ self::class, 'getSlotTemplates' ] );
	}

	/**
	 * Hook handler for the #slot parser hook.
	 *
	 * @param Parser $parser
	 * @param string $slot_name
	 * @param string|null $page_name
	 * @return string
	 */
	public static function getSlotContent( Parser $parser, string $slot_name, string $page_name = null ): string {
		$wikipage = self::getWikiPage( $page_name );

		if ( !$wikipage ) {
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

	/**
	 * Hook handler for the #slottemplates parser hook.
	 *
	 * @param Parser $parser
	 * @param string $slot_name
	 * @param string|null $page_name
	 * @param string $array_name
	 * @return string
	 */
	public static function getSlotTemplates( Parser $parser, string $slot_name, string $page_name = null, string $array_name = null ): string {
		if ( !class_exists( "\ComplexArray" ) ) {
			return "";
		}

		$wikipage = self::getWikiPage( $page_name );

		if ( !$wikipage ) {
			return "";
		}

		$content_object = WSSlots::getSlotContent($wikipage, $slot_name);

		if ( $content_object === null ) {
			return "";
		}

		if ( !( $content_object instanceof TextContent ) ) {
			return "";
		}

		$slot_content = $content_object->serialize();
		$parsed_content = (new ArticleParser())->parseArticle($slot_content);

		// Create a new WSArray with the parsed content
		$GLOBALS['wfDefinedArraysGlobal'][$array_name] = new \ComplexArray( $parsed_content );

		return "";
	}

	private static function getWikiPage( string $page_name = null ) {
		if ( !$page_name ) {
			try {
				return RequestContext::getMain()->getWikiPage();
			} catch (MWException $exception) {
				return false;
			}
		}

		$title = \Title::newFromText( $page_name );

		if ( !$title->exists() ) {
			return false;
		}

		return \WikiPage::factory( $title );
	}
}
