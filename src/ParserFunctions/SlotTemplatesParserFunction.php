<?php

namespace WSSlots\ParserFunctions;

use ComplexArray;
use Error;
use MWException;
use Parser;
use TextContent;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use WSSlots\WikiPageTrait;
use WSSlots\WSSlots;
use WikibaseSolutions\MediaWikiTemplateParser\Parser as DeprecatedParser;

/**
 * Handles the #slottemplates parser function.
 */
class SlotTemplatesParserFunction {
    use WikiPageTrait;

    /**
     * Execute the parser function.
     *
     * @param Parser $parser
     * @param string $slotName
     * @param string|null $pageName
     * @param string|null $arrayName
     * @param string|null $recursive
     * @return string
     * @throws MWException
     */
    public function execute( Parser $parser, string $slotName, string $pageName = null, string $arrayName = null, string $recursive = null ): string {
        if ( !class_exists( "\ComplexArray" ) ) {
            return 'ComplexArrays is required for this functionality.';
        }

        if ( !$pageName || !$arrayName ) {
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

        if ( $recursive ) {
            try {
                $parsedContent = ( new RecursiveParser() )->parse( $contentObject->serialize() );
            } catch ( Error $error ) {
                return 'Max recursion depth reached, aborted.';
            }
        } else {
            $parsedContent = ( new DeprecatedParser() )->parseArticle( $contentObject->serialize() );
        }

        // Create a new WSArray with the parsed content
        $GLOBALS['wfDefinedArraysGlobal'][$arrayName] = new ComplexArray($parsedContent);

        return '';
    }
}