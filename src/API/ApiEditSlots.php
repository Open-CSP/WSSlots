<?php

namespace WSSlots\API;

use ApiBase;
use ApiUsageException;
use MediaWiki\Storage\SlotRecord;
use MWContentSerializationException;
use MWException;
use Wikimedia\ParamValidator\ParamValidator;
use WSSlots\Logger;
use WSSlots\WSSlots;
use MediaWiki\MediaWikiServices;

/**
 * A slot-aware module that allows for editing and creating pages.
 */
class ApiEditSlots extends ApiBase {
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
		$apiResult = $this->getResult();

		// Check if we are allowed to edit or create this page
		$this->checkTitleUserPermissions(
			$title,
			$title->exists() ? 'edit' : [ 'edit', 'create' ],
			[ 'autoblock' => true ]
		);

		$slotUpdates = [];

        $slots = MediaWikiServices::getInstance()->getSlotRoleRegistry()->getKnownRoles();
        foreach ( $slots as $slotName ) {
			if ( isset( $params[ self::maskSlotName( $slotName ) ] ) ) {
				$slotUpdates[ $slotName ] = $params[ self::maskSlotName( $slotName ) ];
			}
		}

		$result = WSSlots::editSlots(
			$user,
			$wikiPage,
			$slotUpdates,
			$params["summary"],
			$params["append"],
			$params["watchlist"]
		);

		if ( $result !== true ) {
			list( $message, $code ) = $result;

			Logger::getLogger()->alert( 'Editing slot failed while performing edit through the "editslots" API: {message}', [
				'message' => $message
			] );

			$this->dieWithError( $message, $code );
		} else {
			$apiResult->addValue( null, 'editslots', ['result' => 'success'] );
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
		$params = [
			'title' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'append' => [
				ApiBase::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
			'summary' => [
				ApiBase::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => ""
			],
			'watchlist' => [
				ApiBase::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => ""
			]
		];

		$slots = MediaWikiServices::getInstance()->getSlotRoleRegistry()->getKnownRoles();
		foreach ( $slots as $slotName ) {
			$params[self::maskSlotName($slotName)] = [
                ApiBase::PARAM_TYPE => 'text',
                ApiBase::PARAM_HELP_MSG => 'apihelp-editslots-param-slot'
            ];
		}

		return $params;
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
			'action=editslots&title=Test&summary=test%20summary&' .
			self::maskSlotName( SlotRecord::MAIN ) . '=article%20content&token=123ABC'
			=> 'apihelp-edit-example-edit'
		];
	}

    /**
     * Masks the given slot name with the prefix "slot_" for use as a parameter name.
     *
     * @param string $slotName
     * @return string
     */
	private static function maskSlotName( string $slotName ): string {
		return 'slot_' . $slotName;
	}
}
