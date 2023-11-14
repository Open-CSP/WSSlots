<?php

namespace WSSlots\API;

use ApiBase;
use ApiUsageException;
use MediaWiki\Revision\SlotRecord;
use MWContentSerializationException;
use MWException;
use Wikimedia\ParamValidator\ParamValidator;
use WSSlots\Logger;
use WSSlots\WSSlots;

/**
 * A slot-aware module that allows for editing and creating pages.
 */
class ApiEditSlot extends ApiBase {
	/**
	 * @inheritDoc
	 *
	 * @throws ApiUsageException
	 * @throws MWContentSerializationException
	 * @throws MWException
	 */
	public function execute() {
		$this->useTransactionalTimeLimit();

		$user = $this->getUser();
		$params = $this->extractRequestParams();
		$wikiPage = $this->getTitleOrPageId( $params );
		$title = $wikiPage->getTitle();

		// Check if we are allowed to edit or create this page
		$this->checkTitleUserPermissions(
			$title,
			$title->exists() ? 'edit' : [ 'edit', 'create' ],
			[ 'autoblock' => true ]
		);

		$result = WSSlots::editSlot(
			$user,
			$wikiPage,
			$params["text"] ?? "",
			$params["slot"],
			$params["summary"],
			$params["append"],
			$params["watchlist"],
			$params["prepend"],
			$params["bot"],
			$params["minor"],
			$params["createonly"],
			$params["nocreate"],
			$params["suppress"]
		);

		if ( $result !== true ) {
			[ $message, $code ] = $result;

			Logger::getLogger()->alert( 'Editing slot failed while performing edit through the "editslot" API: {message}', [
				'message' => $message
			] );

			$this->dieWithError( $message, $code );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams(): array {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string'
			],
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'text'
			],
			'slot' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => SlotRecord::MAIN
			],
			'append' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'prepend' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'summary' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => ""
			],
			'watchlist' => [
				ParamValidator::PARAM_TYPE => [
					'watch',
					'unwatch',
					'preferences',
					'nochange',
				],
				ParamValidator::PARAM_DEFAULT => "nochange",
			],
			'bot' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'minor' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'createonly' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'nocreate' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'suppress' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken(): string {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=editslot&title=Test&summary=test%20summary&' .
			'text=article%20content&token=123ABC'
			=> 'apihelp-edit-example-edit'
		];
	}
}
