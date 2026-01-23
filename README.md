# HannaCodeDialogTiny

A module for ProcessWire CMS/CMF. Provides a number of enhancements for working with Hanna Code tags in **InputfieldTinyMCE**.

The main enhancement is that Hanna tags in a TinyMCE field are rendered as visual widgets and may be **double-clicked** to edit their attributes using core ProcessWire inputfields in a modal dialog.

## Requirements

* ProcessWire >= v3.0.218
* **InputfieldTinyMCE** (Core)
* **TextformatterHannaCode** module

## Configuration

### Option 1: Automatic Setup (Recommended)

1.  Go to **Modules > HannaCodeDialogTiny** (Site > HannaCodeDialogTiny)
2.  Enable the checkbox **"Auto-manage Hanna Code Integration"**
3.  Save the module configuration

This will automatically:
- Add the `hannacode` toolbar button to any TinyMCE field that uses `TextformatterHannaCode`
- Enable the `HannaCodeDialogTiny` plugin in "External plugins to enable"
- Remove both when you remove the text formatter

The sync happens automatically when you save a field or the module configuration.

### Option 2: Manual Setup

If you prefer manual control:

1.  Go to **Setup > Fields** and edit your TinyMCE field (e.g., `body`)
2.  In the **Details** tab, add `TextformatterHannaCode` to **Text Formatters**
3.  In the **Input** tab, scroll to **Toolbar** and add `hannacode`
4.  Scroll to **External plugins to enable** and check `HannaCodeDialogTiny`
5.  Save the field

*(If you use a custom JSON configuration, you must manually add the plugin path:)*
```json
{
  "external_plugins": {
    "HannaCodeDialogTiny": "/site/modules/HannaCodeDialogTiny/tinymce-plugin/HannaCodeDialogTiny.js"
  }
}
```

## New Features Compared to Original HannaCodeDialog

This port adds the following enhancements:

* **Additional Inputfield Types:** Support for `email`, `url`, `toggle`, `date`, `datetime`, and `icon` inputfields.
* **Field Cloning:** Use `__type=field` and `__field=fieldname` to clone the complete configuration of an existing ProcessWire field (especially useful for complex fields like PageAutocomplete).


## Features & Usage

### 1. Insert & Edit Tags
* **Insert:** Place cursor in editor, click the "Insert Hanna tag" dropdown icon, and select a tag.
* **Edit:** Double-click any existing gray Hanna Code widget in the editor to open the configuration dialog.
* **Move:** Drag and drop the widget to move it within the text.

### 2. Defining Attributes in Hanna Code
You can define how the input fields in the dialog look directly within your Hanna Code "Attributes" text area using a double underscore syntax: `attribute__property=value`.

#### Inputfield Types
Define the input type using `__type`.
```text
my_date__type=datetime
my_select__type=select

```

**Supported types:**

* `text` (Default), `email`, `url`
* `textarea` (*Note: Line breaks are removed upon saving, as Hanna Code attributes do not support multiline values*)
* `integer` (HTML5 number input)
* `checkbox` (Single toggle, 0/1)
* `toggle` (InputfieldToggle, if installed, with fallback to checkbox)
* `radios`, `select`, `selectmultiple`, `checkboxes`, `asmselect`
* `pagelistselect`, `pagelistselectmultiple`
* `date`, `datetime`
* `icon` (InputfieldIcon)
* `field` (Clone an existing ProcessWire field, see below)

#### Options (for Selects, Radios, etc.)

You can define options using a pipe `|` separator (or comma `,` for legacy support).

**A) Simple List:**

```text
colors__options=Red|Green|Blue

```

**B) Key:Label Syntax:**

```text
status__options=1:Active|0:Inactive
vegetables__options=spinach:Fresh Spinach|pumpkin:Tasty Pumpkin

```

**C) Dynamic Options:**
Use another Hanna Code tag to generate the string (must return `value:Label|value2:Label2`).

```text
products__options=[[_get_products]]

```

#### Field Description & Notes

```text
my_attr__label=Your Custom Label
my_attr__description=Select the background color.
my_attr__notes=This will affect the whole section.

```

#### Formatting (Date/Time & Numbers)

For datetime fields, you can specify the input format.

```text
start_date__type=datetime
start_date__format="d.m.Y H:i"

```

For date fields (no time), use:

