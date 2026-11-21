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

namespace minilessonitem_conversation;

use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Conversation item: the student speaks one side of a pre-written dialog, and a TTS voice speaks the other.
 *
 * The teacher writes the dialog one turn per line, starting each line with "A)" or "B)", as in the TTS dialog.
 * The student's next line waits in their part of the chat as a dimmed prompt. With auto check, speech
 * recognition must match it before the conversation moves on. With self check, the line is only recorded,
 * and the student can play it back and redo it. At the end the student can play back the whole conversation.
 * Recordings stay in the browser; nothing the student says is stored.
 *
 * @package    minilessonitem_conversation
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_SPEAKING, constants::SKILL_LISTENING];

    /** @var string Column holding the dialog, one "A) ..." or "B) ..." turn per line. */
    public const DIALOG = 'customtext1';
    /** @var string Column holding the TTS voice for speaker A. */
    public const VOICEA = constants::POLLYVOICE;
    /** @var string Column holding the TTS voice for speaker B. */
    public const VOICEB = 'customtext3';
    /** @var string Column holding the display name of speaker A. */
    public const LABELA = 'customtext4';
    /** @var string Column holding the display name of speaker B. */
    public const LABELB = 'customtext6';
    /** @var string Column holding which part the student plays (ROLE_*). */
    public const STUDENTROLE = 'customint1';
    /** @var string Column holding how the student's lines are checked (CHECK_*). */
    public const CHECKMODE = 'customint2';
    /** @var string Column holding how much of the student's line the prompt shows (PROMPT_*). */
    public const PROMPTHELP = 'customint3';
    /** @var string Column holding how the prompt looks (LOOK_*). */
    public const PROMPTLOOK = 'customint5';
    /** @var string File area for the optional scene picture shown behind the chat. */
    public const SCENE_FILEAREA = constants::FILEANSWER . '1_image';

    /** @var int The student plays A. */
    public const ROLE_A = 1;
    /** @var int The student plays B. */
    public const ROLE_B = 2;
    /** @var int The student picks a part on the start screen. */
    public const ROLE_CHOOSE = 3;

    /** @var int Speech recognition must match the line. */
    public const CHECK_AUTO = 0;
    /** @var int The line is only recorded, and always accepted. */
    public const CHECK_SELF = 1;

    /** @var int The prompt shows the whole line. */
    public const PROMPT_FULL = 0;
    /** @var int The prompt shows the first letter of each word. */
    public const PROMPT_INITIALS = 1;
    /** @var int The prompt hides the words. */
    public const PROMPT_HIDDEN = 2;

    /** @var int Grey text in a dashed box. */
    public const LOOK_DOTTED = 0;
    /** @var int Grey text only. */
    public const LOOK_GREY = 1;

    /** @var int The longest recording of one line, in seconds. Silence usually ends it sooner. */
    public const MAXRECORDINGTIME = 20;

    /**
     * The class constructor.
     *
     * @param stdClass $itemrecord
     * @param stdClass|false $moduleinstance
     * @param \context|false $context
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needsspeechrec = true;
    }

    /**
     * The start screen is a white card.
     *
     * @return bool
     */
    public function uses_boxed_layout() {
        return true;
    }

    /**
     * The end of the conversation is this item's results page.
     *
     * @return bool
     */
    public function has_results_page() {
        return true;
    }

    /**
     * Split the dialog text into turns.
     *
     * A line starting "A)" or "B)" is a turn for that speaker. A line with no prefix is another turn for the
     * previous speaker (or for A, if it is the first line). Empty lines are skipped.
     *
     * @param string $dialog the dialog text
     * @return array of objects with speaker ('A' or 'B') and text
     */
    public static function parse_dialog($dialog) {
        $turns = [];
        $speaker = 'A';
        foreach (preg_split('/\R/u', (string) $dialog) as $line) {
            $line = utils::super_trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([AaBb])\)\s*(.*)$/u', $line, $matches)) {
                $speaker = strtoupper($matches[1]);
                $line = utils::super_trim($matches[2]);
                if ($line === '') {
                    continue;
                }
            }
            $turn = new stdClass();
            $turn->speaker = $speaker;
            $turn->text = $line;
            $turns[] = $turn;
        }
        return $turns;
    }

    /**
     * The dialog as plain text, one turn per line with no speaker prefixes.
     * This is what speech recognition (phonetics and language models) works from.
     *
     * @param string $dialog the dialog text
     * @return string
     */
    public static function plain_dialog($dialog) {
        return implode(PHP_EOL, array_map(function ($turn) {
            return $turn->text;
        }, self::parse_dialog($dialog)));
    }

    /**
     * Run a base class speech function on the dialog without its speaker prefixes, so the prefixes do not
     * end up in the phonetics or the language model, and the phonetics line up with the turns.
     *
     * @param stdClass|false $olditemrecord the item as it was before this save, if any
     * @param callable $fn function($olditemrecord) to call while the plain dialog is in place
     * @return mixed what $fn returned
     */
    protected function with_plain_dialog($olditemrecord, callable $fn) {
        $dialog = $this->itemrecord->{self::DIALOG} ?? '';
        $this->itemrecord->{self::DIALOG} = self::plain_dialog($dialog);
        if ($olditemrecord) {
            $olditemrecord = clone $olditemrecord;
            $olditemrecord->{self::DIALOG} = self::plain_dialog($olditemrecord->{self::DIALOG} ?? '');
        }
        try {
            return $fn($olditemrecord);
        } finally {
            $this->itemrecord->{self::DIALOG} = $dialog;
        }
    }

    /**
     * Build the language model from the turns only.
     *
     * @param stdClass|false $olditemrecord
     * @return bool
     */
    public function update_create_langmodel($olditemrecord) {
        return $this->with_plain_dialog($olditemrecord, function ($old) {
            return parent::update_create_langmodel($old);
        });
    }

    /**
     * Build the phonetics from the turns only, one line per turn.
     *
     * @param stdClass|false $olditemrecord
     * @return string
     */
    public function update_create_phonetic($olditemrecord) {
        return $this->with_plain_dialog($olditemrecord, function ($old) {
            return parent::update_create_phonetic($old);
        });
    }

    /**
     * The URL of the scene picture, or an empty string if there is none.
     *
     * @return string
     */
    protected function fetch_scene_url() {
        if (empty($this->itemrecord->id)) {
            return '';
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            constants::M_COMPONENT,
            self::SCENE_FILEAREA,
            $this->itemrecord->id,
            'f.sortorder DESC, f.id ASC',
            false
        );
        // Only one picture is used: the main file if one is set, else the first added.
        foreach ($files as $file) {
            return \moodle_url::make_pluginfile_url(
                $this->context->id,
                constants::M_COMPONENT,
                self::SCENE_FILEAREA,
                $this->itemrecord->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
        return '';
    }

    /**
     * Export the data for the mustache template, and for the item's JS.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output) {
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->newui = true;

        $record = $this->itemrecord;
        $testitem->alternates = $record->{constants::ALTERNATES} ?? '';
        $testitem->labela = utils::super_trim($record->{self::LABELA} ?? '') ?: 'A';
        $testitem->labelb = utils::super_trim($record->{self::LABELB} ?? '') ?: 'B';

        $role = (int) ($record->{self::STUDENTROLE} ?? self::ROLE_B);
        $testitem->choosemode = $role === self::ROLE_CHOOSE;
        $testitem->studentrole = $role === self::ROLE_A ? 'A' : 'B';
        $testitem->selfcheck = (int) ($record->{self::CHECKMODE} ?? 0) === self::CHECK_SELF;
        $prompthelp = (int) ($record->{self::PROMPTHELP} ?? 0);
        $testitem->prompthelp = $prompthelp === self::PROMPT_INITIALS ? 'initials'
            : ($prompthelp === self::PROMPT_HIDDEN ? 'hidden' : 'full');
        $testitem->promptgrey = (int) ($record->{self::PROMPTLOOK} ?? 0) === self::LOOK_GREY;
        $testitem->sceneurl = $this->fetch_scene_url();

        // The turns. Each has its speaker's TTS audio: the partner's lines are spoken with it, and the
        // student's lines use it for "Hear it" and for playback when there is no recording.
        $voices = ['A' => $record->{self::VOICEA} ?? '', 'B' => $record->{self::VOICEB} ?? ''];
        if (empty($voices['B'])) {
            $voices['B'] = $voices['A'];
        }
        $voiceoption = $record->{constants::POLLYOPTION} ?? constants::TTS_NORMAL;
        $phonetics = !empty($record->phonetic) ? explode(PHP_EOL, $record->phonetic) : [];
        $lines = [];
        foreach (self::parse_dialog($record->{self::DIALOG} ?? '') as $index => $turn) {
            $line = new stdClass();
            $line->index = $index;
            $line->speaker = $turn->speaker;
            $line->text = $turn->text;
            $line->phonetic = isset($phonetics[$index]) ? utils::super_trim($phonetics[$index]) : '';
            // What speech recognition compares against. Japanese is compared as segmented words.
            $line->target = $turn->text;
            if ($this->language == constants::M_LANG_JAJP) {
                $line->target = $this->process_japanese_phonetics($turn->text, $line->phonetic ?: false);
            }
            $line->audiourl = utils::fetch_polly_url(
                $this->token,
                $this->region,
                $turn->text,
                $voiceoption,
                $voices[$turn->speaker],
                $this->moduleinstance->id
            );
            $lines[] = $line;
        }
        $testitem->lines = $lines;

        // The streaming speech recognition token, as in listen and repeat.
        $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
        if ($tokenobject) {
            $testitem->speechtoken = $tokenobject->token;
            $testitem->speechtokenregion = $tokenobject->region;
            $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
            $testitem->speechtokentype = $tokenobject->tokentype;
        } else {
            $testitem->speechtoken = false;
            $testitem->speechtokenregion = '';
            $testitem->speechtokenvalidseconds = 0;
            $testitem->speechtokentype = '';
        }
        if (get_config(constants::M_COMPONENT, 'alternatestreaming')) {
            $testitem->forcestreaming = true;
        }

        $testitem = $this->set_cloudpoodll_details($testitem, self::MAXRECORDINGTIME);
        return $testitem;
    }

    /**
     * The student's lines, for the item review.
     *
     * @param stdClass $result
     * @param stdClass $itemquizdata
     */
    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = true;
        $result->correctans = array_map(function ($line) {
            return $line->speaker . ') ' . $line->text;
        }, $itemquizdata->lines ?? []);
        $result->hasanswerdetails = false;
    }

    /**
     * Validates an import record for this item type.
     *
     * @param stdClass $newrecord the db-ready import record
     * @param stdClass $cm the course module
     * @return false|stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $turns = self::parse_dialog($newrecord->{self::DIALOG} ?? '');
        $speakers = array_unique(array_map(function ($turn) {
            return $turn->speaker;
        }, $turns));
        if (count($speakers) < 2) {
            $error = new stdClass();
            $error->col = self::DIALOG;
            $error->message = get_string('error:dialogspeakers', 'minilessonitem_conversation');
            return $error;
        }
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'The learner takes one part (A or B) in a pre-written two-person dialog, for example a textbook '
            . 'dialog, and speaks their lines aloud while a TTS voice speaks the other part. It is not an AI chat: '
            . 'every line is fixed. Use it for role-play practice of a set dialog (ordering in a cafe, meeting a '
            . 'client). Optionally a scene picture sits behind the chat. Requires a lesson language with speech '
            . 'recognition support for auto check.';
    }

    /**
     * Import/export column mapping for this item type.
     *
     * @return array
     */
    public static function get_keycolumns() {
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'dialog', 'type' => 'stringarray', 'optional' => false, 'default' => [],
            'dbname' => self::DIALOG];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [],
            'dbname' => constants::ALTERNATES];
        $keycols['text3'] = ['jsonname' => 'voiceb', 'type' => 'voice', 'optional' => true, 'default' => null,
            'dbname' => self::VOICEB];
        $keycols['text4'] = ['jsonname' => 'labela', 'type' => 'string', 'optional' => true, 'default' => '',
            'dbname' => self::LABELA];
        $keycols['text5'] = ['jsonname' => 'voicea', 'type' => 'voice', 'optional' => true, 'default' => null,
            'dbname' => self::VOICEA];
        $keycols['text6'] = ['jsonname' => 'labelb', 'type' => 'string', 'optional' => true, 'default' => '',
            'dbname' => self::LABELB];
        $keycols['int1'] = ['jsonname' => 'studentrole', 'type' => 'int', 'optional' => true, 'default' => self::ROLE_B,
            'dbname' => self::STUDENTROLE];
        $keycols['int2'] = ['jsonname' => 'checkmode', 'type' => 'int', 'optional' => true, 'default' => self::CHECK_AUTO,
            'dbname' => self::CHECKMODE];
        $keycols['int3'] = ['jsonname' => 'prompthelp', 'type' => 'int', 'optional' => true, 'default' => self::PROMPT_FULL,
            'dbname' => self::PROMPTHELP];
        $keycols['int4'] = ['jsonname' => 'voiceopt', 'type' => 'voiceopts', 'optional' => true, 'default' => null,
            'dbname' => constants::POLLYOPTION];
        $keycols['int5'] = ['jsonname' => 'promptlook', 'type' => 'int', 'optional' => true, 'default' => self::LOOK_DOTTED,
            'dbname' => self::PROMPTLOOK];
        $keycols['fileanswer_image'] = ['jsonname' => self::SCENE_FILEAREA, 'type' => 'anonymousfile', 'optional' => true,
            'default' => null, 'dbname' => false];
        return $keycols;
    }
}
