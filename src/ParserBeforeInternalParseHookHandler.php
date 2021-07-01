<?php

namespace WSSlots;

use Config;
use ConfigException;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Logger\LoggerFactory;
use Parser;
use Psr\Log\LoggerInterface;
use StripState;

/**
 * Class ParserBeforeInternalParseHookHandler
 *
 * This class is the hook handler for the ParserBeforeInternalParse hook. The
 * ParserBeforeInternalParse hook is called at the beginning of Parser::internalParse().
 *
 * @package WSSlots
 */
class ParserBeforeInternalParseHookHandler implements ParserBeforeInternalParseHook {
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * ParserBeforeInternalParseHookHandler constructor.
	 *
	 * @param Config $config
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		$this->logger = LoggerFactory::getInstance( "WSSlots" );
	}

	/**
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $strip_state ) {
		/** @var Parser $parser */
		/** @var string $text */
		/** @var StripState $strip_state */

		$title = $parser->getTitle();

		if ( $title === null ) {
			return true;
		}

		try {
			$wikipage = \WikiPage::factory( $title );
		} catch ( \MWException $exception ) {
			return true;
		}

		$revision_record = $wikipage->getRevisionRecord();

		if ( $revision_record === null ) {
			return true;
		}

		try {
			$append_slots = $this->config->get( "WSSlotsSlotsToAppend" );
			if ( !is_array( $append_slots ) ) {
				throw new ConfigException();
			}
			/** @var array $append_slots */
		} catch ( ConfigException $exception ) {
			$this->logger->critical( wfMessage( "wsslots-invalid-append-slots-config" ) );
			$append_slots = [];
		}

		foreach ( $append_slots as $slot_name ) {
			if ( !is_string( $slot_name ) ) {
				continue;
			}

			if ( !$revision_record->hasSlot( $slot_name ) ) {
				continue;
			}

			$slot = $revision_record->getSlot( $slot_name );
			$slot_wikitext = $slot->getContent()->getWikitextForTransclusion();

			if ( $slot_wikitext === false ) {
				continue;
			}

			$text .= $slot_wikitext;
		}

		return true;
	}
}
