# HannaCodeDialogTiny

A module for ProcessWire CMS/CMF. Provides a number of enhancements for working with Hanna Code tags in **InputfieldTinyMCE**.

The main enhancement is that Hanna tags in a TinyMCE field are rendered as visual widgets and may be **double-clicked** to edit their attributes using core ProcessWire inputfields in a modal dialog.

## Requirements

* ProcessWire >= v3.0.218
* **InputfieldTinyMCE** (Core)
* **TextformatterHannaCode** module

## Installation

1.  Install the module via the ProcessWire module manager or by copying files to `/site/modules/HannaCodeDialogTiny/`.
2.  Go to **Setup > Fields** and edit your TinyMCE field.
3.  In the **Input** tab, scroll to **TinyMCE Advanced Settings** (JSON configuration) or **Toolbar settings**.
4.  Add `hannadropdown` to your toolbar configuration string.

## Configuration

Visit the module configuration screen (**Modules > Configure > HannaCodeDialogTiny**) to set:

* **Exclude prefix:** Tags starting with this prefix will be hidden from the dropdown menu (useful for helper tags).
* **Exclude Hanna tags:** Select specific tags to hide from the dropdown.

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
* `toggle` (InputfieldToggle, if installed)
* `radios`, `select`, `selectmultiple`, `checkboxes`, `asmselect`
* `pagelistselect`, `pagelistselectmultiple`
* `date`, `datetime`
* `icon` (InputfieldIcon)
* `field` (Clone an existing ProcessWire field, see below)

#### Options (for Selects, Radios, etc.)

You can define options using a pipe `|` separator. 

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
my_attr__description=Select the background color.
my_attr__notes=This will affect the whole section.

```

#### Formatting (Date/Time & Numbers)

For datetime fields, you can specify the input format.

```text
start_date__type=datetime
start_date__format="d.m.Y H:i"

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

Hook `ProcessHannaCodeDialog::buildForm` to add custom fields that aren't defined in the Hanna Code attributes, or to modify existing ones.

```php
$wire->addHookAfter('ProcessHannaCodeDialog::buildForm', function(HookEvent $event) {
    $tagName = $event->arguments(0); // Name of the tag being edited
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

Hook `ProcessHannaCodeDialog::prepareOptions` to dynamically inject options into select fields via PHP.

```php
$wire->addHookAfter('ProcessHannaCodeDialog::prepareOptions', function(HookEvent $event) {
    $optionsString = $event->arguments(0);
    $attrName = $event->arguments(1);
    $tagName = $event->arguments(2);

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

## Credits

This module is a collaborative effort:

* **Robin Sallis (Toutouwai):** Creator of the original [HannaCodeDialog](https://github.com/Toutouwai/HannaCodeDialog) for CKEditor. A large part of the logic and concept stems from his work.
* **BitPoet:** Created the initial port `HannaCodeDialogTiny` for InputfieldTinyMCE.
* **interrobang:** Implemented missing features and finalized the module.

## License

Released under Mozilla Public License v2. See file LICENSE for details.

