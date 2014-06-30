<?php

// This is a PLUGIN TEMPLATE for Textpattern CMS.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ("abc" is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'yab_copy_to_new';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.2.1';
$plugin['author'] = 'Tommy Schmucker';
$plugin['author_uri'] = 'http://www.yablo.de/';
$plugin['description'] = 'Copy the current article content to a new one.';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = '5';

// Plugin 'type' defines where the plugin is loaded
// 0 = public              : only on the public side of the website (default)
// 1 = public+admin        : on both the public and admin side
// 2 = library             : only when include_plugin() or require_plugin() is called
// 3 = admin               : only on the admin side (no AJAX)
// 4 = admin+ajax          : only on the admin side (AJAX supported)
// 5 = public+admin+ajax   : on both the public and admin side (AJAX supported)
$plugin['type'] = '3';

// Plugin "flags" signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use
if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String

/** Uncomment me, if you need a textpack
$plugin['textpack'] = <<< EOT
#@admin
#@language en-gb
abc_sample_string => Sample String
abc_one_more => One more
#@language de-de
abc_sample_string => Beispieltext
abc_one_more => Noch einer
EOT;
**/
// End of textpack

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---
/**
 * yab_copy_to_new
 *
 * A Textpattern CMS plugin.
 * Copy the current article content to a new one.
 *
 * @author Tommy Schmucker
 * @link   http://www.yablo.de/
 * @link   http://tommyschmucker.de/
 * @date   2014-02-06
 *
 * This plugin is released under the GNU General Public License Version 2 and above
 * Version 2: http://www.gnu.org/licenses/gpl-2.0.html
 * Version 3: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Config var
 *
 * @param string  $what
 * @return string
 */
function yab_ctn_config($what)
{
	$config = array(
		'exclude'           => '["url_title", "year", "month", "day", "hour", "minute", "second"]',
		'position_selector' => '#write-save', // a valid jQery selector
		'position_method'   => 'append', // any jQuery DOM insert method
		'class'             => 'publish', // html class applied to element
		'style'             => 'margin-left: 0.5em;' // inline style attribute
	);

	// example config for button placed near top »Create new« button
// 	$config = array(
// 		'exclude'           => '["url_title", "year", "month", "day", "hour", "minute", "second"]',
// 		'position_selector' => '.action-create', // a valid jQery selector
// 		'position_method'   => 'append', // any jQuery DOM insert method
// 		'class'             => '', // html class applied to element
// 		'style'             => 'margin-left: 0.5em;' // inline style attribute
// 	);

	return $config[$what];
}

if (@txpinterface == 'admin')
{
	register_callback(
		'yab_copy_to_new',
		'admin_side',
		'body_end'
	);
}

/**
 * Echo the plugin JavaScript on article write tab.
 *
 * @return void Echos the JavaScript
 */
function yab_copy_to_new()
{
	global $event;

	// Config: name attribute for excluded fields (JavaScript array)
	$exclude  = yab_ctn_config('exclude');
	$anchor   = yab_ctn_config('position_selector');
	$method   = yab_ctn_config('position_method');
	$class    = yab_ctn_config('class');
	$style    = yab_ctn_config('style');

	$buttontext = gTxt('yab_copy_to_new');

	$js = <<<EOF
<script>
(function() {

	// ECMAScript < 5 indexOf method
	if (!Array.prototype.indexOf) {
		Array.prototype.indexOf = function (searchElement /*, fromIndex */ ) {
			"use strict";
			if (this == null) {
				throw new TypeError();
			}
			var t = Object(this);
			var len = t.length >>> 0;
			if (len === 0) {
				return -1;
			}
			var n = 0;
			if (arguments.length > 0) {
				n = Number(arguments[1]);
				if (n != n) { // shortcut for verifying if it's NaN
					n = 0;
				} else if (n != 0 && n != Infinity && n != -Infinity) {
					n = (n > 0 || -1) * Math.floor(Math.abs(n));
				}
			}
			if (n >= len) {
				return -1;
			}
			var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
			for (; k < len; k++) {
				if (k in t && t[k] === searchElement) {
					return k;
				}
			}
			return -1;
		}
	}

	var form2string = function(txp_form) {
		return JSON.stringify(txp_form.serializeArray());
	}

	var string2form = function(txp_form, serialized) {
		var excludes = $exclude;
		var fields = JSON.parse(serialized);
		var flength = fields.length;
		for (var i = 0; i < flength; i++) {
			var e_name = fields[i].name;
			var e_value = fields[i].value;
			var e = txp_form.find('[name="' + e_name + '"]');

			if (e.is(':hidden') || excludes.indexOf(e_name) != -1) {
				continue;
			} else if (e.is(':radio')) {
				e.filter('[value=\'' + e_value + '\']').prop('checked', true);
			} else if (e.is(':checkbox') && e_value) {
				e.prop('checked', true);
			} else if (e.is('select')) {
				e.find('[value=\'' + e_value + '\']').prop('selected', true);
			} else {
				e.val(e_value);
			}
		}
	}

	var j_form = $('#article_form');

	var button = '<button id="yab-copy-to-new" class="$class" tabindex="5" style="$style">$buttontext</button>';

	$('$anchor').$method(button);

		j_form.on('click', '#yab-copy-to-new', function(ev) {
		ev.preventDefault();
                if (!confirm('Are you sure you want to copy?')) {
                    return false;
                }
		var form_string = form2string(j_form);
		sessionStorage.setItem('yab_copy_to_new_form', form_string);
		window.location.href = 'index.php?event=article';
	});

		var form_string = sessionStorage.getItem('yab_copy_to_new_form');
		if (form_string) {
			string2form(j_form, form_string);
			sessionStorage.removeItem('yab_copy_to_new_form');
		}

})();
</script>
EOF;

	if ($event == 'article')
	{
		echo $js;
	}
	return;
}
# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<h1>yab_copy_to_new</h1>
<p>Displays a new button in article write tab to copy the current article to a new one.</p>
<p><strong>Version:</strong> 0.2</p>
<h2>Table of contents</h2>
<ol>
	<li><a href="#help-section02">Plugin requirements</a></li>
	<li><a href="#help-config03">Configuration</a></li>
	<li><a href="#help-section10">Changelog</a></li>
	<li><a href="#help-section11">License</a></li>
	<li><a href="#help-section12">Author contact</a></li>
