<?php

namespace WSSlots\API;

use ApiBase;
use ApiUsageException;
use MediaWiki\Revision\SlotRecord;
use MWContentSerializationException;
use Wikimedia\ParamValidator\ParamValidator;
use WSSlots\Logger;
use WSSlots\SlotEditOptions;
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

		$options = new SlotEditOptions();
		$options->summary = $params["summary"];
		$options->append = $params["append"];
		$options->watchlist = $params["watchlist"];
		$options->prepend = $params["prepend"];
		$options->bot = $params["bot"];
		$options->minor = $params["minor"];
		$options->createonly = $params["createonly"];
		$options->nocreate = $params["nocreate"];
		$options->suppress = $params["suppress"];
		$options->tags = $params["tags"];

		$result = WSSlots::performSlotEdit(
			$user,
			$wikiPage,
			$params["text"] ?? "",
			$params["slot"],
			$options
		);

		if ( $result !== true ) {
			[ $message, $code ] = $result;

			Logger::getLogger()->alert(
				'Editing slot failed while performing edit through the "editslot" API: {message}',
				[ 'message' => $message ]
			);

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
			],
			'tags' => [
				ParamValidator::PARAM_TYPE => 'tags',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_DEFAULT => [],
			],
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
