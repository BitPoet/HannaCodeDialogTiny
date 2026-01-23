/**
 * hannadialog plugin for TinyMCE
 * - Converts Hanna Codes to Widgets
 * - Handles Drag & Drop
 * - Opens Iframe Dialog
 */
tinymce.PluginManager.add('HannaCodeDialogTiny', function (editor, url) {

    var openTag = hcdt_config.open_tag;
    var closeTag = hcdt_config.close_tag;
    var tagNames = Object.keys(hcdt_config.hanna_tags);

    /**
     * Parse Hanna Code string into components
     * @param {string} str - The Hanna Code string
     * @param {string} openTag - Opening tag delimiter
     * @param {string} closeTag - Closing tag delimiter
     * @return {object} Parsed components: tag, attrs, fullCode
     */
    var parseHannaCode = function (str, openTag, closeTag) {
        var clean = str;
        if (clean.startsWith(openTag)) clean = clean.substring(openTag.length);
        if (clean.endsWith(closeTag)) clean = clean.substring(0, clean.length - closeTag.length);
        clean = clean.trim();

        var match = /^([a-z0-9_-]+)([\s\S]*)$/i.exec(clean);
        if (!match) return {};

        return {
            tag: match[1],
            attrs: match[2].trim(),
            fullCode: openTag + clean + closeTag
        };
    };

    /**
     * Escape special regex characters
     * @param {string} str - String to escape
     * @return {string} Escaped string
     */
    var escapeRegex = function (str) {
        return str.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    };



    // Load CSS explicitly when editor initializes
    editor.on('init', function () {
        if (hcdt_config.css_url) {
            editor.dom.loadCSS(hcdt_config.css_url);
        }
    });

    // FIX FOR UNDO/REDO: Store raw Hanna Code in undo snapshots instead of widget HTML
    // This prevents the data-hcd-code attribute from getting corrupted during undo
    editor.on('BeforeAddUndo', function (e) {
        if (!e.level || !e.level.content) return;

        // Convert widget HTML back to raw Hanna Code before storing in undo manager
        // This uses the same regex as GetContent
        var re = /<span[^>]*data-hcd-code=(["'])(.*?)\1[^>]*>[\s\S]*?<\/span>/gi;

        e.level.content = e.level.content.replace(re, function (match, quote, captured) {
            // Restore encoded quotes
            return captured.replace(/&quot;/g, '"');
        });
    });


    /**
     * Open the Hanna Code dialog
     * @param {string} tagName - Name of the Hanna tag
     * @param {string} existingCode - Existing code for editing (optional)
     */
    var openDialog = function (tagName, existingCode) {
        try {
            var dialogUrl = new URL(hcdt_config.dialog_url, window.location.origin);
            dialogUrl.searchParams.set('tag', tagName);
            dialogUrl.searchParams.set('pid', hcdt_config.page_id);
            dialogUrl.searchParams.set('inputfield', editor.id);

            if (existingCode) {
                // Simple encoding for URL transport
                dialogUrl.searchParams.set('code', existingCode);
            }

            editor.windowManager.openUrl({
                title: hcdt_config.dialog_title + ': ' + tagName,
                url: dialogUrl.toString(),
                width: 600,
                height: 500
            });
        } catch (e) {
            console.error('HannaDialog URL Error:', e);
        }
    };

    // Register command for other plugins (e.g., dropdown) to use
    editor.addCommand('mceHannaDialog', function (ui, value) {
        if (value && value.tag) {
            openDialog(value.tag, value.code);
        }
    });
    // Register the Toolbar Button
    // Support both FontAwesome icon names (e.g., 'sun-o') and SVG URLs
    var iconName = 'hcdtdd';
    if (hcdt_config.dropdown_icon.indexOf('/') === -1 && hcdt_config.dropdown_icon.indexOf('.') === -1) {
        // It's a FontAwesome icon name, register as HTML with inline style
        editor.ui.registry.addIcon(iconName, '<i class="fa fa-' + hcdt_config.dropdown_icon + '" style="font-family: FontAwesome !important;"></i>');
    } else {
        // It's a URL, register as custom icon
        editor.ui.registry.addIcon(iconName, '<img style="width:24px; height: 24px;" src="' + hcdt_config.dropdown_icon + '" />');
    }

    editor.ui.registry.addMenuButton('hannacode', {
        text: hcdt_config.dropdown_title,
        icon: iconName,
        fetch: function (callback) {
            var items = [];
            var tags = hcdt_config.hanna_tags;
            // Sort tags alphabetically
            var sortedKeys = Object.keys(tags).sort(function (a, b) {
                return a.toLowerCase().localeCompare(b.toLowerCase());
            });

            for (var i = 0; i < sortedKeys.length; i++) {
                (function (tagKey) {
                    var tagDef = tags[tagKey];
                    items.push({
                        type: 'menuitem',
                        text: tagKey,
                        onAction: function () {
                            if (!tagDef.has_attrs) {
                                // Direct insert
                                editor.insertContent(hcdt_config.open_tag + tagKey + hcdt_config.close_tag);
                            } else {
                                // Open dialog
                                openDialog(tagKey);
                            }
                        }
                    });
                })(sortedKeys[i]);
            }
            callback(items);
        }
    });

    /**
     * Message handler for dialog communication
     * Listens for postMessage from the iframe dialog
     */
    var messageHandler = function (event) {
        var data = event.data;
        if (data && data.mceAction === 'insertHanna') {
            // Verify content target matches this editor exactly
            if (!data.inputfield || data.inputfield !== editor.id) {
                return;
            }
            editor.insertContent(data.text);
            editor.windowManager.close();
        }
    };

    window.addEventListener('message', messageHandler);

    // Cleanup listener when editor is removed to prevent memory leaks
    editor.on('remove', function () {
        window.removeEventListener('message', messageHandler);
    });

    // Double-click to edit Hanna Code widget
    editor.on("dblclick", function (e) {
        var node = e.target.closest('.hannadialog');
        if (node) {
            // Read raw code from attribute
            var content = node.getAttribute('data-hcd-code');

            // Fallback to text content
            if (!content) content = node.innerText || node.textContent;

            // Validate that we have content
            if (!content) {
                console.warn('HannaDialog: No code found in widget');
                return;
            }

            var hannaData = parseHannaCode(content, openTag, closeTag);
            if (hannaData.tag) {
                openDialog(hannaData.tag, content);
            }
        }
    });

    // Drag start: Store dragged node and set drag data
    var draggedNode = null;
    editor.on('dragstart', function (e) {
        var node = e.target.closest('.hannadialog');
        if (node) {
            draggedNode = node;
            var code = node.getAttribute('data-hcd-code') || node.innerText;

            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', code);
            e.dataTransfer.setData('text/html', code);

            node.style.opacity = 0.4;
        }
    });

    // Drag end: Refresh content if widget was successfully moved
    editor.on('dragend', function (e) {
        if (draggedNode) {
            // Check if TinyMCE already moved the widget
            // If the widget is no longer in the DOM, TinyMCE handled the move successfully
            // If it's still there, the drop failed (e.g., onto a contenteditable=false widget)
            var stillInDom = draggedNode.parentNode !== null;
            var wasSuccessfulMove = e.dataTransfer.dropEffect !== 'none' && !stillInDom;

            if (!wasSuccessfulMove) {
                // Drop was cancelled OR drop failed (widget still in original position)
                // Restore opacity
                draggedNode.style.opacity = '';
            }

            draggedNode = null;

            // Only refresh if widget was actually moved
            if (wasSuccessfulMove) {
                setTimeout(function () {
                    var bm = editor.selection.getBookmark(2, true);
                    editor.setContent(editor.getContent());
                    editor.selection.moveToBookmark(bm);
                }, 50);
            }
        }
    });

    // Render widgets: Convert raw Hanna Code to widget HTML
    editor.on("BeforeSetContent", function (e) {
        if (!e.content) return;
        var content = "" + e.content;

        if (tagNames.length > 0) {
            var tagsPattern = tagNames.map(escapeRegex).join('|');
            // Build regex to find Hanna Code tags
            var pattern = "(" + escapeRegex(openTag) + "(?:" + tagsPattern + ")\\b[\\s\\S]*?\\/?" + escapeRegex(closeTag) + ")";
            var regex = new RegExp(pattern, "gi");

            content = content.replace(regex, function (match) {
                var data = parseHannaCode(match, openTag, closeTag);

                // Escape quotes for HTML attribute
                var safeCode = match.replace(/"/g, '&quot;');

                return '<span class="noneditable hannadialog" contenteditable="false" data-hcd-code="' + safeCode +
                    '" title="' + hcdt_config.hover_title +
                    '"><strong class="h-name">' + data.tag +
                    '</strong><em class="h-attrs">' + data.attrs + '</em></span>';
            });
        }
        e.content = content;
    });

    // Save: Convert widget HTML back to raw Hanna Code
    editor.on("GetContent", function (e) {
        if (!e.content) return;

        // Robust regex to capture the code from the data attribute (single or double quotes)
        // Uses backreference \1 to match the same quote type
        var re = /<span[^>]*data-hcd-code=(["'])(.*?)\1[^>]*>[\s\S]*?<\/span>/gi;

        e.content = e.content.replace(re, function (match, quote, captured) {
            // Restore encoded quotes
            return captured.replace(/&quot;/g, '"');
        });
    });

    return { getMetadata: function () { return { name: 'HannaDialog' }; } };
});
