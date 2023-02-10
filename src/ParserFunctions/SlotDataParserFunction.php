<?php

namespace WSSlots\ParserFunctions;

use ArrayFunctions\Utils;
use Error;
use ExtensionRegistry;
use FormatJson;
use JsonPath\InvalidJsonException;
use JsonPath\InvalidJsonPathException;
use JsonPath\JsonObject;
use MediaWiki\MediaWikiServices;
use MWException;
use Parser;
use TextContent;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use WSSlots\WikiPageTrait;
use WSSlots\WSSlots;

/**
 * Handles the #slotdata parser function.
 */
class SlotDataParserFunction {
	use WikiPageTrait;

	/**
	 * Execute the parser function.
	 *
	 * @param Parser $parser
	 * @param string $slotName
	 * @param string|null $pageName
	 * @param string|null $key
	 * @param string|null $search
	 * @return string|array
	 * @throws MWException
	 */
	public function execute( Parser $parser, string $slotName, string $pageName = null, string $key = null, string $search = null, bool $arrayFunctionsCompat = null ) {
		if ( !$pageName ) {
			return '';
		}

		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return '';
		}

        $userCan = MediaWikiServices::getInstance()->getPermissionManager()->userCan(
            'read',
            $parser->getUser(),
            $wikiPage->getTitle()
        );

        if ( !$userCan ) {
            // The user is not allowed to read the page
            return '';
        }

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !$contentObject instanceof TextContent ) {
			return '';
		}

		if ( $contentObject instanceof \JsonContent ) {
			$result = $this->handleJSON( $contentObject->getText() );
		} elseif ( $contentObject instanceof \WikitextContent ) {
			$result = $this->handleWikitext( $contentObject->getText() );
		} else {
			return '';
		}

		if ( $result === null ) {
			return '';
		}

		if ( !empty( $search ) ) {
			$searchParts = explode( '=', $search, 2 );

			if ( count( $searchParts ) < 2 ) {
				return null;
			}

			$result = $this->findBlockByValue( trim( $searchParts[0] ), trim( $searchParts[1] ), $result );

			if ( $result === null ) {
				return null;
			}
		}

		if ( !empty( $key ) ) {
			$result = $this->findBlockByPath( $key, $result );
		}

		if (
			filter_var( $arrayFunctionsCompat, FILTER_VALIDATE_BOOLEAN ) &&
			ExtensionRegistry::getInstance()->isLoaded( 'ArrayFunctions' )
		) {
			$result = Utils::export( $result );
		} else {
			$result = is_array( $result ) ? json_encode( $result ) : strval( $result );
		}

		return [ $result, 'noparse' => true ];
	}

	/**
	 * Handles JSON content.
	 *
	 * @param string $content The JSON content
	 * @return array|null
	 */
	private function handleJSON( string $content ): ?array {
		$content = FormatJson::parse(
			$content,
			FormatJson::FORCE_ASSOC | FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS
		);

		if ( !$content->isGood() ) {
			return null;
		}

		return $content->getValue();
	}

	/**
	 * Handles wikitext content.
	 *
	 * @param string $content The wikitext content
	 * @return array|null
	 */
	private function handleWikitext( string $content ): ?array {
		try {
			$content = ( new RecursiveParser() )->parse( $content );
		} catch ( Error $error ) {
			return null;
		}

		return $content;
	}

	/**
	 * Returns the value associated with the given key, or NULL if it does not exist.
	 *
	 * @param string $path A JSON-path
	 * @param array $array The array to search in
	 * @return mixed
	 */
	private function findBlockByPath( string $path, array $array ) {
		if ( substr( $path, 0, 2 ) !== '$.' ) {
			$path = '$.' . $path;
		}

		try {
			$jsonObject = new JsonObject( $array );
			return $jsonObject->get( $path );
		} catch ( InvalidJsonException | InvalidJsonPathException $exception ) {
			return null;
		}
	}

	/**
	 * Finds the earliest block in an array that has a key $key with value $value. This function returns the block if
	 * it is found, or NULL otherwise.
	 *
	 * @param string $key The key that contains $value
	 * @param mixed $value The value to search for
	 * @param array $array The array to search in
	 * @return array|null
	 */
	private function findBlockByValue( string $key, $value, array $array ): ?array {
		// Loose comparison to support numerical values
		if ( isset( $array[$key] ) && $array[$key] == $value ) {
			return $array;
		}

		foreach ( $array as $subarray ) {
			if ( !is_array( $subarray ) ) {
				continue;
			}

			$result = $this->findBlockByValue( $key, $value, $subarray );
			if ( $result !== null ) {
				return $result;
			}
		}

		return null;
	}
}
