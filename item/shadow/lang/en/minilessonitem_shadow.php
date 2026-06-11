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
 * English language pack for Video Shadowing
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video Shadowing';
$string['enablesubtitlefetch'] = 'Enable subtitle fetch button';
$string['enablesubtitlefetch_details'] = 'Shows a "Fetch subtitles" button on the item form that downloads a YouTube video\'s subtitles into the subtitle editor. Note: fetching subtitles will not always work, and may stop working at any time. It is a utility tool that Poodll does not guarantee will always be available.';
$string['error:badtimestamp'] = 'Clip start and end times must be in hh:mm:ss format, e.g. 00:01:30.';
$string['error:subtitlefetchdisabled'] = 'Subtitle fetching is disabled on this site.';
$string['error:badshadowlines'] = 'Lines to shadow must be * (all lines) or a comma-separated list of line numbers, e.g. 1,4,5,6.';
$string['error:badvtt'] = 'The subtitles could not be parsed. Enter valid WebVTT with at least one timed cue.';
$string['error:noshadowlines'] = 'None of the selected line numbers match a subtitle line inside the clip start and end times.';
$string['fetchvtt'] = 'Fetch subtitles';
$string['fetchvtt_failed'] = 'Could not fetch subtitles from YouTube.';
$string['fetchvtt_invalidurl'] = 'Enter a valid YouTube URL or 11 character video ID first.';
$string['fetchvtt_overwrite'] = 'This will replace the subtitles currently in the editor. Continue?';
$string['fetchvtt_overwrite_title'] = 'Replace subtitles?';
$string['error:nocuesinclip'] = 'No subtitle lines fall fully inside the clip start and end times. Adjust the times or the subtitles.';
$string['error:novideoid'] = 'A YouTube video ID or URL is required.';
$string['error:startafterend'] = 'The clip end time must be after the start time.';
$string['item_desc'] = 'The Video Shadowing item plays a YouTube clip line by line. Students "shadow" each subtitle line, speaking along with the video as the words highlight.';
$string['loopcount'] = 'Shadow count per line';
$string['loopcount_desc'] = 'How many times each line is replayed for the student to shadow.';
$string['loopindicator'] = 'Shadow: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Next';
$string['pluginname'] = 'Video Shadowing';
$string['privacy:metadata'] = 'The Video Shadowing plugin doesn\'t store any personal data.';
$string['retry'] = 'Retry';
$string['rotatedevice'] = 'Please rotate your device to portrait mode to continue.';
$string['shadow_instructions1'] = 'Watch the video. Then shadow each line: listen, and speak along with the video as it replays.';
$string['shadowlines'] = 'Lines to shadow';
$string['shadowpause'] = 'Pause between shadows (seconds)';
$string['shadowlines_desc'] = 'Subtitle line numbers to shadow, counted from 1 in the subtitles below, e.g. 1,4,5,6. Use * to shadow all lines. Other lines still display while watching.';
$string['shadowvtt'] = 'Subtitles (WebVTT)';
$string['shadowvtt_desc'] = 'Paste or edit the WebVTT subtitles for the clip. Word-level inline timestamps (e.g. &lt;00:00:10.100&gt;word) enable per-word highlighting; plain cues highlight the whole line.';
$string['startshadowing'] = 'Start shadowing';
$string['watchhint'] = 'Press play and watch the clip. When it finishes, press "Start shadowing".';
$string['ytclipdetails'] = 'YouTube clip (ID/URL, start and end seconds)';
