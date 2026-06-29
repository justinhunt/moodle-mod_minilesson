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

namespace minilessonitem_shadow;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;

/**
 * Renderable class for a video shadowing item in a minilesson activity.
 *
 * @package    minilessonitem_shadow
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_LISTENING, constants::SKILL_SPEAKING, constants::SKILL_PRONUNCIATION];


    /** @var string the column holding the WebVTT subtitle text */
    public const VTT = 'customtext1';

    /** @var string the column holding the per-line shadow count */
    public const LOOPCOUNT = 'customint1';

    /** @var int default number of shadow repetitions per line */
    public const DEFAULT_LOOPCOUNT = 3;

    /** @var string the column holding the CSV of line numbers to shadow ('*' = all) */
    public const SHADOWLINES = 'customtext2';

    /** @var string the column holding the pause between shadow attempts (milliseconds) */
    public const SHADOWPAUSE = 'customint2';

    /** @var string the column holding the per-word highlighting flag (1 = on) */
    public const WORDHIGHLIGHT = 'customint3';

    /** @var int default pause between shadow attempts (milliseconds) */
    public const DEFAULT_SHADOWPAUSE = 2000;

    /** @var string the shadowlines value meaning all lines */
    public const ALLLINES = '*';

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {

        $testitem = parent::export_for_template($output);

        // The parent exports itemytvideoid/start/end which questioncontent.mustache
        // would render as a generic clip player. This item owns the player, so we
        // re-home those keys and remove the originals.
        $testitem->videoid = isset($testitem->itemytvideoid) ? $testitem->itemytvideoid : '';
        $testitem->videostart = isset($testitem->itemytvideostart) ? (int)$testitem->itemytvideostart : 0;
        $testitem->videoend = isset($testitem->itemytvideoend) ? (int)$testitem->itemytvideoend : 0;
        unset($testitem->itemytvideoid, $testitem->itemytvideostart, $testitem->itemytvideoend);

        // Only cues that fall fully inside the configured clip window are kept.
        $cues = vttparser::parse((string)$this->itemrecord->{self::VTT});
        // Line numbers (1-based, against the whole VTT) are assigned before any filtering,
        // so they match what the author counts in the subtitle editor.
        foreach ($cues as $i => $unused) {
            $cues[$i]['lineno'] = $i + 1;
        }
        $clipstart = $testitem->videostart;
        $clipend = $testitem->videoend;
        $cues = array_values(array_filter($cues, function ($cue) use ($clipstart, $clipend) {
            if ($clipstart > 0 && $cue['start'] < $clipstart) {
                return false;
            }
            if ($clipend > 0 && $cue['end'] > $clipend) {
                return false;
            }
            return true;
        }));

        // With per-word highlighting off, any word timings in the VTT are dropped
        // so the player highlights whole lines only.
        if (empty($this->itemrecord->{self::WORDHIGHLIGHT})) {
            foreach ($cues as $i => $unused) {
                $cues[$i]['haswordtimings'] = false;
                $cues[$i]['words'] = [];
            }
        }

        // All remaining cues show as watch-mode subtitles, but only the selected
        // lines are shadowed in loop mode.
        $selectedlines = self::parse_shadowlines((string)$this->itemrecord->{self::SHADOWLINES});
        $shadowcount = 0;
        foreach ($cues as $i => $cue) {
            $cues[$i]['index'] = $i;
            $isshadow = $selectedlines === null || in_array($cue['lineno'], $selectedlines);
            $cues[$i]['shadow'] = $isshadow;
            if ($isshadow) {
                $shadowcount++;
            }
        }
        $testitem->cues = $cues;
        $testitem->totallines = $shadowcount;

        $loopcount = (int)$this->itemrecord->{self::LOOPCOUNT};
        $testitem->loopcount = $loopcount > 0 ? $loopcount : self::DEFAULT_LOOPCOUNT;

        $shadowpause = (int)$this->itemrecord->{self::SHADOWPAUSE};
        $testitem->shadowpause = $shadowpause > 0 ? $shadowpause : self::DEFAULT_SHADOWPAUSE;

        return $testitem;
    }

    /**
     * Parse the lines-to-shadow setting.
     *
     * @param string $value the raw setting, e.g. "1,4,5,6" or "*"
     * @return array|null the selected 1-based line numbers, or null meaning all lines
     */
    public static function parse_shadowlines(string $value): ?array {
        $value = trim($value);
        if ($value === '' || $value === self::ALLLINES) {
            return null;
        }
        $lines = [];
        foreach (explode(',', $value) as $bit) {
            $bit = trim($bit);
            if (ctype_digit($bit) && (int)$bit > 0) {
                $lines[] = (int)$bit;
            }
        }
        return $lines;
    }

    /**
     * Format seconds as a hh:mm:ss timestamp for display in the item form.
     *
     * @param int $seconds
     * @return string e.g. "00:01:30", or '' for zero (meaning not set)
     */
    public static function seconds_to_timestamp(int $seconds): string {
        if ($seconds <= 0) {
            return '';
        }
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds, 60) % 60, $seconds % 60);
    }

    /**
     * Parse a clip time form value into seconds.
     * Accepts hh:mm:ss, mm:ss or a plain number of seconds; blank means not set.
     *
     * @param string $value the submitted form value
     * @return int|null seconds, or null if the value could not be parsed
     */
    public static function timestamp_to_seconds(string $value): ?int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int)$value;
        }
        if (!preg_match('/^(\d{1,3}):([0-5]?\d)(?::([0-5]?\d))?$/', $value, $matches)) {
            return null;
        }
        if (isset($matches[3])) {
            return (int)$matches[1] * 3600 + (int)$matches[2] * 60 + (int)$matches[3];
        }
        return (int)$matches[1] * 60 + (int)$matches[2];
    }

    /**
     * Check the lines-to-shadow setting is well formed ('*' or a CSV of positive integers).
     *
     * @param string $value the raw setting
     * @return bool
     */
    public static function is_valid_shadowlines(string $value): bool {
        $value = trim($value);
        return $value === '' || $value === self::ALLLINES ||
            preg_match('/^[1-9]\d*(\s*,\s*[1-9]\d*)*$/', $value) === 1;
    }

    /**
     * Validate an imported item record.
     *
     * @param \stdClass $newrecord the record built from import data
     * @param \stdClass $cm the course module
     * @return \stdClass|false an error object, or false if there is no error
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if (empty($newrecord->{constants::YTVIDEOID})) {
            $error->col = constants::YTVIDEOID;
            $error->message = get_string('error:novideoid', 'minilessonitem_shadow');
            return $error;
        }

        if (empty($newrecord->{self::VTT}) || count(vttparser::parse($newrecord->{self::VTT})) === 0) {
            $error->col = self::VTT;
            $error->message = get_string('error:badvtt', 'minilessonitem_shadow');
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }

    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'vtt', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => self::VTT];
        $keycols['text2'] = ['jsonname' => 'shadowlines', 'type' => 'string', 'optional' => true, 'default' => self::ALLLINES, 'dbname' => self::SHADOWLINES];
        $keycols['int1'] = ['jsonname' => 'loopcount', 'type' => 'int', 'optional' => true, 'default' => self::DEFAULT_LOOPCOUNT, 'dbname' => self::LOOPCOUNT];
        $keycols['int2'] = ['jsonname' => 'shadowpause', 'type' => 'int', 'optional' => true, 'default' => self::DEFAULT_SHADOWPAUSE, 'dbname' => self::SHADOWPAUSE];
        $keycols['int3'] = ['jsonname' => 'wordhighlight', 'type' => 'int', 'optional' => true, 'default' => 1, 'dbname' => self::WORDHIGHLIGHT];
        return $keycols;
    }

    /**
     * Builds the prompt for the AI helper in the code editor.
     *
     * The editor holds WebVTT subtitles, so the AI must only touch the spoken
     * text and leave the cue structure (timing lines, identifiers, inline word
     * timestamps) intact.
     *
     * @param string $language The language of the code (always 'vtt' here).
     * @param string $prompt The user's instruction.
     * @param string $currentcode The current WebVTT in the editor.
     * @return string The full prompt for the AI.
     */
    public static function codeeditor_build_prompt($language, $prompt, $currentcode) {
        $fullprompt = "You are an assistant helping a teacher clean up WebVTT subtitles for a "
            . "language-learning video shadowing exercise." . PHP_EOL . PHP_EOL;

        $fullprompt .= "### STRICT RULES ###" . PHP_EOL;
        $fullprompt .= "- Output valid WebVTT only. Keep the 'WEBVTT' header and any 'Kind'/'Language' lines." . PHP_EOL;
        $fullprompt .= "- Do NOT change, add or remove any timing line (e.g. '00:00:01.200 --> 00:00:03.360')." . PHP_EOL;
        $fullprompt .= "- Keep every cue identifier line exactly as is (e.g. 'line-number: 01'). Do not renumber them." . PHP_EOL;
        $fullprompt .= "- Preserve any inline word timestamps (e.g. '<00:00:10.100>') and their positions within the text." . PHP_EOL;
        $fullprompt .= "- Keep the same number of cues, in the same order. Only edit the spoken text of each cue." . PHP_EOL;
        $fullprompt .= "- Do not translate the text or change its meaning." . PHP_EOL . PHP_EOL;

        $fullprompt .= "The teacher's instruction (e.g. add punctuation, fix spelling and capitalization): "
            . $prompt . PHP_EOL . PHP_EOL;

        $fullprompt .= "The current WebVTT is:" . PHP_EOL . "---" . PHP_EOL . $currentcode . PHP_EOL . "---" . PHP_EOL;
        $fullprompt .= "Return only the full updated WebVTT, without any explanations or markdown code blocks.";
        return $fullprompt;
    }
}
