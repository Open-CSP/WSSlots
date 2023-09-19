<?php

namespace WSSlots\Scribunto;

use Error;
use FormatJson;
use JsonContent;
use MWException;
use TextContent;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use WSSlots\UserCanTrait;
use WSSlots\WikiPageTrait;
use WSSlots\WSSlots;

/**
 * Register the Lua library.
 */
class ScribuntoLuaLibrary extends \Scribunto_LuaLibraryBase {
	use UserCanTrait;
	use WikiPageTrait;

	/**
	 * @inheritDoc
	 */
	public function register(): void {
		$interfaceFuncs = [
			'slotContent' => [ $this, 'slotContent' ],
			'slotTemplates' => [ $this, 'slotTemplates' ],
			'slotContentModel' => [ $this, 'slotContentModel' ],
			'slotData' => [ $this, 'slotData' ]
		];

		$this->getEngine()->registerInterface( __DIR__ . '/' . 'mw.wsslots.lua', $interfaceFuncs, [] );
	}

	/**
	 * This mirrors the functionality of the #slot parser function and makes it available in Lua.
	 *
	 * @param string $slotName
	 * @param string|null $pageName
	 * @return array
	 * @throws MWException
	 */
	public function slotContent( string $slotName, ?string $pageName = null ): array {
		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return [ null ];
		}

		if ( !$this->userCan( $wikiPage ) ) {
			// The user is not allowed to read the page
			return [ null ];
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !$contentObject instanceof TextContent ) {
			return [ null ];
		}

		return [ $contentObject->serialize() ];
	}

	/**
	 * This mirrors the functionality of the #slottemplates parser function and makes it available
	 * in Lua.
	 *
	 * @param string $slotName
	 * @param string|null $pageName
	 * @return array
	 * @throws MWException
	 */
	public function slotTemplates( string $slotName, ?string $pageName = null ): array {
		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return [ null ];
		}

		if ( !$this->userCan( $wikiPage ) ) {
			// The user is not allowed to read the page
			return [ null ];
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !$contentObject instanceof TextContent ) {
			return [ null ];
		}

		try {
			return [ $this->convertToLuaTable( ( new RecursiveParser() )->parse( $contentObject->serialize() ) ) ];
		} catch ( Error $error ) {
			return [ null ];
		}
	}

	/**
	 * This mirrors the functionality of the #slotdata parser function and makes it available
	 * in Lua. This function omits any advanced selection tools (JSONPath).
	 *
	 * @param string $slotName
	 * @param string|null $pageName
	 * @return array
	 * @throws MWException
	 */
	public function slotData( string $slotName, ?string $pageName = null ): array {
		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return [ null ];
		}

		if ( !$this->userCan( $wikiPage ) ) {
			// The user is not allowed to read the page
			return [ null ];
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( $contentObject instanceof JsonContent ) {
			$result = FormatJson::parse(
				$contentObject->serialize(),
				FormatJson::FORCE_ASSOC | FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS
			);

			if ( !$result->isGood() ) {
				return [ null ];
			}

			$result = $result->getValue();
		} elseif ( $contentObject instanceof \WikitextContent ) {
			try {
				$result = ( new RecursiveParser() )->parse( $contentObject->serialize() );
			} catch ( Error $error ) {
				return [ null ];
			}
		} else {
			return [ null ];
		}

		return [ $this->convertToLuaTable( $result ) ];
	}

	/**
	 * Returns the content model of the specified slot.
	 *
	 * @param string $slotName
	 * @param string|null $pageName
	 * @return array
	 * @throws MWException
	 */
	public function slotContentModel( string $slotName, ?string $pageName = null ): array {
		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return [ null ];
		}

		if ( !$this->userCan( $wikiPage ) ) {
			// The user is not allowed to read the page
			return [ null ];
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !$contentObject instanceof TextContent ) {
			return [ null ];
		}

		return [ $contentObject->getModel() ];
	}

	/**
	 * @param $array
	 * @return mixed
	 */
	private function convertToLuaTable( $array ) {
		if ( is_array( $array ) ) {
			foreach ( $array as $key => $value ) {
				$array[$key] = $this->convertToLuaTable( $value );
			}

			array_unshift( $array, '' );
			unset( $array[0] );
		}

		return $array;
	}
}
