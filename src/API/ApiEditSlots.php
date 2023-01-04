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

		$slotupdates = array();

		if ( isset( $params[ self::maskSlotName( SlotRecord::MAIN ) ] ) ) {
			$slotupdates[ SlotRecord::MAIN ] = $params[ self::maskSlotName( SlotRecord::MAIN ) ];
		}

		$slots = MediaWikiServices::getInstance()->getMainConfig()->get( "WSSlotsDefinedSlots" );
		foreach( $slots as $slotName => $config ) {
			if ( isset( $params[ self::maskSlotName( $slotName ) ] ) ) {
				$slotupdates[ $slotName ] = $params[ self::maskSlotName( $slotName ) ];
			}
		}

		$result = WSSlots::editSlots(
			$user,
			$wikiPage,
			$slotupdates,
			$params["summary"],
			$params["append"],
			$params["watchlist"]
		);

		if ( $result !== true ) {
			list( $message, $code ) = $result;

			Logger::getLogger()->alert( 'Editing slot failed while performing edit through the "editslot" API: {message}', [
				'message' => $message
			] );

			$this->dieWithError( $message, $code );
		}

		else {
			$apiResult->addValue( null, 'editslots', ['result' => 'Success'] );
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

		$params[self::maskSlotName(SlotRecord::MAIN)] = [ApiBase::PARAM_TYPE => 'text'];

		$slots = MediaWikiServices::getInstance()->getMainConfig()->get( "WSSlotsDefinedSlots" );
		foreach($slots as $slotName => $config) {
			$params[self::maskSlotName($slotName)] = [ApiBase::PARAM_TYPE => 'text'];
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
			self::maskSlotName(SlotRecord::MAIN) . '=article%20content&token=123ABC'
			=> 'apihelp-edit-example-edit'
		];
	}

	public static function maskSlotName($slotName) {
		$prefix = 'slot_';
		return $prefix . $slotName;
	}

	public static function demaskSlotName($masked_slotName) {
		$prefix = 'slot_';
		if (substr($masked_slotName, 0, strlen($prefix)) == $prefix) {
			$masked_slotName = substr($masked_slotName, strlen($prefix));
		} 
		return $masked_slotName;
	}
}
