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

namespace mod_minilesson;

/**
 * Class module
 *
 * @package    mod_minilesson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_minilesson_module {

    const STATE_TERMS = 'terms';
    const STATE_STEP1 = 'step1practicetype';
    const STATE_STEP2 = 'step2practicetype';
    const STATE_STEP3 = 'step3practicetype';
    const STATE_STEP4 = 'step4practicetype';
    const STATE_STEP5 = 'step5practicetype';
    const STATE_END = 'end';

    protected static $states = [
        self::STATE_TERMS,
        self::STATE_STEP1,
        self::STATE_STEP2,
        self::STATE_STEP3,
        self::STATE_STEP4,
        self::STATE_STEP5,
        self::STATE_END,
    ];

    protected $course;
    protected $cm;
    protected $context;
    protected $mod;

    protected function __construct($course, $cm, $mod = null) {
        global $DB;
        $this->course = $course;
        $this->cm = $cm;
        // TODO: Is the DB table correct for this structure?
        $this->mod = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = context_module::instance($cm->id);
    }

    public static function get_by_modid($modid) {
        list($course, $cm) = get_course_and_cm_from_instance($modid, 'minilesson');
        return new static($course, $cm);
    }

    public function get_mod() {
        return $this->mod;
    }
}
