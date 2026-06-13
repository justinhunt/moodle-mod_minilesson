<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Video Shadowing mod_minilesson
 *
 * @package    minilessonitem_shadow
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_shadow;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform {
    public function custom_definition() {
        global $PAGE;
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('shadow_instructions1', 'minilessonitem_shadow'));

        // Add a heading for this form.
        $this->add_itemsettings_heading();

        // The media prompts section (plain-prompt mode only) contains a youtube clip group
        // using the same DB columns this item uses for its video. Remove it so the form
        // does not contain duplicate element names.
        if ($mform->elementExists('ytarray')) {
            $mform->removeElement('ytarray');
        }

        // The youtube clip for this item. Start/end times are entered as hh:mm:ss
        // but stored as seconds (see set_data/get_data below).
        $ytarray = [];
        $ytarray[] =& $mform->createElement('text', constants::YTVIDEOID, get_string('itemytid', constants::M_COMPONENT),
            ['size' => 25, 'placeholder' => 'Video ID or URL']);
        $ytarray[] =& $mform->createElement('text', constants::YTVIDEOSTART, get_string('itemytstart', constants::M_COMPONENT),
            ['size' => 8, 'placeholder' => '00:00:00']);
        $ytarray[] =& $mform->createElement('html', ' - ');
        $ytarray[] =& $mform->createElement('text', constants::YTVIDEOEND, get_string('itemytend', constants::M_COMPONENT),
            ['size' => 8, 'placeholder' => '00:00:00']);
        if (!empty(get_config('minilessonitem_shadow', 'enablesubtitlefetch'))) {
            $ytarray[] =& $mform->createElement('button', 'fetchvtt', get_string('fetchvtt', 'minilessonitem_shadow'));
        }
        $mform->addGroup($ytarray, 'shadowytclip', get_string('ytclipdetails', 'minilessonitem_shadow'), [' '], false);
        $mform->setType(constants::YTVIDEOID, PARAM_RAW);
        $mform->setType(constants::YTVIDEOSTART, PARAM_TEXT);
        $mform->setType(constants::YTVIDEOEND, PARAM_TEXT);

        // Whether words are highlighted individually as they are spoken. YouTube's
        // word timings are patchy on some videos, so the author can turn this off;
        // fetching then skips word timestamps and the player highlights whole lines.
        $this->add_checkbox(
            itemtype::WORDHIGHLIGHT,
            get_string('wordhighlight', 'minilessonitem_shadow'),
            get_string('wordhighlight_details', 'minilessonitem_shadow'),
            1
        );

        // The WebVTT subtitles, edited in a code editor. The transcriptfetch module
        // sets up the code editor itself (so it can write fetched VTT into it) and
        // wires the fetch-from-youtube button.
        $this->add_static_text('shadowvtt_desc', '', get_string('shadowvtt_desc', 'minilessonitem_shadow'));
        $fixedwidthfont = true;
        $this->add_textarearesponse(itemtype::VTT, get_string('shadowvtt', 'minilessonitem_shadow'), true, $fixedwidthfont);
        $PAGE->requires->js_call_amd(
            'minilessonitem_shadow/transcriptfetch',
            'init',
            ['id_' . itemtype::VTT, [
                'buttonid' => 'id_fetchvtt',
                'ytfieldid' => 'id_' . constants::YTVIDEOID,
                'wordhighlightid' => 'id_' . itemtype::WORDHIGHLIGHT,
                'contextid' => $this->context->id,
                'lang' => $this->moduleinstance->ttslanguage,
            ]]
        );

        // How many times the student shadows each line.
        $loopoptions = array_combine(range(1, 10), range(1, 10));
        $this->add_dropdown(itemtype::LOOPCOUNT, get_string('loopcount', 'minilessonitem_shadow'),
            $loopoptions, itemtype::DEFAULT_LOOPCOUNT);

        // The pause before each shadow attempt, stored in milliseconds.
        $pauseoptions = [];
        foreach ([1000, 1500, 2000, 2500, 3000, 3500, 4000] as $ms) {
            $pauseoptions[$ms] = number_format($ms / 1000, 1);
        }
        $this->add_dropdown(itemtype::SHADOWPAUSE, get_string('shadowpause', 'minilessonitem_shadow'),
            $pauseoptions, itemtype::DEFAULT_SHADOWPAUSE);

        // Which subtitle lines to shadow: '*' for all, or a CSV of 1-based line numbers.
        $mform->addElement('text', itemtype::SHADOWLINES, get_string('shadowlines', 'minilessonitem_shadow'),
            ['size' => 30, 'placeholder' => itemtype::ALLLINES]);
        $mform->setType(itemtype::SHADOWLINES, PARAM_TEXT);
        $mform->setDefault(itemtype::SHADOWLINES, itemtype::ALLLINES);
        $this->add_static_text('shadowlines_desc', '', get_string('shadowlines_desc', 'minilessonitem_shadow'));
    }

    /**
     * Convert the stored start/end seconds to hh:mm:ss for display.
     *
     * @param \stdClass|array $data the item record
     */
    public function set_data($data) {
        $data = (object)$data;
        foreach ([constants::YTVIDEOSTART, constants::YTVIDEOEND] as $field) {
            if (isset($data->{$field}) && is_numeric($data->{$field})) {
                $data->{$field} = itemtype::seconds_to_timestamp((int)$data->{$field});
            }
        }
        parent::set_data($data);
    }

    /**
     * Convert the submitted hh:mm:ss start/end times back to seconds for storage.
     *
     * @return \stdClass|null the form data
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            foreach ([constants::YTVIDEOSTART, constants::YTVIDEOEND] as $field) {
                if (isset($data->{$field})) {
                    $data->{$field} = (int)itemtype::timestamp_to_seconds((string)$data->{$field});
                }
            }
        }
        return $data;
    }

    /**
     * Validate the submitted item settings.
     *
     * @param array $data the submitted form data
     * @param array $files the submitted files
     * @return array of element name => error message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty(trim($data[constants::YTVIDEOID] ?? ''))) {
            $errors['shadowytclip'] = get_string('error:novideoid', 'minilessonitem_shadow');
        }

        $start = itemtype::timestamp_to_seconds((string)($data[constants::YTVIDEOSTART] ?? ''));
        $end = itemtype::timestamp_to_seconds((string)($data[constants::YTVIDEOEND] ?? ''));
        if ($start === null || $end === null) {
            $errors['shadowytclip'] = get_string('error:badtimestamp', 'minilessonitem_shadow');
            // Treat unparseable values as unset for the checks below.
            $start = $start ?? 0;
            $end = $end ?? 0;
        }
        if ($start > 0 && $end > 0 && $end <= $start) {
            $errors['shadowytclip'] = get_string('error:startafterend', 'minilessonitem_shadow');
        }

        $shadowlines = (string)($data[itemtype::SHADOWLINES] ?? itemtype::ALLLINES);
        if (!itemtype::is_valid_shadowlines($shadowlines)) {
            $errors[itemtype::SHADOWLINES] = get_string('error:badshadowlines', 'minilessonitem_shadow');
        }

        $cues = vttparser::parse($data[itemtype::VTT] ?? '');
        if (count($cues) === 0) {
            $errors[itemtype::VTT] = get_string('error:badvtt', 'minilessonitem_shadow');
        } else {
            // Cues outside the clip window are excluded at runtime, so at least one must fit inside it.
            // array_filter preserves keys, so key + 1 is still the line number in the whole VTT.
            $cuesinclip = array_filter($cues, function ($cue) use ($start, $end) {
                if ($start > 0 && $cue['start'] < $start) {
                    return false;
                }
                if ($end > 0 && $cue['end'] > $end) {
                    return false;
                }
                return true;
            });
            if (count($cuesinclip) === 0) {
                $errors[itemtype::VTT] = get_string('error:nocuesinclip', 'minilessonitem_shadow');
            } else if (!isset($errors[itemtype::SHADOWLINES])) {
                // At least one of the selected lines must survive the clip window.
                $selectedlines = itemtype::parse_shadowlines($shadowlines);
                if ($selectedlines !== null) {
                    $shadowable = 0;
                    foreach ($cuesinclip as $i => $unused) {
                        if (in_array($i + 1, $selectedlines)) {
                            $shadowable++;
                        }
                    }
                    if ($shadowable === 0) {
                        $errors[itemtype::SHADOWLINES] = get_string('error:noshadowlines', 'minilessonitem_shadow');
                    }
                }
            }
        }

        return $errors;
    }
}
