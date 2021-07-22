# WSSlots

This extension provides a mechanism to create new slots.

## Configuration

The extension provides the following configuration options:

### `$wgWSSlotsDefinedSlots`

This is an array of the slots to define. Each item in the array corresponds to the name of the slot to define. It is also possible to optionally configure the slot's content model and slot role layout, like so:

```php
$wgWSSlotsDefinedSlots = [
    "example" => [
        "content_model" => "wikitext",
        "slot_role_layout" => [
            "display" => "none",
            "region" => "center",
            "placement" => "append"
        ]
    ]
];
```

For more information on content models see [MediaWiki.org](https://www.mediawiki.org/wiki/Manual:Page_content_models) and for more information on slot role layouts see [here](https://doc.wikimedia.org/mediawiki-core/master/php/classMediaWiki_1_1Revision_1_1SlotRoleHandler.html#a42a50a9312fd931793c3573808f5b8a1).

### `$wgWSSlotsDefaultContentModel`

This is the default content model to use, if no content model is given explicitly.

### `$wgWSSlotsDefaultSlotRoleLayout`

This is the default slot role layout to use, if no slot role layout is given explicitly.

### `$wgWSSlotsSlotsToAppend`

This configuration options specifies from which slots the content should be appended when a page is parsed. It is an array of slot names. Please note that the content will be appended for each parse of the page.

## Parser functions

The extension provides the `#slot` parser function to get the content of a specific slot. For example, `{{#slot: main}}` returns the content of the `main` slot. You can optionally specify a page as the second parameter. For instance, `{{#slot: main | Foobar }}` gets the `main` slot from the page `Foobar`. An additional third parameter can be set to anything to have the returned content parsed.

The extension also provides the `#slottemplates` parser function that returns the templates in a specific slot as a multidimensional array. This parser function required WSArrays to be installed. For example, `{{#slottemplates: main | Foobar | foo }}` creates a multidimensional array `foo` with the templates in the `main` slot of the page `Foobar`. If no page is given, the current page is fetched.
