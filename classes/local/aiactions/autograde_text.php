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

namespace mod_minilesson\local\aiactions;

use mod_minilesson\aimanager;
use mod_minilesson\utils;

/**
 * Class autograde_text
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class autograde_text extends generate_text {

    protected $submittedtext;

    protected $instructions;

    protected $isspeech;

    protected $language;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        string $submittedtext,
        string $instructions,
        bool $isspeech = false,
        string $language = 'English'
    ) {
        $this->submittedtext = $submittedtext;
        $this->instructions = $instructions;
        $this->isspeech = $isspeech;
        $this->language = $language;
        parent::__construct($contextid, $userid, $prompttext);
    }

    public function generate_prompt(): string {
        $this->prompttext = "Evaluate the following passage of {$this->language} ( between [[ and ]]) written by a student. " . PHP_EOL;
        $this->prompttext .= 'Return a JSON object with these three properties: {"submittedtext":"string","correctedtext":"string","marks":"number","feedback":"array"}.' . PHP_EOL;
        if ($this->isspeech) {
            $this->prompttext .= "Correct any grammar mistakes in the student's $this->language and set to the \"correctedtext\" property.";
            $this->prompttext .= "Do not correct spelling or punctuation because this is an audio transcript." . PHP_EOL;
        } else {
            $this->prompttext .= "Correct any grammar mistakes in the student's {$this->language} and set to the \"correctedtext\" property." . PHP_EOL;
        }

        $instructionsobj = json_decode($this->instructions);
        $feedbackscheme = $instructionsobj->feedbackscheme;
        $feedbacklanguage = $instructionsobj->feedbacklanguage;
        $markscheme = $instructionsobj->markscheme;
        $maxmarks = $instructionsobj->maxmarks;

        if (!empty($markscheme)) {
            $this->prompttext .= 'Set the "marks" property to a single number summing all marks.';
            $this->prompttext .= " The maximum score is: $maxmarks .";
            $this->prompttext .= ' ' . $markscheme;
            $this->prompttext .= PHP_EOL;
        } else {
            $this->prompttext .= 'Set "marks" to null in the json object.' . PHP_EOL;
        }

        if (!empty($feedbackscheme)) {
            $this->prompttext .= 'Set an array of strings to the "feedback" property. Each string is a feedback item.';
            $this->prompttext .= ' ' . trim($feedbackscheme);
            if (!empty($feedbacklanguage)) {
                $feedbacklanguage = utils::get_lang_english_name($feedbacklanguage);
                if ($feedbacklanguage !== $this->language) {
                    $this->prompttext .= ' Give feedback in ' . $feedbacklanguage . '.';
                }
            }
            $this->prompttext .= PHP_EOL;
        } else {
            $this->prompttext .= 'Set "feedback" to null in the json object.' . PHP_EOL;
        }

        if ($this->isspeech) {
            $punctuated = preg_match('/[.,!?;:()\[\]{}"«»„“”‹›¡¿،؛؟。、「」『』【】《》]/u', $this->submittedtext);
            if ($punctuated) {
                $punctuatedresponse = aimanager::call_ai_provider_action(
                    punctuation::class,
                    [
                        'contextid' => $this->contextid,
                        'userid' => $this->userid,
                        'prompttext' => '',
                        'language' => $this->language,
                        'passage' => $this->submittedtext,
                    ]
                );
                if (is_object($punctuatedresponse)) {
                    $punctuatedresponseobject = json_decode($punctuatedresponse->returnMessage);
                    if (!json_last_error()) {
                        $this->submittedtext = $punctuatedresponseobject->passage;
                    }
                }
            }
        }

        $responsetext = strip_tags($this->submittedtext);
        $responsetext = '[[' . $responsetext . ']]';
        $this->prompttext .= $responsetext;

        return $this->prompttext;
    }

    public static function get_system_instruction(): string {
        return 'You are a language teacher';
    }
}
