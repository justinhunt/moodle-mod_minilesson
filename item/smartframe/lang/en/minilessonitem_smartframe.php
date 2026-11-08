<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language pack for the widget item
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Make a widget with AI';
$string['aiinstructions_copied'] = 'AI instructions copied';
$string['aiinstructions_copy'] = 'Copy AI instructions';
$string['aiinstructions_download'] = 'Download';
$string['aiinstructions_edit'] = 'To change a widget later, download it from the file box below, attach it to a new chat with these instructions, and say what to change.';
$string['aiinstructions_intro'] = 'Make the widget in ChatGPT, Claude or Gemini, where you can try it and ask for changes:';
$string['aiinstructions_show'] = 'Show the instructions';
$string['aiinstructions_step1'] = 'Paste these instructions into a new chat, or download them and attach the file.';
$string['aiinstructions_step2'] = 'Describe the activity. For example: "A quiz on the past tense of 10 common verbs, with English and Japanese instructions, that reports a score."';
$string['aiinstructions_step3'] = 'Try it in the chat, and ask for changes until you are happy with it.';
$string['aiinstructions_step4'] = 'Say "Export it for Poodll", download the file it gives you, and upload it below.';
$string['error:htmlencoding'] = 'The HTML file must be saved as UTF-8 text.';
$string['error:importbase64'] = 'The widget file {$a} is not valid base64. Encode the file\'s bytes as plain base64, without a "data:" prefix.';
$string['error:importboth'] = 'Give the widget either as widgethtml or as a file in customfile1, not both.';
$string['error:importgradepolicy'] = 'gradepolicy must be 0 (ignore the widget\'s score), 1 (record it) or 2 (count it in the lesson grade).';
$string['error:importnofile'] = 'The item has no widget. Put the widget\'s HTML in its widgethtml field.';
$string['error:importonefile'] = 'The item has more than one file in its customfile1 file area. Add just one widget file (.html or .zip).';
$string['error:manifestentry'] = 'The manifest\'s "entry" ({$a}) must name an HTML file in the ZIP.';
$string['error:manifestfield'] = 'The manifest\'s "{$a}" is missing or not valid.';
$string['error:manifestjson'] = 'The widget\'s manifest is not valid JSON.';
$string['error:manifestprotocol'] = 'This widget needs a newer lesson host. This host speaks protocol version {$a}.';
$string['error:manifestschema'] = 'The manifest\'s "schemaVersion" must be {$a}.';
$string['error:nomanifest'] = 'The ZIP has no manifest.json at its top level.';
$string['error:nomanifesthtml'] = 'The HTML file has no widget manifest. It needs a &lt;script type="application/json" id="poodll-widget-manifest"&gt; block. Ask the AI tool to "export it for Poodll".';
$string['error:notazip'] = 'The file is not a ZIP file, or it is damaged.';
$string['error:notpublished'] = 'This widget could not be published, so learners can not see it. Save the item again to retry.';
$string['error:onezip'] = 'Upload just one widget file. Delete the others.';
$string['error:publishfailed'] = 'The widget could not be published.';
$string['error:zipbadpath'] = 'The ZIP contains a file name that is not allowed: {$a}';
$string['error:zipduplicate'] = 'The ZIP contains two files with the same name: {$a}';
$string['error:zipencrypted'] = 'The ZIP is password protected. Upload a ZIP without a password.';
$string['error:zipfiletoobig'] = 'The file {$a->file} is too big. The largest file allowed is {$a->size}.';
$string['error:zipfiletype'] = 'This kind of file is not allowed in a widget: {$a}';
$string['error:zipread'] = 'The file {$a} could not be read from the ZIP.';
$string['error:zipsymlink'] = 'The ZIP contains a link, which is not allowed: {$a}';
$string['error:ziptoobig'] = 'The ZIP is too big. The largest ZIP allowed is {$a}.';
$string['error:ziptoomanyfiles'] = 'The ZIP contains too many files. The most allowed is {$a}.';
$string['error:ziptotaltoobig'] = 'The files in the ZIP are too big in total. The most allowed is {$a}.';
$string['error:zipunreadable'] = 'The ZIP could not be read.';
$string['gradepolicy'] = 'Widget score';
$string['gradepolicy_help'] = 'Some widgets report a score, such as 3 out of 4. Choose what the lesson does with it.

* Ignore: the score is not used.
* Save, but do not grade: the score is saved with the attempt, but does not count in the lesson grade.
* Use as the item grade: the score counts in the lesson grade, scaled to the total marks. A learner who moves on before finishing scores 0.

A score sent by the widget is worked out in the learner\'s browser, so a learner could change it. Use it for practice, not for tests that matter.';
$string['gradepolicy_ignore'] = 'Ignore';
$string['gradepolicy_record'] = 'Save, but do not grade';
$string['gradepolicy_use'] = 'Use as the item grade';
$string['item_desc'] = 'Smart Frame loads an uploaded HTML page in a frame inside the Minilesson. It is responsive, secure and can generate a grade. It can be used for explainer widgets, custom interactive activities or even games.';
$string['pluginname'] = 'Smart Frame';
$string['privacy:metadata'] = 'The Smart Frame item does not store any personal data.';
$string['uilanguage'] = 'Widget language';
$string['uilanguage_auto'] = 'Automatic';
$string['uilanguage_help'] = 'The language of the widget\'s instructions, buttons and feedback. This does not change the language being learned.

Automatic uses the learner\'s own language if the widget offers it, then the lesson\'s native language, then the learner\'s Moodle language, then the widget\'s default language. Save the item once to choose from the widget\'s languages.';
$string['warning:importuilanguage'] = 'uilanguage "{$a->lang}" is not one of the widget\'s interface languages ({$a->languages}), so learners will see its default language.';
$string['widget_instructions1'] = 'Complete the activity below.';
$string['widgetnotavailable'] = 'This activity is not available right now.';
$string['widgetpackage'] = 'Widget file';
$string['widgetpackage_help'] = 'The widget: either one HTML file (what AI tools make most easily), or a ZIP for a widget with audio, video or many images.

An HTML file must hold the widget\'s manifest in a &lt;script type="application/json" id="poodll-widget-manifest"&gt; block. A ZIP must have a manifest.json and an HTML page at its top. Everything the widget uses must be in the file or the ZIP: a widget can not load files from other sites, and can not use browser storage (such as localStorage) or cookies.';
$string['widgetsummary'] = 'Current widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Languages: {$a->languages}<br>Reports a score: {$a->grade}';
