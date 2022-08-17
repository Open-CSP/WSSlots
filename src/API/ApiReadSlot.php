<?php

namespace WSSlots\API;

use ApiBase;
use ApiQueryBase;
use ApiUsageException;
use MediaWiki\Storage\SlotRecord;
use MWException;
use TextContent;
use Wikimedia\ParamValidator\ParamValidator;
use WSSlots\WSSlots;

/**
 * A slot-aware module that allows for reading pages.
 */
class ApiReadSlot extends ApiBase {
    /**
     * @inheritDoc
     *
     * @throws ApiUsageException
     * @throws MWException
     */
    public function execute() {
        $this->useTransactionalTimeLimit();

        $params = $this->extractRequestParams();
        $wikiPage = $this->getTitleOrPageId( $params );
        $title = $wikiPage->getTitle();

        // Check if we are allowed to read this page
        $this->checkTitleUserPermissions( $title, 'read', [ 'autoblock' => true ] );

        $content = WSSlots::getSlotContent( $wikiPage, $params["slot"] );

        if ( $content === null ) {
            $this->dieWithError( wfMessage( "wsslots-apierror-slotdoesnotexist", $params['slot'], $title->getFullText() ), "slotdoesnotexist" );
        }

        if ( !$content instanceof TextContent ) {
            $this->dieWithError( wfMessage( "wsslots-apierror-nottext", $content->getModel() ), "nottext" );
        }

        $this->getResult()->addValue( null, 'result', $content->getText() );
    }

    /**
     * @inheritDoc
     */
    public function isReadMode(): bool {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedParams(): array {
        return [
            'title' => [
                ApiBase::PARAM_TYPE => 'string'
            ],
            'pageid' => [
                ApiBase::PARAM_TYPE => 'integer'
            ],
            'slot' => [
                ApiBase::PARAM_TYPE => 'text',
                ParamValidator::PARAM_DEFAULT => SlotRecord::MAIN
            ]
        ];
    }
}
