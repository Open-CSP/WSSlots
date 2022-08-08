<?php

namespace WSSlots;

use ApiBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\RevisionRecord;
use MediaWiki\Storage\SlotRecord;
use MWException;
use WikibaseSolutions\MediaWikiTemplateParser\RecursiveParser;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * A slot-aware module that allows you to parse revisions and return the parse tree.
 */
class ApiParseTemplates extends ApiBase {
	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws MWException
	 */
	public function execute() {
		$this->useTransactionalTimeLimit();

        $params = $this->extractRequestParams();
        $revision = $this->getRevisionOrDie( $params );

		// Check if we are allowed to view the page to which the revision belongs
		$this->checkTitleUserPermissions( $revision->getPageAsLinkTarget(), 'read', [ 'autoblock' => true ] );

        $content = $revision->getContent( $params['slot'] );

        if ( !$content instanceof \WikitextContent ) {
            $this->dieWithError( 'wsslots-cannot-parse-model', $content->getModel() );
        }

        $parseTree = ( new RecursiveParser() )->parse( $content->getText() );
        $this->getResult()->addValue( null, 'tree', json_encode( $parseTree ) );
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted(): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode(): bool {
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
            'oldid' => [
                ApiBase::PARAM_TYPE => 'integer'
            ],
			'slot' => [
				ApiBase::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => SlotRecord::MAIN
			]
		];
	}

    private function getRevisionOrDie( array $params ): RevisionRecord {
        if ( isset( $params['oldid'] ) ) {
            // Since we know 'oldid' is set, if more than one of these parameter is set, we should give an error
            $this->requireMaxOneParameter( $params, 'title', 'pageid', 'oldid' );

            $revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
            $revision = $revisionStore->getRevisionById( $params['oldid'] );
        } else {
            $wikiPage = $this->getTitleOrPageId( $params );
            $revision = $wikiPage->getRevisionRecord();
        }

        if ( $revision === null ) {
            // No such revision
            $this->dieWithError( 'wsslots-no-such-revision' );
        }

        return $revision;
    }
}
