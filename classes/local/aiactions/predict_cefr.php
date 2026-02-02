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
 * Class predict_cefr
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class predict_cefr extends request_grammar_correction {

    protected $originaltext;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        string $originaltext,
        string $language = "English"
    ) {
        $this->originaltext = $originaltext;
        return generate_text::__construct($contextid, $userid, $prompttext, $language);
    }

    public function generate_prompt(): string {
        $this->prompttext = "Evaluate the {$this->language} CEFR level of the following passage.";
        $this->prompttext .= "Return the answer as one of 'A1','A2','B1','B2','C1','C2' with no additional text: ";
        $this->prompttext .= PHP_EOL . $this->originaltext;
        return $this->prompttext;
    }
}