```text
birthday__type=date
birthday__format="d.m.Y"

```

### 3. Clone Existing Fields (Powerful!)

Instead of configuring complex fields like `PageAutocomplete` manually, you can tell the dialog to simply "clone" the configuration of an existing ProcessWire field from your setup.

**Example:** You have a field `blog_category` (Page Reference) in your system.

```text
category=""
category__type=field
category__field=blog_category

```

The dialog will now render the full `blog_category` inputfield and save the selected ID(s) into the `category` attribute.

---

## Hooks (Advanced)

You can customize the dropdown and the dialog form using Hooks in your `/site/ready.php`.

### 1. Manipulate Dropdown Tags

Hook `HannaCodeDialogTiny::getDropdownTags` to filter which tags are shown (e.g., based on user roles).

```php
$wire->addHookAfter('HannaCodeDialogTiny::getDropdownTags', function(HookEvent $event) {
    $tags = $event->return;

    // Example: Remove 'secret_tag' if user is not superuser
    if(!$this->user->isSuperuser()) {
        unset($tags['secret_tag']);
    }

    $event->return = $tags;
});

```

### 2. Manipulate Dialog Form (Add/Modify Fields)

Hook `ProcessHannaCodeDialogTiny::buildForm` to add custom fields that aren't defined in the Hanna Code attributes, or to modify existing ones.

```php
$wire->addHookAfter('ProcessHannaCodeDialogTiny::buildForm', function(HookEvent $event) {
    $tagName = $event->arguments(0);           // Name of the tag being edited
    $editedPage = $event->arguments(1);        // The page being edited
    $currentAttributes = $event->arguments(2); // Attributes already present
    $defaultAttributes = $event->arguments(3); // Default attributes from Hanna Code
    $inputfieldName = $event->arguments(4);    // The name of the TinyMCE inputfield
    
    $form = $event->return; // The InputfieldForm object

    if($tagName === 'my_special_tag') {
        // Add a specialized field via API
        $f = $event->wire('modules')->get('InputfieldMarkup');
        $f->label = "Important Note";
        $f->value = "<p>Please remember to fill out all fields!</p>";
        $form->prepend($f);
    }
});

```

### 3. Manipulate Options

Hook `ProcessHannaCodeDialogTiny::prepareOptions` to dynamically inject options into select fields via PHP.
If you hook after `ProcessHannaCodeDialogTiny::prepareOptions` then your hook should set `$event->return` to an array of option values, or an associative array in the form of `$value => $label`.


```php
$wire->addHookAfter('ProcessHannaCodeDialogTiny::prepareOptions', function(HookEvent $event) {
    $optionsString = $event->arguments(0); // The raw options string (e.g. "1:Active|0:Inactive")
    $attrName = $event->arguments(1);      // The name of the attribute
    $tagName = $event->arguments(2);       // The name of the Hanna tag
    $editedPage = $event->arguments(3);    // The page being edited

    if($tagName === 'employee_list' && $attrName === 'employee') {
        // Generate options array dynamically
        $options = [];
        foreach($this->pages->find("template=employee") as $p) {
            $options[$p->id] = $p->title;
        }
        $event->return = $options;
    }
});
```

## Migration from CKEditor (HannaCodeDialog)

This module is fully compatible with the original `HannaCodeDialog` for CKEditor. You can move your existing Hanna Code attributes to TinyMCE without changes.

However, if you have registered custom **Hooks** in your `ready.php`, you need to update the class names:

*   `HannaCodeDialog::getDropdownTags` &rarr; `HannaCodeDialogTiny::getDropdownTags`
*   `ProcessHannaCodeDialog::buildForm` &rarr; `ProcessHannaCodeDialogTiny::buildForm`
*   `ProcessHannaCodeDialog::prepareOptions` &rarr; `ProcessHannaCodeDialogTiny::prepareOptions`

## Credits

This module is a collaborative effort:

* **Robin Sallis (Toutouwai):** Creator of the original [HannaCodeDialog](https://github.com/Toutouwai/HannaCodeDialog) for CKEditor. A large part of the logic and concept stems from his work.
* **BitPoet:** Created the initial port `HannaCodeDialogTiny` for InputfieldTinyMCE.
* **interrobang:** Implemented missing features and finalized the module.

## License

Released under Mozilla Public License v2. See file LICENSE for details.
