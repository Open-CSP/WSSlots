<?php

namespace WSSlots;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MWException;
use RequestContext;
use SMW\ParserData;
use SMW\SemanticData;
use SMW\Store;
use WikiPage;
use WSSlots\ParserFunctions\SlotParserFunction;
use WSSlots\ParserFunctions\SlotTemplatesParserFunction;
use WSSlots\Scribunto\ScribuntoLuaLibrary;
use WSSlots\ServiceManipulators\SlotRoleRegistryServiceManipulator;

/**
 * Hook handler for WSSlots.
 */
class WSSlotsHooks implements
	ListDefinedTagsHook,
	ChangeTagsListActiveHook,
	ParserFirstCallInitHook,
	MediaWikiServicesHook
{
	/**
	 * @inheritDoc
	 */
	public function onListDefinedTags( &$tags ) {
		$tags[] = 'wsslots-slot-edit';
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		$tags[] = 'wsslots-slot-edit';
	}

	/**
	 * @inheritDoc
	 *
	 * @throws MWException
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'slot', [ new SlotParserFunction(), 'execute' ] );
		$parser->setFunctionHook( 'slottemplates', [ new SlotTemplatesParserFunction(), 'execute' ] );
	}

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		$config = $services->getMainConfig();

		$serviceManipulator = new SlotRoleRegistryServiceManipulator( $config );
		$manipulator = [ $serviceManipulator, "defineRoles" ];
		$services->addServiceManipulator( "SlotRoleRegistry", $manipulator );
	}

	/**
	 * Allow extensions to add libraries to Scribunto.
	 *
	 * @link https://www.mediawiki.org/wiki/Extension:Scribunto/Hooks/ScribuntoExternalLibraries
	 *
	 * @param string $engine
	 * @param array $extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( string $engine, array &$extraLibraries ): bool {
		if ( $engine !== 'lua' ) {
			// Don't mess with other engines
			return true;
		}

		$extraLibraries['slots'] = ScribuntoLuaLibrary::class;

		return true;
	}

	/**
	 * Hook to extend the SemanticData object before the update is completed.
	 *
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/technical/hooks/hook.store.beforedataupdatecomplete.md
	 *
	 * @param Store $store
	 * @param SemanticData $semanticData
	 * @return bool
	 */
	public static function onBeforeDataUpdateComplete( Store $store, SemanticData $semanticData ): bool {
		$subjectTitle = $semanticData->getSubject()->getTitle();

		if ( $subjectTitle === null ) {
			return true;
		}

		$semanticSlots = RequestContext::getMain()->getConfig()->get( 'WSSlotsSemanticSlots' );

		try {
			$wikiPage = WikiPage::factory( $subjectTitle );
		} catch ( MWException $exception ) {
			return true;
		}

		if ( !$wikiPage instanceof WikiPage ) {
			// Page does not exist (anymore)
			return true;
		}

		$revision = $wikiPage->getRevisionRecord();

		if ( $revision === null ) {
			// Page does not exist (anymore)
			return true;
		}

		foreach ( $semanticSlots as $slot ) {
			if ( !$revision->hasSlot( $slot ) ) {
				continue;
			}

			$content = $revision->getContent( $slot );

			if ( $content === null ) {
				continue;
			}

			$parserOutput = $content->getParserOutput( $subjectTitle, $revision->getId() );

			/** @var SemanticData $slotSemanticData */
			$slotSemanticData = $parserOutput->getExtensionData( ParserData::DATA_ID );

			if ( $slotSemanticData === null ) {
				continue;
			}

			if ( !$semanticData->getSubject()->equals( $slotSemanticData->getSubject() ) ) {
				// This would throw an exception in "importDataFrom" otherwise
				// TODO: Figure out the root cause of why the subject of a slot does not equal the subject of the main slot
				continue;
			}

			// Remove any pre-defined properties that exist in both the main semantic data as well as the slot semantic
			// data from the main semantic data to prevent them from merging
			foreach ( $slotSemanticData->getProperties() as $property ) {
				if ( !$property->isUserDefined() ) {
					$semanticData->removeProperty( $property );
				}
			}

			$semanticData->importDataFrom( $slotSemanticData );
		}

		return true;
	}
}
