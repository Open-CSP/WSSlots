<?php

namespace WSSlots;

use Config;
use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MWException;
use RequestContext;
use SMW\DIContainer;
use SMW\ParserData;
use SMW\SemanticData;
use SMW\Store;
use WikiPage;
use WSSlots\ParserFunctions\SlotDataParserFunction;
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
	MediaWikiServicesHook,
	ResourceLoaderGetConfigVarsHook,
	BeforeInitializeHook
{
	private const AVAILABLE_ACTION_OVERRIDES = [
		'raw' => 'rawslot'
	];

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
		$parser->setFunctionHook( 'slotdata', [ new SlotDataParserFunction(), 'execute' ] );
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
	 * @inheritDoc
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgWSSlotsDefinedSlots'] = $config->get( 'WSSlotsDefinedSlots' );
		$vars['wgKnownRoles'] = MediaWikiServices::getInstance()->getSlotRoleRegistry()->getKnownRoles();
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWiki ): void {
		$overrides = MediaWikiServices::getInstance()->getMainConfig()->get( "WSSlotsOverrideActions" );
		// We cannot use $mediaWiki->getAction() here, because that reads from the Request and gets cached
		$action = $request->getText( 'action' );

		if ( self::isActionOverridden( $action, $overrides ) && isset( self::AVAILABLE_ACTION_OVERRIDES[$action] ) ) {
			$request->setVal( 'action', self::AVAILABLE_ACTION_OVERRIDES[$action] );
		}
	}

	/**
	 * Allow extensions to add libraries to Scribunto.
	 *
	 * @link https://www.mediawiki.org/wiki/Extension:Scribunto/Hooks/ScribuntoExternalLibraries
	 *
	 * @param string $engine
	 * @param array &$extraLibraries
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
			
			if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) { 
				$wikiPage = MediaWikiServices::getInstance()
					->getWikiPageFactory()
					->newFromTitle( $subjectTitle );
			} else {
				$wikiPage = WikiPage::factory( $subjectTitle );
			}
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

		$mwServices = MediaWikiServices::getInstance();

		foreach ( $semanticSlots as $slot ) {
			if ( !$revision->hasSlot( $slot ) ) {
				continue;
			}

			$content = $revision->getContent( $slot );

			if ( $content === null ) {
				continue;
			}

			if ( method_exists( $mwServices, 'getContentRenderer' ) ) {
				$parserOutput = $mwServices->getContentRenderer()->getParserOutput( $content, $subjectTitle, $revision->getId() );
			} else {
				$parserOutput = $content->getParserOutput( $subjectTitle, $revision->getId() );
			}

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
			// Except for DIContainers, because these _should_ be merged; the subsemanticdata is saved anyway, so deleting their
			// relation to the semantic data is a bad idea.
			foreach ( $slotSemanticData->getProperties() as $property ) {
				if (
					!( $property instanceof DIContainer )
					&& !$property->isUserDefined()
				) {
					$semanticData->removeProperty( $property );
				}
			}

			$semanticData->importDataFrom( $slotSemanticData );
		}

		return true;
	}

	/**
	 * Checks whether the given action is overridden.
	 *
	 * @param string $action
	 * @param bool|array $overrides
	 * @return bool
	 */
	private static function isActionOverridden( string $action, $overrides ): bool {
		if ( is_bool( $overrides ) ) {
			return $overrides;
		}

		if ( is_array( $overrides ) ) {
			return in_array( $action, $overrides, true );
		}

		// Anything else, return false.
		return false;
	}
}
