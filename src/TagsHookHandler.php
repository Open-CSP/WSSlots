<?php

namespace WSSlots;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;

/**
 * Class TagsHookHandler
 *
 * @package MetaDataSlot
 */
class TagsHookHandler implements ListDefinedTagsHook, ChangeTagsListActiveHook {
	/**
	 * @inheritDoc
	 */
	public function onListDefinedTags( &$tags ) {
		$this->registerTags( $tags );
	}

	/**
	 * @inheritDoc
	 */
	public function onChangeTagsListActive( &$tags ) {
		$this->registerTags( $tags );
	}

	/**
	 * @param array $tags
	 */
	private function registerTags( &$tags ) {
		$tags[] = 'wsslots-slot-edit';
	}
}
