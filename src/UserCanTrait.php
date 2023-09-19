<?php

namespace WSSlots;

use MediaWiki\MediaWikiServices;
use RequestContext;
use User;
use WikiPage;

trait UserCanTrait {
	/**
	 * Checks if the user can read the given WikiPage.
	 *
	 * @param WikiPage $wikiPage
	 * @param User|null $user
	 * @return bool
	 */
	private function userCan( WikiPage $wikiPage, User $user = null ): bool {
		// Only do a check for user rights when not in CLI mode
		if ( PHP_SAPI === 'cli' ) {
			return true;
		}

		return MediaWikiServices::getInstance()->getPermissionManager()->userCan(
			'read',
			$user ?? RequestContext::getMain()->getUser(),
			$wikiPage->getTitle()
		);
	}
}
