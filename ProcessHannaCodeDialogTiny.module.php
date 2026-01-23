<?php

namespace ProcessWire;

/**
 * ProcessHannaCodeDialogTiny
 *
 * Renders the dialog form within the ProcessWire Admin environment
 * to insert or edit Hanna Code tags via TinyMCE.
 */
class ProcessHannaCodeDialogTiny extends Process
{
    /**
     * @return array
     */
    public static function getModuleInfo()
    {
        return [
            'title' => 'Hanna Code Dialog Tiny Process',
            'summary' => 'Backend Process for HannaCodeDialogTiny',
            'version' => '0.9.1',
            'author' => 'Robin Sallis, BitPoet, interrobang',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'hanna-code-dialog-tiny',
                'parent' => 'setup',
                'title' => 'Hanna Code Dialog',
                'status' => 'hidden',
            ],
            'useNavJSON' => false,
        ];
    }

    /**
     * Main execution method.
     * Handles input parameters and orchestrates the form rendering.
     *
     * @return string
     * @throws WireException
     */
    public function execute()
    {
        $input = $this->wire()->input;
        $tagName = $input->get->name('tag');
        $pageId = (int) $input->get('pid');
        $inputfieldName = $input->get->name('inputfield');
        $currentCode = $input->get('code');

        if (!$tagName) {
            return 'No tag specified';
        }

        $editedPage = $this->wire()->pages->get($pageId);
        if (!$editedPage->id || !$editedPage->editable()) {
            $editedPage = $this->wire()->page;
        }

        // Parse existing attributes from the code snippet if available
        $currentAttributes = [];
        if ($currentCode) {
            $hanna = $this->wire()->modules->get('TextformatterHannaCode');
            if ($hanna) {
                $currentAttributes = $hanna->getAttributes($currentCode);
            }
        }

        // Ensure name is set
        if (!isset($currentAttributes['name'])) {
            $currentAttributes['name'] = $tagName;
        }

        // Load default attributes from Hanna Code definition
        $hannaTags = $this->getHannaTags();
        $defaultAttributes = isset($hannaTags[$tagName]) ? $hannaTags[$tagName] : [];

        $form = $this->buildForm($tagName, $editedPage, $currentAttributes, $defaultAttributes, $inputfieldName);

        // Configure Form Actions
        $form->action = "./?modal=1&tag=$tagName&pid=$pageId&inputfield=$inputfieldName";
        $form->attr('onsubmit', 'return false;');

        $submit = $this->modules->get('InputfieldSubmit');
        $submit->attr('id+name', 'hcd_save');
        $submit->value = $this->_('Insert');
        $submit->icon = 'check';
        $submit->addClass('ui-button ui-widget ui-state-default ui-corner-all hcd-submit-button');
        $form->add($submit);

        // Ensure dependencies are loaded
        $this->modules->get('JqueryCore');
        $this->modules->get('JqueryUI');

        return $form->render() . $this->getCustomScript($tagName, $inputfieldName);
    }

    /**
     * Builds the InputfieldForm based on Hanna Code attributes.
     *
     * @param  string  $tagName  Name of the Hanna Tag
     * @param  Page  $editedPage  The page currently being edited
     * @param  array  $currentAttributes  Attributes already present in the tag (for editing)
     * @param  array  $defaultAttributes  Default attributes defined in the Hanna Code
     * @param  string  $inputfieldName  Context inputfield name
     *
     * @return InputfieldForm
     * @throws WireException
     */
    public function ___buildForm($tagName, $editedPage, $currentAttributes, $defaultAttributes, $inputfieldName = '')
    {
        $modules = $this->wire()->modules;
        $form = $modules->get('InputfieldForm');
        $form->attr('id+name', 'hanna-form');

        // Parse attributes and extract metadata
        [$cleanAttributes, $meta] = $this->parseAttributeMetadata($defaultAttributes);

        // Build inputfields for each attribute
        foreach ($cleanAttributes as $key => $defaultValue) {
            $inputfield = $this->createInputfield($key, $meta, $editedPage);
            if (!$inputfield) {
                continue;
            }

            $this->configureInputfield($inputfield, $key, $meta, $editedPage);
            $this->setInputfieldValue($inputfield, $key, $currentAttributes, $defaultValue, $meta);

            $form->add($inputfield);
        }

        return $form;
    }

    /**
     * Parse attribute metadata from default attributes
     * Extracts type, options, description, notes, format, and field definitions
     *
     * @param  array  $defaultAttributes
     *
     * @return array [cleanAttributes, meta]
     */
    private function parseAttributeMetadata(array $defaultAttributes)
    {
        $meta = [
            'options' => [],
            'types' => [],
            'desc' => [],
            'notes' => [],
            'format' => [],
            'field' => [],
            'labels' => [],
        ];
        $cleanAttributes = [];

        // Map suffixes to meta keys
        $metaMap = [
            '__options' => 'options',
            '__type' => 'types',
            '__description' => 'desc',
            '__notes' => 'notes',
            '__format' => 'format',
            '__field' => 'field',
            '__label' => 'labels',
        ];

        foreach ($defaultAttributes as $key => $value) {
            $foundMeta = false;

            foreach ($metaMap as $suffix => $metaKey) {
                if (substr($key, -strlen($suffix)) === $suffix) {
                    $baseKey = substr($key, 0, -strlen($suffix));
                    // Types are always lowercase
                    $meta[$metaKey][$baseKey] = ($metaKey === 'types') ? strtolower($value) : $value;
                    $foundMeta = true;
                    break;
                }
            }

            if (!$foundMeta) {
                $cleanAttributes[$key] = $value;
            }
        }

        return [$cleanAttributes, $meta];
    }

    /**
     * Create an inputfield based on attribute type
     *
     * @param  string  $key  Attribute name
     * @param  array  $meta  Metadata array
     * @param  Page  $editedPage  Context page
     *
     * @return Inputfield|null
     */
    private function createInputfield($key, array $meta, $editedPage)
    {
        $modules = $this->wire()->modules;
        $typeRaw = isset($meta['types'][$key]) ? $meta['types'][$key] : 'text';

        // Case A: Mirror an existing ProcessWire Field
        if ($typeRaw === 'field') {
            return $this->createFieldMirrorInputfield($key, $meta, $editedPage);
        }

        // Case B: Create Standard Inputfield
        return $this->createStandardInputfield($key, $meta, $typeRaw, $editedPage);
    }

    /**
     * Create inputfield that mirrors an existing PW field
     *
     * @param  string  $key
     * @param  array  $meta
     * @param  Page  $editedPage
     *
     * @return Inputfield
     */
    private function createFieldMirrorInputfield($key, array $meta, $editedPage)
    {
        $modules = $this->wire()->modules;
        $sourceFieldName = isset($meta['field'][$key]) ? $meta['field'][$key] : $key;
        $sourceField = $this->wire()->fields->get($sourceFieldName);

        if ($sourceField) {
            return $sourceField->getInputfield($editedPage);
        }

        // Error fallback
        $errorField = $modules->get('InputfieldMarkup');
        $errorField->value = "<p class='ui-state-error'>Error: Field '$sourceFieldName' not found.</p>";

        return $errorField;
    }

    /**
     * Create standard inputfield based on type
     *
     * @param  string  $key
     * @param  array  $meta
     * @param  string  $typeRaw
     * @param  Page  $editedPage
     *
     * @return Inputfield
     */
    private function createStandardInputfield($key, array $meta, $typeRaw, $editedPage)
    {
        $modules = $this->wire()->modules;

        // Map simple types to Inputfield classes
        $typeMap = [
            'text' => 'InputfieldText',
            'int' => 'InputfieldInteger',
            'integer' => 'InputfieldInteger',
            'date' => 'InputfieldDatetime',
            'datetime' => 'InputfieldDatetime',
            'bool' => 'InputfieldCheckbox',
            'icon' => 'InputfieldIcon',
            'email' => 'InputfieldEmail',
            'url' => 'InputfieldURL',
            'toggle' => 'InputfieldToggle',
            'radios' => 'InputfieldRadios',
            'selectmultiple' => 'InputfieldSelectMultiple',
            'asmselect' => 'InputfieldAsmSelect',
            'checkboxes' => 'InputfieldCheckboxes',
            'select' => 'InputfieldSelect',
            'pagelistselect' => 'InputfieldPageListSelect',
            'pagelistselectmultiple' => 'InputfieldPageListSelectMultiple',
        ];

        $moduleName = isset($typeMap[$typeRaw]) ? $typeMap[$typeRaw] : ('Inputfield' . ucfirst($typeRaw));
        $hasOptions = isset($meta['options'][$key]);

        // Toggle fallback if not installed
        if ($moduleName === 'InputfieldToggle' && !$modules->isInstalled('InputfieldToggle')) {
            $moduleName = $hasOptions ? 'InputfieldSelect' : 'InputfieldCheckbox';
        }

        // Generic fallback if module is missing
        if (!$modules->isInstalled($moduleName) && $moduleName !== 'InputfieldCheckbox' && $moduleName !== 'InputfieldSelect') {
            $moduleName = $hasOptions ? 'InputfieldSelect' : 'InputfieldText';
        }

        $inputfield = $modules->get($moduleName);

        // Process options if present
        if ($hasOptions) {
            $this->addInputfieldOptions($inputfield, $meta['options'][$key], $key, $editedPage);
        }

        return $inputfield;
    }

    /**
     * Add options to an inputfield
     *
     * @param  Inputfield  $inputfield
     * @param  string  $optString
     * @param  string  $key
     * @param  Page  $editedPage
     */
    private function addInputfieldOptions($inputfield, $optString, $key, $editedPage)
    {
        // Parse Hanna Code inside options if exists
        $openTag = $this->wire()->modules->getConfig('TextformatterHannaCode', 'openTag') ?: '[[';
        if (strpos($optString, $openTag) !== false) {
            $modules = $this->wire()->modules;
            $optString = $modules->get('TextformatterHannaCode')->formatValue($editedPage, new Field(), $optString);
            $optString = strip_tags($optString);
        }

        $preparedOptions = $this->prepareOptions($optString, $key, '', $editedPage);
        if (method_exists($inputfield, 'addOptions')) {
            $inputfield->addOptions($preparedOptions);
        }
    }

    /**
     * Configure inputfield with labels, descriptions, and special settings
     *
     * @param  Inputfield  $inputfield
     * @param  string  $key
     * @param  array  $meta
     * @param  Page  $editedPage
     */
    private function configureInputfield($inputfield, $key, array $meta, $editedPage)
    {
        $inputfield->attr('id+name', $key);

        $typeRaw = isset($meta['types'][$key]) ? $meta['types'][$key] : 'text';

        // Skip configuration for field mirrors
        if ($typeRaw === 'field') {
            return;
        }

        // Set label, description, notes
        if (isset($meta['labels'][$key])) {
            $inputfield->label = $meta['labels'][$key];
        } else {
            $inputfield->label = ucfirst(str_replace('_', ' ', $key));
        }

        if (isset($meta['desc'][$key])) {
            $inputfield->description = $meta['desc'][$key];
        }
        if (isset($meta['notes'][$key])) {
            $inputfield->notes = $meta['notes'][$key];
        }

        // Integer configuration
        if ($typeRaw === 'integer' || $typeRaw === 'int') {
            $inputfield->inputType = 'number';
        }

        // Datetime configuration
        if ($inputfield instanceof InputfieldDatetime) {
            $inputfield->dateInputFormat = isset($meta['format'][$key]) ? $meta['format'][$key] : 'Y-m-d';
            $inputfield->timeInputFormat = ($typeRaw === 'datetime') ? 'H:i' : '';
            $inputfield->datepicker = 3;
        }

        // Icon configuration
        if ($inputfield instanceof InputfieldIcon) {
            $config = $this->wire()->config;
            $config->scripts->add($config->urls->InputfieldIcon . 'InputfieldIcon.js');
            $config->styles->add($config->urls->InputfieldIcon . 'InputfieldIcon.css');
        }
    }

    /**
     * Set the value of an inputfield from current or default attributes
     *
     * @param  Inputfield  $inputfield
     * @param  string  $key
     * @param  array  $currentAttributes
     * @param  mixed  $defaultValue
     * @param  array  $meta
     */
    private function setInputfieldValue($inputfield, $key, array $currentAttributes, $defaultValue, array $meta)
    {
        $value = isset($currentAttributes[$key]) ? $currentAttributes[$key] : $defaultValue;

        if ($this->isMultiValueField($inputfield)) {
            // Multi-value fields: split by pipe or comma
            if (is_array($value)) {
                $inputfield->value = $value;
            } else {
                $inputfield->value = strpos($value, '|') !== false
                    ? explode('|', $value)
                    : explode(',', $value);
            }
        } elseif ($inputfield instanceof InputfieldCheckbox || ($inputfield instanceof InputfieldToggle && !isset($meta['options'][$key]))) {
            // Checkbox / Boolean toggle
            $checked = (int) $value;
            if ($inputfield instanceof InputfieldCheckbox) {
                $inputfield->attr('checked', $checked === 1 ? 'checked' : '');
            } else {
                $inputfield->value = $checked;
            }
        } else {
            // Standard value
            $inputfield->value = $value;
        }
    }

    /**
     * Check if an inputfield accepts multiple values
     *
     * @param  Inputfield  $inputfield
     *
     * @return bool
     */
    private function isMultiValueField($inputfield)
    {
        return ($inputfield instanceof InputfieldSelectMultiple)
            || ($inputfield instanceof InputfieldPageListSelectMultiple)
            || (strpos($inputfield->className(), 'AsmSelect') !== false)
            || (strpos($inputfield->className(), 'PageAutocomplete') !== false)
            || (strpos($inputfield->className(), 'Checkboxes') !== false);
    }

    /**
     * Parses the options string into an array.
     * Supports "key=value", "key:value" or simple values.
     *
     * @param  string  $optionsString  Pipe-separated options string
     * @param  string  $attributeName  Reserved for hook usage
     * @param  string  $tagName  Reserved for hook usage
     * @param  Page|null  $page  Reserved for hook usage
     *
     * @return array
     */
    public function ___prepareOptions($optionsString, $attributeName = '', $tagName = '', $page = null)
    {
        $options = [];
        $arr = explode('|', $optionsString);

        foreach ($arr as $opt) {
            $opt = trim($opt);
            if ($opt === '') {
                continue;
            }

            if (strpos($opt, ':') !== false) {
                [$k, $v] = explode(':', $opt, 2);
                $options[trim($k)] = trim($v);
            } elseif (strpos($opt, '=') !== false) {
                [$k, $v] = explode('=', $opt, 2);
                $options[trim($k)] = trim($v);
            } else {
                $options[$opt] = $opt;
            }
        }

        return $options;
    }

    /**
     * Retrieves all configured Hanna Tags and their default attributes.
     *
     * @return array
     */
    public function getHannaTags()
    {
        $hanna = $this->wire()->modules->get('TextformatterHannaCode');
        $tags = [];
        if (!$hanna) {
            return $tags;
        }

        foreach ($hanna->hannaCodes()->getAll() as $hc) {
            $tags[$hc->name] = $hc->attrs;
        }

        return $tags;
    }



    /**
     * Returns custom JavaScript for the dialog.
     * Handles data serialization and communication with the parent window (TinyMCE).
     *
     * @param  string  $tagName
     *
     * @return string
     */
    protected function getCustomScript($tagName, $inputfieldName)
    {
        $openTag = $this->wire()->modules->getConfig('TextformatterHannaCode', 'openTag') ?: '[[';
        $closeTag = $this->wire()->modules->getConfig('TextformatterHannaCode', 'closeTag') ?: ']]';

        return <<<JS
<script>
    $(document).ready(function() {
        if(typeof Inputfields !== 'undefined') Inputfields.init();

        $('#hcd_save').click(function(e) {
            e.preventDefault(); 
            var tag = '$tagName';
            var openTag = '$openTag';
            var closeTag = '$closeTag';
            var inputfieldId = '$inputfieldName';
            
            var formData = $('form#hanna-form').serializeArray();
            var dataMap = {};

            $.each(formData, function(index, field) {
                var name = field.name;
                var val = field.value;

                if(!name || name.indexOf('TOKEN') === 0 || name.indexOf('_') === 0 || name === 'hcd_save') return;
                if(name.indexOf('asmSelect') === 0) return; 

                var cleanName = name;
                if(cleanName.endsWith('[]')) {
                    cleanName = cleanName.substring(0, cleanName.length - 2);
                }

                if(dataMap[cleanName]) {
                    dataMap[cleanName].push(val);
                } else {
                    dataMap[cleanName] = [val];
                }
            });

            var attrs = '';
            for (var key in dataMap) {
                if (dataMap.hasOwnProperty(key)) {
                    // Join with Pipe (|) instead of Comma (,) for better PW compatibility
                    var valStr = dataMap[key].join('|');
                    valStr = valStr.replace(/"/g, '&quot;');
                    attrs += ' ' + key + '="' + valStr + '"';
                }
            }

            var finalCode = openTag + tag + attrs + closeTag;
            window.parent.postMessage({ 
                mceAction: 'insertHanna', 
                text: finalCode,
                inputfield: inputfieldId
            }, '*');
        });
    });
</script>
JS;
    }
}