# HannaCodeDialogTiny

A module for ProcessWire CMS/CMF. Provides a number of enhancements for working with Hanna Code tags in TinyMCE. The main enhancement is that Hanna tags in a TinyMCE field may be double-clicked to edit their attributes using core ProcessWire inputfields in a modal dialog.

Requires the Hanna Code module, InputfieldTinyMCE and ProcessWire >= v3.0.218.

## Stability

Alpha, please do not use this in production yet

## Installation

Install the HannaCodeDialogTiny module using any of the normal methods.

For any TinyMCE field where you want the "Insert Hanna tag" dropdown menu to appear in the TinyMCE toolbar, visit the field settings and add "hannadropdown" to the "TinyMCE Toolbar" settings field.

## Module configuration

Visit the module configuration screen to set any of the following:

* Exclude prefix: Hanna tags named with this prefix will not appear in the TinyMCE toolbar dropdown menu for Hanna tag insertion.
* Exclude Hanna tags: Hanna tags selected here will not appear in the TinyMCE toolbar dropdown menu for Hanna tag insertion.

## Features

### Insert tag from toolbar dropdown menu

Place the cursor in the TinyMCE window where you want to insert your Hanna tag, then select the tag from the "Insert Hanna tag" dropdown.

Advanced: if you want to control which tags appear in the dropdown on particular pages or templates you can hook `HannaCodeDialogTiny::getDropdownTags`. 

### Edit tag attributes in modal dialog

Insert a tag using the dropdown or double-click an existing tag in the TinyMCE window to edit the tag attributes in a modal dialog.

### Tags are noneditables

Hanna tags that have been inserted in a TinyMCE window are "noneditables" - they have a background colour for easy identification, are protected from accidental editing, and can be moved within the text by drag-and-drop.

## Credits

HannaCodeDialogTiny was inspired by [HannaCodeDialog](https://github.com/Toutouwai/HannaCodeDialog), a Hanna Code helper module for InputfieldCKEditor. Big thanks to Robin S! A lot of the PHP code was taken and adapted from that module. Some features (select options in Hanna dialogs or dynamic options) have not been implemented here.

## License

Released under Mozilla Public License v2. See file LICENSE for details.
