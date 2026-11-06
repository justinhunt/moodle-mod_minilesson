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

$string['additem'] = 'Widget';
$string['error:manifestentry'] = 'The manifest\'s "entry" ({$a}) must name an HTML file in the ZIP.';
$string['error:manifestfield'] = 'The manifest\'s "{$a}" is missing or not valid.';
$string['error:manifestjson'] = 'manifest.json is not valid JSON.';
$string['error:manifestprotocol'] = 'This widget needs a newer lesson host. This host speaks protocol version {$a}.';
$string['error:manifestschema'] = 'The manifest\'s "schemaVersion" must be {$a}.';
$string['error:nomanifest'] = 'The ZIP has no manifest.json at its top level.';
$string['error:notazip'] = 'The file is not a ZIP file, or it is damaged.';
$string['error:notpublished'] = 'This widget could not be published, so learners can not see it. Save the item again to retry.';
$string['error:onezip'] = 'Upload just one widget ZIP. Delete the others.';
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
$string['item_desc'] = 'The Widget item runs an interactive activity uploaded as a ZIP, such as one made with ChatGPT or Claude. It can report a score to the lesson.';
$string['pluginname'] = 'Widget';
$string['privacy:metadata'] = 'The Widget item does not store any personal data.';
$string['uilanguage'] = 'Widget language';
$string['uilanguage_auto'] = 'Automatic';
$string['uilanguage_help'] = 'The language of the widget\'s instructions, buttons and feedback. This does not change the language being learned.

Automatic uses the learner\'s own language if the widget offers it, then the lesson\'s native language, then the learner\'s Moodle language, then the widget\'s default language. Save the item once to choose from the widget\'s languages.';
$string['widget_instructions1'] = 'Complete the activity below.';
$string['widgetnotavailable'] = 'This activity is not available right now.';
$string['widgetpackage'] = 'Widget ZIP';
$string['widgetpackage_help'] = 'A Poodll widget package: a ZIP with a manifest.json and an HTML page at its top level, plus any CSS, JavaScript, images, audio and language files it uses. Everything must be in the ZIP. A widget can not load files from other sites, and can not use browser storage (such as localStorage) or cookies.';
$string['widgetsummary'] = 'Current widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Languages: {$a->languages}<br>Reports a score: {$a->grade}';
