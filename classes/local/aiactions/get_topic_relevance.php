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
 * Class get_topic_relevance
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_topic_relevance extends generate_text {

    protected $referencetext;

    protected $submittedtext;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        string $referencetext,
        string $submittedtext
    ) {
        $this->referencetext = $referencetext;
        $this->submittedtext = $submittedtext;
        parent::__construct($contextid, $userid, $prompttext);
    }

    public static function get_system_instruction(): string {
        return 'You are generous language teacher.';
    }

    public function generate_prompt(): string {
        $this->prompttext = 'Determine how relevant the student response is to the assignment topic.';
        $this->prompttext .= 'Relevance should be am integer between 1 (not relevant) and 10 (highly relevant).';
        $this->prompttext .= 'Return the results as a JSON object of the format {relevance: 0} with no additional commentary.';
        $this->prompttext .= PHP_EOL . json_encode([
            'topic' => $this->referencetext,
            'student_response' => $this->submittedtext,
        ]);
        return $this->prompttext;
    }

}
