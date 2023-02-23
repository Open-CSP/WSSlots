# WSSlots

This extension provides a mechanism to create new slots.

## Installation

* Download an place the file(s) in a directory called `WSSlots` in your `extensions/` folder.
* Run Composer to install PHP dependencies, by issuing `composer install --no-dev` in the extension directory.
* Add the following code to the bottom of your LocalSettings.php:

    ```php
    wfLoadExtension( 'WSSlots' );
    ```

* Done - Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

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

This configuration variable is also exposed as a JavaScript variable and can be accessed like so:

```javascript
var site_slots = mw.config.get('wgWSSlotsDefinedSlots');
```

Please note that this only covers slots created by the WSSlots extension.

For more information on content models see [MediaWiki.org](https://www.mediawiki.org/wiki/Manual:Page_content_models) and for more information on slot role layouts see [here](https://doc.wikimedia.org/mediawiki-core/master/php/classMediaWiki_1_1Revision_1_1SlotRoleHandler.html#a42a50a9312fd931793c3573808f5b8a1).

### `$wgWSSlotsDefaultContentModel`

This is the default content model to use, if no content model is given explicitly.

### `$wgWSSlotsDefaultSlotRoleLayout`

This is the default slot role layout to use, if no slot role layout is given explicitly.

### `$wgWSSlotsSemanticSlots`

This configuration parameter defines which slots should be analysed for semantic annotations.

### `$wgWSSlotsDoPurge`

This configuration option specifies whether to purge the page after a slot edit is performed.

### `$wgWSSlotsOverrideActions`

When set to true, all actions are replaced by slot-aware actions when available. When set to an array, each item in the array specifies an action to replace with its slot aware counterpart. See [#Actions](#Actions) below for a list of available slot-aware actions.

## Parser functions

### `#slot`
The extension provides the `#slot` parser function to get the content of a specific slot. For example, `{{#slot: main}}` returns the content of the `main` slot. You can optionally specify a page as the second parameter. For instance, `{{#slot: main | Foobar }}` gets the `main` slot from the page `Foobar`. An additional third parameter can be set to anything to have the returned content parsed.

### `#slotdata`
The extension provides the `#slotdata` parser function to get structured content from a specific slot. The syntax of the parser function is as follows:

```
{{#slotdata: <slotname> | [<pagename> | [<key> | [<search> | [<compat>]]]]}}
```

* `<slotname>`: The name of the slot to get the data from.
* `<pagename>` (optional, default: `{{FULLPAGENAME}}` ): The name of the page to get the data from.
* `<key>` (optional, default: ``): The key of the value to return (dot-separated list of indices).
* `<search>` (optional, default: ``): The search to perform before looking for the key, should be of the form `key=value`. If the given key-value pair is not unique, the first enclosing block that contains that pair will be used.
* `<compat>` (optional, default: false): Whether to return the result in a manner that [ArrayFunctions](https://www.mediawiki.org/wiki/Extension:ArrayFunctions) understands. Requires ArrayFunctions to be installed and enabled.

This function is aware of the content model of the page. This means that it parses a page differently, depending on what the content model is. The content models from which it can extract structured data are:

* `JsonContent`: Parses the page as JSON;
* `WikitextContent`: Parses the page using a wikitext parser to get template calls.

### `#slottemplates` (deprecated)
The extension also provides the `#slottemplates` parser function that returns the templates in a specific slot as a multidimensional array. This parser function required WSArrays to be installed.

The parser function has two modes of operation. It can either process templates non-recursively (DEPRECATED), or it can process them recursively (RECOMMENDED). With the non-recursive parser function, multiple templates with the same name are not supported and nested template calls are not processed. With recursive parsing, this is supported. Recursive parsing also supports retrieving the original unparsed content of an argument.

The syntax of the parser function is as follows:

```
{{#slottemplates: <slotname> | <pagename> | <arrayname> | <recursive> }}
```

## Lua functions

The following Lua functions are available:

### `mw.slots.slotContent(slotName, pageName)`

Mirrors the functionality of the #slot parser function and makes it available in Lua. It takes the following parameters:

* `slotName` (required, string): The name of the slot to get the content for;
* `pageName` (optional, string, default=`{{PAGENAME}}`): The name of the page to get the slot from.

### `mw.slots.slotTemplates(slotName, pageName)` (deprecated)

Mirrors the functionality of the #slottemplates parser function and makes it available in Lua.  It takes the following parameters:

* `slotName` (required, string): The name of the slot to get the templates for;
* `pageName` (optional, string, default=`{{PAGENAME}}`): The name of the page to get the slot from.

This function is deprecated and will be replaced by `mw.slots.slotData()` soon.

### `mw.slots.slotContentModel(slotName, pageName)`

Returns the content model of the specified slot. It takes the following parameters:

* `slotName` (required, string): The name of the slot to get the content model for;
* `pageName` (optional, string, default=`{{PAGENAME}}`): The name of the page to get the slot from.

### `mw.slots.slotData(slotName, pageName)`

Mirrors the functionality of the #slotdata parser function and makes it available in Lua. This function omits any advanced selection tools (JSONPath). It takes the following parameters:

* `slotName` (required, string): The name of the slot to get the data for;
* `pageName` (optional, string, default=`{{PAGENAME}}`): The name of the page to get the slot from.

## Actions

### `rawslot`
Slot-aware version of `action=raw` (see [RawAction](https://m.mediawiki.org/wiki/Manual:RawAction.php)). Returns the content of the specified slot as raw value (format depends on the slot content model). Example:

```
/wiki/MyMultislotPage?action=rawslot&slot=someslot
```
