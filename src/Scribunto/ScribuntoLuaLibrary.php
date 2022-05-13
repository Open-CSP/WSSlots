<?php

namespace WSSlots\Scribunto;

use MWException;
use TextContent;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use WSSlots\WikiPageTrait;
use WSSlots\WSSlots;

/**
 * Register the Lua library.
 */
class ScribuntoLuaLibrary extends \Scribunto_LuaLibraryBase {
    use WikiPageTrait;

    /**
     * @inheritDoc
     */
    public function register(): void {
        $interfaceFuncs = [
            'slotTemplates' => [ $this, 'slotTemplates' ]
        ];

        $this->getEngine()->registerInterface( __DIR__ . '/' . 'mw.wsslots.lua', $interfaceFuncs, [] );
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

        $contentObject = WSSlots::getSlotContent( $wikiPage, $slotName );

        if ( !$contentObject instanceof TextContent ) {
            return [ null ];
        }

        return [ $this->convertToLuaTable( ( new RecursiveParser() )->parse( $contentObject->serialize() ) ) ];
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