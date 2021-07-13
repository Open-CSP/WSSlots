<?php

namespace WSSlots;

use ApiBase;
use ApiMain;
use ApiUsageException;
use MediaWiki\Storage\SlotRecord;
use Title;
use User;
use Wikimedia\ParamValidator\ParamValidator;
use WikiPage;

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

		// Check if we are allowed to edit or create this page
		$this->checkTitleUserPermissions(
			$title_object,
			$title_object->exists() ? 'edit' : [ 'edit', 'create' ],
  			[ 'autoblock' => true ]
  		);

		$result = WSSlots::editSlot(
			$user,
			$wikipage_object,
			$params["text"],
			$params["slot"],
			$params["summary"],
			$params["append"]
		);

		if ($result !== true) {
			list($message, $code) = $result;
			$this->dieWithError($message, $code);
		}
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
}
