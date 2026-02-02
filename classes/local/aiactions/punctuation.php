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

/**
 * Class punctuation
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class punctuation extends generate_text {

    protected $passage;

    protected $language;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        string $passage,
        string $language = 'English'
    ) {
        $this->passage = $passage;
        $this->language = $language;
        return parent::__construct($contextid, $userid, $prompttext);
    }

    public static function get_system_instruction(): string {
        return "You are a helpful assistant.";
    }

    public function generate_prompt(): string {
        $this->prompttext = "Add punctuation to this passage of spoken {$this->language}. Do not add, remove or change any of the words. Do not correct grammar or word usage. ";
        $this->prompttext .= "Return the article as a JSON object of the form {\"passage\": \"updated passage\"}. ";
        $this->prompttext .= "The passage is:\n\n{$this->passage}";
        return $this->prompttext;
    }
}