</ol>
<h2 id="help-section02">Plugin requirements</h2>
<p>yab_copy_to_new&#8217;s  minimum requirements:</p>
<ul>
	<li>Textpattern 4.x</li>
	<li>Modern browser capable of HTML5 sessionStorage</li>
</ul>
<h2 id="help-config03">Configuration</h2>
<p>Install and activate the plugin.</p>
<p>The following form fields will not be copied by default:
<ul>
	<li>all of hidden type</li>
	<li>an exclude array of posted day and time and the url_title<br />
		You can modify this exclude array on your own,
	</li>
</ul>
</p>
<p>The function yab_ctn_config() contains an array with some config values and a commented example of the array which places the button near the top »Create new button«.</p>
<ul>
	<li><code>&#39;exclude&#39;</code>: Javascript array with field to excluded from copying</li>
	<li><code>&#39;position_selector&#39;</code>: a valid jQery selector (used by <code>position_method</code>)</li>
	<li><code>&#39;position_method&#39;</code>: any jQuery <span class="caps">DOM</span> insert method (after, prepend, append, before etc.)</li>
	<li><code>&#39;class&#39;</code>: html class applied to teh button</li>
	<li><code>&#39;style&#39;</code>: inline style attribute aplied to the button</li>
</ul>
<h2 id="help-section10">Changelog</h2>
<ul>
	<li>
		v0.1: 2014-02-06
		<ul>
			<li>initial release</li>
		</ul>
	</li>
	<li>
		v0.2: 2014-04-11
		<ul>
			<li>feature: enhanced config for placing/styling the button</li>
		</ul>
	</li>
	<li>
		v0.2.1: 2014-06-30
		<ul>
			<li>feature: added confirm box</li>
		</ul>
	</li>
</ul>
<h2 id="help-section11">Licence</h2>
<p>This plugin is released under the <span class="caps">GNU</span> General Public License Version 2 and above
<ul>
	<li>Version 2: <a href="http://www.gnu.org/licenses/gpl-2.0.html">http://www.gnu.org/licenses/gpl-2.0.html</a></li>
	<li>Version 3: <a href="http://www.gnu.org/licenses/gpl-3.0.html">http://www.gnu.org/licenses/gpl-3.0.html</a></li>
</ul>
</p>
<h2 id="help-section12">Author contact</h2>
<ul>
	<li><a href="http://www.yablo.de/article/479/yab_copy_to_new-copy-the-current-article-content-to-a-new-one">Plugin on author&#8217;s site</a></li>
	<li><a href="https://github.com/trenc/yab_copy_to_new">Plugin on GitHub</a></li>
	<li><a href="http://forum.textpattern.com/viewtopic.php?pid=278692">Plugin on textpattern forum</a></li>
	<li><a href="http://textpattern.org/plugins/1289/yab_copy_to_new">Plugin on textpattern.org</a></li>
</ul>
# --- END PLUGIN HELP ---
-->
<?php
}
?>
