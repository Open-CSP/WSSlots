<?php

namespace WSSlots;

use ComplexArray;
use Error;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MWException;
use Parser;
use RequestContext;
use TextContent;
use WikibaseSolutions\MediaWikiTemplateParser\Parser as TemplateParser;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use WikiCategoryPage;
use WikiFilePage;
use WikiPage;

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
	 * @param string|null $parse
	 * @return string|array
	 */
	public static function getSlotContent( Parser $parser, string $slot_name, string $page_name = null, string $parse = null ) {
		$wikipage = self::getWikiPage( $page_name );

		if ( !$wikipage ) {
			return '';
		}

		$content_object = WSSlots::getSlotContent($wikipage, $slot_name);

		if ( $content_object === null ) {
			return '';
		}

		if ( !( $content_object instanceof TextContent ) ) {
			return '';
		}

		$content = $content_object->serialize();

		if ( $parse ) {
			return [ $content, 'noparse' => false ];
		} else {
			return $content;
		}
	}

	/**
	 * Hook handler for the #slottemplates parser hook.
	 *
	 * @param Parser $parser
	 * @param string $slot_name
	 * @param string|null $page_name
	 * @param string|null $array_name
	 * @return string
	 */
	public static function getSlotTemplates( Parser $parser, string $slot_name, string $page_name = null, string $array_name = null, string $recursive = null ): string {
		if ( !class_exists( "\ComplexArray" ) ) {
			return 'ComplexArrays is required for this functionality.';
		}

		if ( !$page_name || !$array_name ) {
			return 'Missing page or array name';
		}

		$wikipage = self::getWikiPage( $page_name );

		if ( !$wikipage ) {
			return 'Invalid page name ' . $page_name;
		}

		$content_object = WSSlots::getSlotContent($wikipage, $slot_name);

		if ( !$content_object instanceof TextContent ) {
			return 'The content model for the page ' . $page_name . ' is not text';
		}

		$slot_content = $content_object->serialize();

		if ( !empty( $recursive ) ) {
			try {
				$parsed_content = (new RecursiveParser())->parse( $slot_content );
			} catch ( Error $error ) {
				return 'Max recursion depth reached, aborted.';
			}
		} else {
			$parsed_content = (new TemplateParser())->parseArticle( $slot_content );
		}

		// Create a new WSArray with the parsed content
		$GLOBALS['wfDefinedArraysGlobal'][$array_name] = new ComplexArray( $parsed_content );

		return '';
	}

	/**
	 * @param string|null $page_name
	 * @return false|WikiCategoryPage|WikiFilePage|WikiPage|null
	 * @throws MWException
	 */
	private static function getWikiPage( string $page_name = null ) {
		if ( !$page_name ) {
			try {
				return RequestContext::getMain()->getWikiPage();
			} catch (MWException $exception) {
				return false;
			}
		}

		$title = \Title::newFromText( $page_name );

		if ( $title === null || !$title->exists() ) {
			return false;
		}

		return WikiPage::factory( $title );
	}
}
