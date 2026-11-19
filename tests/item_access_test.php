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

use mod_minilesson\local\itemtype\item;

/**
 * Access checks on creating lessons and editing their items.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson_external
 * @covers     \mod_minilesson\local\itemtype\item::delete_item
 */
final class item_access_test extends \advanced_testcase {
    /** @var \stdClass the course */
    protected $course;

    /** @var \stdClass a lesson's course module */
    protected $cm;

    /** @var \stdClass a second lesson's course module, in another course */
    protected $othercm;

    /** @var \stdClass a student in the first course */
    protected $student;

    /** @var \stdClass an editing teacher in the first course */
    protected $teacher;

    /**
     * Load the library files.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/minilesson/lib.php');
    }

    /**
     * Two courses with a lesson each, a student and a teacher in the first.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $othercourse = $gen->create_course();
        $this->cm = $this->create_lesson($this->course);
        $this->othercm = $this->create_lesson($othercourse);
        $this->student = $gen->create_and_enrol($this->course, 'student');
        $this->teacher = $gen->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Create an empty lesson.
     *
     * @param \stdClass $course the course
     * @return \stdClass the course module
     */
    protected function create_lesson(\stdClass $course): \stdClass {
        $moduledata = (object) [
            'name' => 'Lesson',
            'modulename' => 'minilesson',
            'pagelayout' => 'standard',
            'showqtitles' => 1,
            'maxattempts' => 0,
            'ttslanguage' => constants::M_LANG_ENUS,
            'region' => 'useast1',
            'transcriber' => constants::TRANSCRIBER_AUTO,
            'richtextprompt' => 0,
            'containerwidth' => constants::M_CONTWIDTH_COMPACT,
            'activitylink' => 0,
            'foriframe' => 0,
            'grade' => 100,
            'visible' => 1,
            'nativelang' => constants::M_LANG_ENUS,
            'nativetranslation' => 0,
            'nextbuttonalign' => constants::M_NEXTBUTTON_DEFAULT,
            'showitemreview' => 1,
        ];
        $cmid = utils::create_instance($moduledata, $course, 0);
        return get_coursemodule_from_id('minilesson', $cmid, 0, false, MUST_EXIST);
    }

