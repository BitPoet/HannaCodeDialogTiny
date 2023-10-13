/**
 * hannadialog plugin for TinyMCE
 *
 * @param editor
 *
 */
function hdDialog(editor, node, hanna) {
	var $ = jQuery;

	var inputs = [];
	var initial = {};
	for(const [key, value] of Object.entries(hanna.attrs)) {
		inputs.push({
			type:		'input',
			name:		key,
			label:		key.charAt(0).toUpperCase() + key.slice(1)
		});
		initial[key] = value;
	}

	editor.windowManager.open({
	  title: hcdt_config.dropdown_title, // The dialog's title - displayed in the dialog header
	  body: {
	    type: 'panel', // The root body type - a Panel or TabPanel
	    items: inputs
	  },
	  initialData: initial,
	  buttons: [ // A list of footer buttons
	    {
	      type: 'submit',
	      name: 'submitButton',
	      text: 'OK'
	    }, {
	    	type: 'cancel',
	    	text: 'Cancel',
	    	name: 'closeButton'
	    }
	  ],
	  onSubmit: (api) => {
	  	const data = api.getData();
	  	var str = hcdt_config.open_tag + hanna.tag;
	  	for(const k of Object.keys(hanna.attrs)) {
	  		str += " " + k + '="' + data[k] + '"';
	  	}
	  	str += hcdt_config.close_tag;
	  	node.innerHTML = str;
	  	api.close();
	  }
	});	
	
	// To be continued...
}


const escapeReChars = (str) => {
	return str.replace(/([[\]*+{}])/g, '\\$1');
};

const parseHannaCode = (str, openTag, closeTag) => {
	str = str.substring(openTag.length);
	str = str.substring(0, str.length - closeTag.length);
	str = str.trim();
	var m1 = /^([a-z0-9_-]+)(.*)$/i.exec(str);
	var hannaName = m1[1];
	var rest = m1[2].trim();
	var attrs = {}, matches;
	if(rest) {
		var ptn = /([a-z0-9_-]+)\s*=\s*(['"])(.*?)\2/gi;
		matches = Array.from(rest.matchAll(ptn), m => {return [m[1], m[3]];});
	}
	for(const [attrN, attrV] of matches) {
		attrs[attrN] = attrV;
	}
	return {
		tag:	hannaName,
		attrs:	attrs
	};
}

tinymce.PluginManager.add('hannadialog', (editor, url) => {
	
	var $ = jQuery;
	const openTag = hcdt_config.open_tag;
	const tagNames = Object.keys(hcdt_config.hanna_tags);
	const closeTag = hcdt_config.close_tag;
	
	editor.on("dblclick", (e) => {
		var node = e.originalTarget;
		if(typeof node === 'undefined')
			return;
		if(node.nodeName === 'SPAN' && $(node).hasClass('hannadialog')) {
			var hannaData = parseHannaCode(node.innerHTML, openTag, closeTag);
			if(Object.keys(hannaData).length > 0) {
				hdDialog(editor, node, hannaData);
			}
		}
	});
	
	editor.on("BeforeSetContent", (e) => {
		// Fired before the content is parsed and rendered in the editor.
		// We make our Hanna Codes noneditable and highlight them.
		var content = "" + e.content;
		for(const tagName of tagNames) {
			const rePattern = "(" + escapeReChars(openTag) + tagName + ".*?" + escapeReChars(closeTag) + ")";
			const re = new RegExp(rePattern, "smg");
			content = content.replace(re, '<span class="noneditable hannadialog" title="' + hcdt_config.hover_title + '">$1</span>');
		}
		e.content = content;
		return e;
	});
	
	editor.on("GetContent", (e) => {
		// Fired before the content is serialized from the editor, e.g. when saving or opening
		// the source editor. We remove any noneditable wrappers around our Hanna Codes.
		var content = "" + e.content;
		for(const tagName of tagNames) {
			const re = new RegExp('<span class="noneditable hannadialog" title="[^"]*">(.*?)</span>', "smg");
			content = content.replace(re, '$1');
		}
		e.content = content;
		return e;
	});

	// Return metadata for the plugin 
	return {
		getMetadata: () => ({ name: 'HannaDialog' })
	};

});
