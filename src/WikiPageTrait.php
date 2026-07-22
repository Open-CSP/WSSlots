<?php

namespace WSSlots;

use MediaWiki\MediaWikiServices;
use RequestContext;
use Title;
use Throwable;
use WikiPage;

/**
 * Trait used to get the WikiPage from a page name.
 */
trait WikiPageTrait {
	/**
	 * Returns the WikiPage object for the given page name, or the current WikiPage if the given page name is NULL.
	 *
	 * @param string|null $pageName
	 * @return WikiPage|null
	 */
	private function getWikiPage( ?string $pageName ): ?WikiPage {
		if ( !$pageName ) {
			try {
				return RequestContext::getMain()->getWikiPage();
			} catch ( Throwable $exception ) {
				return null;
			}
		}

		$title = Title::newFromText( $pageName );

		if ( $title === null || !$title->exists() ) {
			return null;
		}
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			return MediaWikiServices::getInstance()
				->getWikiPageFactory()
				->newFromTitle( $title );
		}
		return WikiPage::factory( $title );
	}
}