    /**
     * Add a page item to a lesson.
     *
     * @param \stdClass $cm the lesson's course module
     * @return int the item id
     */
    protected function create_item(\stdClass $cm): int {
        global $DB;
        return $DB->insert_record(constants::M_QTABLE, (object) [
            'minilesson' => $cm->instance,
            'type' => constants::TYPE_PAGE,
            'name' => 'Item',
            'itemorder' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * The lesson's module context id.
     *
     * @param \stdClass $cm the course module
     * @return int
     */
    protected function contextid(\stdClass $cm): int {
        return \context_module::instance($cm->id)->id;
    }

    /**
     * A student cannot create a lesson.
     */
    public function test_create_instance_denied_for_student(): void {
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        \mod_minilesson_external::create_instance($this->course->id, '{}');
    }

    /**
     * A user cannot create a lesson in a course they are not in.
     */
    public function test_create_instance_denied_outside_course(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $this->expectException(\require_login_exception::class);
        \mod_minilesson_external::create_instance($this->course->id, '{}');
    }

    /**
     * An editing teacher can still create a lesson.
     */
    public function test_create_instance_allowed_for_teacher(): void {
        $this->setUser($this->teacher);
        $result = \mod_minilesson_external::create_instance($this->course->id, '{}');
        $this->assertNotEmpty(get_coursemodule_from_id('minilesson', $result['cmid']));
    }

    /**
     * A student cannot delete, move or duplicate items.
     *
     * @dataProvider item_function_provider
     * @param string $function the external function
     */
    public function test_item_functions_denied_for_student(string $function): void {
        $itemid = $this->create_item($this->cm);
        $this->setUser($this->student);
        $this->expectException(\required_capability_exception::class);
        $this->call_item_function($function, $this->contextid($this->cm), $itemid);
    }

    /**
     * Data for test_item_functions_denied_for_student.
     *
     * @return array
     */
    public static function item_function_provider(): array {
        return [
            'delete' => ['delete_item'],
            'move' => ['move_item'],
            'duplicate' => ['duplicate_item'],
            'submit form' => ['submit_mform'],
        ];
    }

    /**
     * Call one of the item web service functions.
     *
     * @param string $function the function name
     * @param int $contextid the context id
     * @param int $itemid the item id
     */
    protected function call_item_function(string $function, int $contextid, int $itemid): void {
        switch ($function) {
            case 'delete_item':
                \mod_minilesson_external::delete_item($contextid, $itemid, constants::TYPE_PAGE);
                break;
            case 'move_item':
                \mod_minilesson_external::move_item($contextid, $itemid, 'up');
                break;
            case 'duplicate_item':
                \mod_minilesson_external::duplicate_item($contextid, $itemid);
                break;
            case 'submit_mform':
                \mod_minilesson_external::submit_mform($contextid, json_encode('itemid=' . $itemid), constants::TYPE_PAGE);
                break;
        }
    }

    /**
     * A teacher cannot delete another lesson's item through their own lesson.
     */
    public function test_delete_item_from_other_lesson_refused(): void {
        global $DB;
        $otheritemid = $this->create_item($this->othercm);
        $this->setUser($this->teacher);
        try {
            \mod_minilesson_external::delete_item($this->contextid($this->cm), $otheritemid, constants::TYPE_PAGE);
            $this->fail('An item of another lesson was deleted');
        } catch (\moodle_exception $e) {
            $this->assertTrue($DB->record_exists(constants::M_QTABLE, ['id' => $otheritemid]));
        }
    }

    /**
     * A teacher can still delete their own lesson's item.
     */
    public function test_delete_item_allowed_for_teacher(): void {
        global $DB;
        $itemid = $this->create_item($this->cm);
        $this->setUser($this->teacher);
        \mod_minilesson_external::delete_item($this->contextid($this->cm), $itemid, constants::TYPE_PAGE);
        $this->assertFalse($DB->record_exists(constants::M_QTABLE, ['id' => $itemid]));
    }

    /**
     * item::delete_item() itself refuses an item of another lesson.
     */
    public function test_item_delete_item_checks_lesson(): void {
        global $DB;
        $otheritemid = $this->create_item($this->othercm);
        $this->expectException(\moodle_exception::class);
        try {
            item::delete_item($otheritemid, \context_module::instance($this->cm->id));
        } finally {
            $this->assertTrue($DB->record_exists(constants::M_QTABLE, ['id' => $otheritemid]));
        }
    }

    /**
     * A student cannot load the item editing fragments.
     *
     * @dataProvider fragment_provider
     * @param string $function the fragment callback
     * @param array $args extra fragment arguments
     */
    public function test_editing_fragments_denied_for_student(string $function, array $args): void {
        $this->setUser($this->student);
        $args['context'] = \context_module::instance($this->cm->id);
        $this->expectException(\required_capability_exception::class);
        $function($args);
    }

    /**
     * Data for test_editing_fragments_denied_for_student.
     *
     * @return array
     */
    public static function fragment_provider(): array {
        return [
            'item form' => ['minilesson_output_fragment_mform', ['formname' => constants::TYPE_PAGE, 'itemid' => 0]],
            'item preview' => ['minilesson_output_fragment_preview_item', ['itemtype' => 'page', 'formdata' => '']],
            'ai prompt' => ['mod_minilesson_output_fragment_ai_prompt',
                ['promptid' => 1, 'prompttype' => 'grade', 'itemtype' => 'freewriting']],
            'tts element' => ['minilesson_output_fragment_ttsaudioelement',
                ['formdata' => '', 'options' => '{}', 'groupname' => 'x']],
            'lesson preview' => ['minilesson_output_fragment_preview', ['itemid' => 0]],
        ];
    }
}
