<?php

namespace WSSlots\ParserFunctions;

use FormatJson;
use MWException;
use Parser;
use TextContent;
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
	public function execute( Parser $parser, string $slotName, string $pageName = null, string $key = null, string $search = null ) {
		if ( !$pageName ) {
			return '';
		}

		$wikiPage = $this->getWikiPage( $pageName );

		if ( !$wikiPage ) {
			return '';
		}

		$contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

		if ( !$contentObject instanceof TextContent ) {
			return '';
		}

		$result = $this->handleJSON( $contentObject->getText(), (string)$key, $search );

		if ( $result === null ) {
			return '';
		}

		return [ $result, 'noparse' => true ];
	}

	/**
	 * Handles JSON content.
	 *
	 * @param string $content The JSON content
	 * @param string $key The key to search
	 * @param string|null $search
	 * @return string|null
	 */
	private function handleJSON( string $content, string $key, ?string $search ): ?string {
		$content = FormatJson::parse(
			$content,
			FormatJson::FORCE_ASSOC | FormatJson::TRY_FIXING | FormatJson::STRIP_COMMENTS
		);

		if ( !$content->isGood() ) {
			return null;
		}

		$content = $content->getValue();

		if ( $search !== null ) {
			$searchParts = explode( '=', $search, 2 );

			if ( count( $searchParts ) < 2 ) {
				return null;
			}

			$content = $this->findBlockByValue(
				trim( $searchParts[0] ),
				trim( $searchParts[1] ),
				$content
			);

			if ( $content === null ) {
				return null;
			}
		}

		$value = $this->findValueByKey( $key, $content );

		return is_array( $value ) ?
			json_encode( $value ) :
			strval( $value );
	}

	/**
	 * Returns the value associated with the given key, or NULL if it does not exist.
	 *
	 * @param string $key A dot-separated list of array indices (i.e. foo.bar.quz for $array["foo"]["bar"]["quz"])
	 * @param array $array The array to search in
	 * @return mixed
	 */
	private function findValueByKey( string $key, array $array ) {
		$parts = $key === '' ? [] : explode( '.', $key );

		foreach ( $parts as $part ) {
			if ( preg_match( '/\d+/', $part ) ) {
				$part = intval( $part );
			}

			if ( !isset( $array[$part] ) ) {
				return null;
			}

			$array = $array[$part];
		}

		return $array;
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
