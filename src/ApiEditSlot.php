<?php

namespace WSSlots;

use ApiBase;
use ApiMain;
use ApiUsageException;
use Content;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Storage\SlotRecord;
use TextContent;
use Title;
use User;
use Wikimedia\ParamValidator\ParamValidator;
use WikiPage;
use WikiRevision;

/**
 * A slot-aware module that allows for editing and creating pages.
 */
class ApiEditSlot extends ApiBase {
	/**
	 * ApiEditSlot constructor.
	 *
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
	}

	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws \MWContentSerializationException
	 * @throws \MWException
	 */
	public function execute() {
		$this->useTransactionalTimeLimit();

		/** @var User $user */
		$user = $this->getUser();

		/** @var array $params */
		$params = $this->extractRequestParams();

		/** @var WikiPage $wikipage_object */
		$wikipage_object = $this->getTitleOrPageId( $params );

		/** @var Title $title_object */
		$title_object = $wikipage_object->getTitle();

		/** @var SlotRoleRegistry $slot_role_registery */
		$slot_role_registery = MediaWikiServices::getInstance()->getSlotRoleRegistry();

		if ( !$slot_role_registery->isDefinedRole( $params["slot"] ) ) {
			$this->dieWithError( wfMessage( "wsslots-apierror-unknownslot", $params["slot"] ), "unknownslot" );
		}

		// Check if we are allowed to edit or create this page
		$this->checkTitleUserPermissions(
			$title_object,
			$title_object->exists() ? 'edit' : [ 'edit', 'create' ],
  			[ 'autoblock' => true ]
  		);

		if ( $params["append"] ) {
			// We want to append the given text to the current page, instead of replacing the content
			$content = $this->getSlotContent( $wikipage_object, $params["slot"] );

			if ( $content !== null ) {
				if ( !( $content instanceof TextContent ) ) {
					$slot_content_handler = $content->getContentHandler();
					$model_id = $slot_content_handler->getModelID();
					$this->dieWithError( [ 'apierror-appendnotsupported', $model_id ] );
				}

				/** @var string $text */
				$text = $content->serialize();
				$params["text"] = $text . $params["text"];
			}
		}

		$wiki_revision = new WikiRevision( MediaWikiServices::getInstance()->getMainConfig() );
		$revision_record = $wikipage_object->getRevisionRecord();

		// Set the main and other slots for this revision
		if ( $revision_record !== null ) {
			$main_content = $revision_record->getContent( SlotRecord::MAIN );
			$wiki_revision->setContent( SlotRecord::MAIN, $main_content );

			// Set the content for any other slots the page may have
			$additional_slots = $revision_record->getSlots()->getSlots();
			foreach ( $additional_slots as $slot ) {
				if ( !$slot_role_registery->isDefinedRole( $slot->getRole() ) ) {
					// Prevent "Undefined slot role" error when editing a page that has an undefined slot
					continue;
				}

				$wiki_revision->setContent( $slot->getRole(), $slot->getContent() );
			}
		} else {
			$main_content = ContentHandler::makeContent( "", $title_object );
			$wiki_revision->setContent( SlotRecord::MAIN, $main_content );
		}

		// Set the content for the slot we want to edit
		if ( $revision_record->hasSlot( $params["slot"] ) ) {
			$slot = $revision_record->getSlot( $params["slot"] );
			$slot_content_handler = $slot->getContent()->getContentHandler();
			$model_id = $slot_content_handler->getModelID();
			$slot_content = ContentHandler::makeContent( $params["text"], $title_object, $model_id );
			$wiki_revision->setContent( $params["slot"], $slot_content );
		} else {
			$role_handler = $slot_role_registery->getRoleHandler( $params["slot"] );
			$model_id = $role_handler->getDefaultModel( $title_object );
			$slot_content = ContentHandler::makeContent( $params["text"], $title_object, $model_id );
			$wiki_revision->setContent( $params["slot"], $slot_content );
		}

		$wiki_revision->setTitle( $title_object );
		$wiki_revision->setComment( $params["summary"] );
		$wiki_revision->setTimestamp( wfTimestampNow() );
		$wiki_revision->setUserObj( $user );

		MediaWikiServices::getInstance()
			->getWikiRevisionOldRevisionImporter()
			->import( $wiki_revision );
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'title' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'text' => [
				ApiBase::PARAM_TYPE => 'text',
				ApiBase::PARAM_REQUIRED => true
			],
			'slot' => [
				ApiBase::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => SlotRecord::MAIN
			],
			'append' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'summary' => [
				ApiBase::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => ""
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		//return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=editslot&title=Test&summary=test%20summary&' .
			'text=article%20content&token=123ABC'
			=> 'apihelp-edit-example-edit'
		];
	}

	/**
	 * @param WikiPage $wikipage
	 * @param string $slot
	 * @return Content|null The content in the given slot, or NULL if no content exists
	 */
	private function getSlotContent( WikiPage $wikipage, string $slot ) {
		$revision_record = $wikipage->getRevisionRecord();

		if ( $revision_record === null ) {
			return null;
		}

		if ( !$revision_record->hasSlot( $slot ) ) {
			return null;
		}

		return $revision_record->getContent( $slot );
	}
}
