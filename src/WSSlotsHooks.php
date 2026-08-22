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
use RequestContext;
use SMW\ParserData;
use SMW\SemanticData;
use SMW\Store;
use SMWDIContainer;
use Throwable;
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

		if ( $subjectTitle === null || !$subjectTitle->canExist() ) {
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
		} catch ( Throwable $exception ) {
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
				if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
					$parserOutput = $mwServices->getContentRenderer()->getParserOutput(
						$content,
						$subjectTitle,
						$revision
					);
				} else {
					// MW 1.39-1.42
					$parserOutput = $mwServices->getContentRenderer()->getParserOutput(
						$content,
						$subjectTitle,
						$revision->getId()
					);
				}
			} else {
				// MW 1.35-1.38
				$parserOutput = $content->getParserOutput( $subjectTitle, $revision->getId() );
			}

			/** @var SemanticData $slotSemanticData */
			$slotSemanticData = $parserOutput->getExtensionData( ParserData::DATA_ID );

			if ( $slotSemanticData === null ) {
				continue;
			}

			if ( !$semanticData->getSubject()->equals( $slotSemanticData->getSubject() ) ) {
				// This would throw an exception in "importDataFrom" otherwise
				// TODO: Figure out the root cause of why the subject of a slot does not equal
				//       the subject of the main slot
				continue;
			}

			foreach ( $slotSemanticData->getProperties() as $property ) {
                if ( $property->getKey() === '_SKEY' ) {
					// Remove the sortkey from the slot semantic data
                    $slotSemanticData->removeProperty( $property );
                } elseif ( !( $property instanceof SMWDIContainer ) && !$property->isUserDefined() ) {
					// Remove any other pre-defined properties from the main semantic data
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
