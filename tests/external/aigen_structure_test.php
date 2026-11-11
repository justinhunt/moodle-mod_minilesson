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

namespace mod_minilesson\external;

use core_external\external_api;
use mod_minilesson\constants;
use mod_minilesson\local\chatagent\tools;
use mod_minilesson\utils;

/**
 * Tests for the aigen functions that change a lesson's structure, media and settings, and find items.
 *
 * @package    mod_minilesson
 * @category   test
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\local\aigen\itemeditor
 * @covers     \mod_minilesson\local\aigen\lessonsettings
 */
final class aigen_structure_test extends \advanced_testcase {
    /** @var string A one pixel PNG, as base64. */
    const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var \stdClass the course */
    protected $course;

    /** @var \stdClass the lesson's course module */
    protected $cm;

    /**
     * Make a course with one empty lesson, as admin.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->create_lesson('Lesson one');
    }

    /**
     * Create an empty lesson in the test course.
     *
     * @param string $name
     * @return \stdClass the course module
     */
    protected function create_lesson(string $name, string $ttslanguage = constants::M_LANG_ENUS): \stdClass {
        $moduledata = (object) [
            'name' => $name,
            'modulename' => 'minilesson',
            'pagelayout' => 'standard',
            'showqtitles' => 1,
            'maxattempts' => 0,
            'ttslanguage' => $ttslanguage,
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
        $cmid = utils::create_instance($moduledata, $this->course, 0);
        return get_coursemodule_from_id('minilesson', $cmid, 0, false, MUST_EXIST);
    }

    /**
     * A page item in the import format.
     *
     * @param string $name
     * @param bool $withimage give it a picture in itemmedia (filesid 1)
     * @return array
     */
    protected function page(string $name, bool $withimage = false): array {
        $item = ['type' => 'page', 'name' => $name, 'text' => 'Text of ' . $name];
        if ($withimage) {
            $item['filesid'] = 1;
        }
        return $item;
    }

    /**
     * Add items with aigen_insert_items and return the result.
     *
     * @param array $items
     * @param int $after
     * @param array $files by filesid
     * @param bool $dryrun
     * @param int|null $cmid
     * @return array
     */
    protected function insert(array $items, int $after = -1, array $files = [], bool $dryrun = false, ?int $cmid = null): array {
        $json = json_encode(['items' => $items, 'files' => (object) $files]);
        $result = aigen_insert_items::execute($cmid ?? $this->cm->id, $json, $after, $dryrun);
        return external_api::clean_returnvalue(aigen_insert_items::execute_returns(), $result);
    }

    /**
     * The item names of a lesson in order.
     *
     * @param int|null $cmid
     * @return string[]
     */
    protected function names(?int $cmid = null): array {
        $result = aigen_get_items::execute($cmid ?? $this->cm->id, [], ['name']);
        return array_column(json_decode($result['itemsjson'], true)['items'], 'name');
    }

    /**
     * New items go where after_itemid says, in the order sent. A dry run adds nothing.
     */
    public function test_insert_items(): void {
        $first = $this->insert([$this->page('A'), $this->page('D')]);
        $this->assertSame(2, $first['itemcount']);
        [$a, $d] = $first['itemids'];

        $result = $this->insert([$this->page('B'), $this->page('C')], $a);
        $this->assertSame(2, $result['itemcount']);
        $this->assertSame(['A', 'B', 'C', 'D'], $this->names());

        $this->insert([$this->page('Start')], 0);
        $this->insert([$this->page('End')]);
        $this->assertSame(['Start', 'A', 'B', 'C', 'D', 'End'], $this->names());

        $dry = $this->insert([$this->page('X'), ['type' => 'page', 'name' => 'Bad', 'nosuchfield' => 1]], $d, [], true);
        $this->assertTrue($dry['dryrun']);
        $this->assertSame(1, $dry['itemcount']);
        $this->assertSame(1, $dry['failed']);
        $this->assertSame(2, $dry['errors'][0]['itemnum']);
        $this->assertCount(6, $this->names());

        $bad = $this->insert([$this->page('Y')], 999999);
        $this->assertFalse($bad['success']);
        $this->assertCount(6, $this->names());
    }

    /**
     * Reordering takes exactly the lesson's items.
     */
    public function test_reorder_items(): void {
        [$a, $b, $c] = $this->insert([$this->page('A'), $this->page('B'), $this->page('C')])['itemids'];

        $result = aigen_reorder_items::execute($this->cm->id, [$c, $a, $b], true);
        $this->assertTrue($result['changed']);
        $this->assertSame(['A', 'B', 'C'], $this->names());

        $result = aigen_reorder_items::execute($this->cm->id, [$c, $a, $b]);
        $this->assertTrue($result['success']);
        $this->assertSame([$c, $a, $b], $result['itemids']);
        $this->assertSame(['C', 'A', 'B'], $this->names());

        foreach ([[$c, $a], [$c, $a, $b, $b], [$c, $a, 999999]] as $bad) {
            $result = aigen_reorder_items::execute($this->cm->id, $bad);
            $this->assertFalse($result['success']);
            $this->assertSame(['C', 'A', 'B'], $this->names());
        }
    }

    /**
     * Deleting removes the item and its files. A dry run only lists.
     */
    public function test_delete_items(): void {
        $inserted = $this->insert([$this->page('A', true), $this->page('B')], -1, [1 => ['itemmedia' => ['dot.png' => self::PNG]]]);
        [$a, $b] = $inserted['itemids'];
        $contextid = \context_module::instance($this->cm->id)->id;
        $fs = get_file_storage();
        $this->assertFalse($fs->is_area_empty($contextid, constants::M_COMPONENT, 'itemmedia', $a));

        $dry = aigen_delete_items::execute($this->cm->id, [$a, 999999], true);
        $this->assertSame([999999], $dry['notfound']);
        $this->assertSame('A', $dry['deleted'][0]['name']);
        $this->assertSame(['A', 'B'], $this->names());

        $result = aigen_delete_items::execute($this->cm->id, [$a]);
        $this->assertSame(1, $result['deleted'][0]['position']);
        $this->assertSame(['B'], $this->names());
        $this->assertTrue($fs->is_area_empty($contextid, constants::M_COMPONENT, 'itemmedia', $a));
    }

    /**
     * Items are copied between lessons with their files, and placed.
     */
    public function test_copy_items(): void {
        [$a, $b] = $this->insert(
            [$this->page('A', true), $this->page('B')],
            -1,
            [1 => ['itemmedia' => ['dot.png' => self::PNG]]]
        )['itemids'];
        $target = $this->create_lesson('Lesson two');
        [$x] = $this->insert([$this->page('X'), $this->page('Y')], -1, [], false, $target->id)['itemids'];

        $result = aigen_copy_items::execute($target->id, $this->cm->id, [$b, $a, 999999], $x);
        $result = external_api::clean_returnvalue(aigen_copy_items::execute_returns(), $result);
        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['itemids']);
        $this->assertSame([999999], $result['notfound']);
        $this->assertSame(['X', 'A', 'B', 'Y'], $this->names($target->id));
        $this->assertSame(['A', 'B'], $this->names());

        $copyofa = $result['itemids'][0];
        $files = json_decode(aigen_get_items::execute($target->id, [$copyofa], [], false)['itemsjson']);
        $this->assertSame(self::PNG, $files->files->{$copyofa}->itemmedia->{'dot.png'});
    }

    /**
     * A voice for another language is swapped for the lesson's auto voice, on insert and on copy, including the
     * voices of a TTS dialog and passage. A voice for the same language in another region is kept.
     */
    public function test_voices_follow_language(): void {
        global $DB;
        $this->assertTrue(utils::voice_fits_language('Amy', constants::M_LANG_ENUS));
        $this->assertFalse(utils::voice_fits_language('Takumi', constants::M_LANG_ENUS));
        $this->assertTrue(utils::voice_fits_language('no-such-voice', constants::M_LANG_ENUS));

        $item = $this->page('Voices') + [
            'ttsvoice' => 'Takumi',
            'ttsdialog' => ['A: Hello.', 'B: Hi there.'],
            'ttsdialogvoicea' => 'Amy',
            'ttsdialogvoiceb' => 'Mizuki',
            'ttspassage' => 'A short passage.',
            'ttspassagevoice' => 'Takumi',
        ];
        [$itemid] = $this->insert([$item])['itemids'];
        $record = $DB->get_record(constants::M_QTABLE, ['id' => $itemid]);
        $dialog = json_decode($record->{constants::TTSDIALOGOPTS});
        $passage = json_decode($record->{constants::TTSPASSAGEOPTS});
        $this->assertTrue(utils::voice_fits_language($record->itemttsvoice, constants::M_LANG_ENUS));
        $this->assertNotSame('Takumi', $record->itemttsvoice);
        $this->assertSame('Amy', $dialog->{constants::TTSDIALOGVOICEA});
        $this->assertTrue(utils::voice_fits_language($dialog->{constants::TTSDIALOGVOICEB}, constants::M_LANG_ENUS));
        $this->assertNotSame('Mizuki', $dialog->{constants::TTSDIALOGVOICEB});
        $this->assertTrue(utils::voice_fits_language($passage->{constants::TTSPASSAGEVOICE}, constants::M_LANG_ENUS));

        // Copied into a Japanese lesson, every English voice is swapped and the rest of the options stay.
        $japanese = $this->create_lesson('Japanese lesson', constants::M_LANG_JAJP);
        $result = aigen_copy_items::execute($japanese->id, $this->cm->id, [$itemid], -1);
        $copy = $DB->get_record(constants::M_QTABLE, ['id' => $result['itemids'][0]]);
        $this->assertArrayHasKey($copy->itemttsvoice, constants::ALL_VOICES[constants::M_LANG_JAJP]);
        $copydialog = json_decode($copy->{constants::TTSDIALOGOPTS});
        foreach ([constants::TTSDIALOGVOICEA, constants::TTSDIALOGVOICEB] as $slot) {
            $this->assertArrayHasKey($copydialog->{$slot}, constants::ALL_VOICES[constants::M_LANG_JAJP]);
        }
        $this->assertSame($dialog->{constants::TTSDIALOGVISIBLE}, $copydialog->{constants::TTSDIALOGVISIBLE});
        $copypassage = json_decode($copy->{constants::TTSPASSAGEOPTS});
        $this->assertArrayHasKey($copypassage->{constants::TTSPASSAGEVOICE}, constants::ALL_VOICES[constants::M_LANG_JAJP]);

        // Copied into another English lesson, nothing changes.
        $english = $this->create_lesson('English lesson');
        $result = aigen_copy_items::execute($english->id, $this->cm->id, [$itemid], -1);
        $same = $DB->get_record(constants::M_QTABLE, ['id' => $result['itemids'][0]]);
        $this->assertSame($record->itemttsvoice, $same->itemttsvoice);
        $this->assertSame($record->{constants::TTSDIALOGOPTS}, $same->{constants::TTSDIALOGOPTS});
    }

    /**
     * Copying needs export on the source and manage on the target.
     */
    public function test_copy_capabilities(): void {
        [$a] = $this->insert([$this->page('A')])['itemids'];
        $othercourse = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $this->setAdminUser();
        $this->course = $othercourse;
        $target = $this->create_lesson('Other course lesson');

        $this->setUser($teacher);
        try {
            aigen_copy_items::execute($target->id, $this->cm->id, [$a]);
            $this->fail('A teacher must not copy from a course they do not teach');
        } catch (\moodle_exception $e) {
            $this->assertEquals([], $this->names_as_admin($target->id));
        }
    }

    /**
     * The item names of a lesson, read as admin.
     *
     * @param int $cmid
     * @return string[]
     */
    protected function names_as_admin(int $cmid): array {
        global $USER;
        $user = $USER;
        $this->setAdminUser();
        $names = $this->names($cmid);
        $this->setUser($user);
        return $names;
    }

    /**
     * Finding items by type, exact field values and text, across the course.
     */
    public function test_find_items(): void {
        $this->insert([
            ['type' => 'multichoice', 'name' => 'Q1', 'text' => 'Pick one', 'answers' => ['(A) cat', '(B) dog'],
                'correctanswer' => 1, 'listenorread' => 1],
            ['type' => 'multichoice', 'name' => 'Q2', 'text' => 'Pick one', 'answers' => ['cat', 'dog'],
                'correctanswer' => 2],
            $this->page('A page about (A) things'),
        ]);
        $other = $this->create_lesson('Lesson two');
        $this->insert([$this->page('(A) elsewhere')], -1, [], false, $other->id);

        $result = aigen_find_items::execute($this->course->id, [], '', '', '(a)');
        $this->assertSame(2, $result['searched']);
        $this->assertSame(['Q1', 'A page about (A) things', '(A) elsewhere'], array_column($result['matches'], 'name'));
        $matched = json_decode($result['matches'][0]['matchedjson'], true);
        $this->assertArrayHasKey('answers', $matched);

        $result = aigen_find_items::execute(0, [$this->cm->id], 'multichoice', '{"correctanswer": "2"}');
        $this->assertSame(['Q2'], array_column($result['matches'], 'name'));

        $result = aigen_find_items::execute($this->course->id, [], '', '', '(a)', 1);
        $this->assertCount(1, $result['matches']);
        $this->assertTrue($result['truncated']);

        $result = aigen_find_items::execute(0, [], '', '', 'x');
        $this->assertFalse($result['success']);
    }

    /**
     * Uploading files replaces or adds to a file area, and only an area the item type takes files in.
     */
    public function test_put_item_files(): void {
        [$a] = $this->insert([$this->page('A', true)], -1, [1 => ['itemmedia' => ['old.png' => self::PNG]]])['itemids'];

        $result = aigen_put_item_files::execute($this->cm->id, $a, 'itemmedia', json_encode(['new.png' => self::PNG]));
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(['new.png'], array_column($result['files'], 'filename'));

        $result = aigen_put_item_files::execute($this->cm->id, $a, 'itemmedia', json_encode(['more.png' => self::PNG]), false);
        $this->assertSame(['more.png', 'new.png'], array_column($result['files'], 'filename'));

        $result = aigen_put_item_files::execute($this->cm->id, $a, 'intro', json_encode(['x.png' => self::PNG]));
        $this->assertFalse($result['success']);
        $this->assertSame('invalid', $result['status']);

        $result = aigen_put_item_files::execute($this->cm->id, $a, 'itemmedia', json_encode(['x.png' => '***']));
        $this->assertFalse($result['success']);
        $result = aigen_put_item_files::execute($this->cm->id, $a, 'itemmedia', json_encode(['../x.png' => self::PNG]));
        $this->assertFalse($result['success']);
    }

    /**
     * A picture comes back scaled down to the width asked for.
     */
    public function test_get_item_media(): void {
        $image = imagecreatetruecolor(1600, 800);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image);
        $jpeg = base64_encode(ob_get_clean());
        [$a] = $this->insert([$this->page('A', true)], -1, [1 => ['itemmedia' => ['big.jpg' => $jpeg]]])['itemids'];

        $result = aigen_get_item_media::execute($this->cm->id, $a, '', 400);
        $result = external_api::clean_returnvalue(aigen_get_item_media::execute_returns(), $result);
        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['media']);
        $entry = $result['media'][0];
        $this->assertSame('itemmedia', $entry['filearea']);
        $this->assertSame(400, $entry['width']);
        $this->assertSame(200, $entry['height']);
        $this->assertSame('image/jpeg', $entry['contentmimetype']);
        $info = getimagesizefromstring(base64_decode($entry['content']));
        $this->assertSame(400, $info[0]);

        // A photo saved as PNG comes back as JPEG; a see-through PNG stays PNG.
        $photo = imagecreatetruecolor(300, 200);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 10, 120, 10));
        $clear = imagecreatetruecolor(300, 200);
        imagesavealpha($clear, true);
        imagefill($clear, 0, 0, imagecolorallocatealpha($clear, 0, 0, 0, 127));
        $pngs = [];
        foreach (['photo.png' => $photo, 'clear.png' => $clear] as $name => $picture) {
            ob_start();
            imagepng($picture);
            $pngs[$name] = base64_encode(ob_get_clean());
        }
        aigen_put_item_files::execute($this->cm->id, $a, 'itemmedia', json_encode($pngs));
        $media = array_column(aigen_get_item_media::execute($this->cm->id, $a)['media'], 'contentmimetype', 'filename');
        $this->assertSame(['clear.png' => 'image/png', 'photo.png' => 'image/jpeg'], $media);

        $result = aigen_get_item_media::execute($this->cm->id, 999999);
        $this->assertFalse($result['success']);
    }

    /**
     * Lesson settings can be read and changed from the allowed list only.
     */
    public function test_lesson_settings(): void {
        global $DB;
        $settings = array_column(aigen_get_lesson_settings::execute($this->cm->id)['settings'], 'value', 'setting');
        $this->assertSame('1', $settings['showqtitles']);
        $this->assertSame('Lesson one', $settings['name']);

        $changes = json_encode(['showqtitles' => 'no', 'name' => 'Renamed']);
        $dry = aigen_update_lesson_settings::execute($this->cm->id, $changes, true);
        $this->assertSame('wouldupdate', $dry['status']);
        $this->assertSame('Lesson one', $DB->get_field('minilesson', 'name', ['id' => $this->cm->instance]));

        $result = aigen_update_lesson_settings::execute(
            $this->cm->id,
            json_encode([
                'showqtitles' => 'no',
                'name' => 'Renamed',
                'visible' => 0,
                'finishscreen' => constants::FINISHSCREEN_SIMPLE,
            ])
        );
        $this->assertSame('updated', $result['status'], $result['message']);
        $record = $DB->get_record('minilesson', ['id' => $this->cm->instance]);
        $this->assertEquals(0, $record->showqtitles);
        $this->assertSame('Renamed', $record->name);
        $this->assertEquals(constants::FINISHSCREEN_SIMPLE, $record->finishscreen);
        $this->assertEquals(0, get_coursemodule_from_id('minilesson', $this->cm->id)->visible);
        $this->assertSame('Renamed', get_fast_modinfo($this->course->id)->get_cm($this->cm->id)->name);

        foreach ([['ttslanguage' => 'fr-FR'], ['containerwidth' => 'huge'], ['maxattempts' => -1], ['name' => '']] as $bad) {
            $result = aigen_update_lesson_settings::execute($this->cm->id, json_encode($bad));
            $this->assertSame('invalid', $result['status']);
        }
        $this->assertSame('unchanged', aigen_update_lesson_settings::execute($this->cm->id, '{"name": "Renamed"}')['status']);
    }

    /**
     * The outline lists sections and activities, with each lesson's item count.
     */
    public function test_get_course_outline(): void {
        $this->insert([$this->page('A'), $this->page('B')]);
        $result = aigen_get_course_outline::execute($this->course->id);
        $result = external_api::clean_returnvalue(aigen_get_course_outline::execute_returns(), $result);
        $activities = array_merge(...array_column($result['sections'], 'activities'));
        $lesson = array_values(array_filter($activities, function ($a) {
            return $a['cmid'] == $this->cm->id;
        }))[0];
        $this->assertSame(2, $lesson['itemcount']);
        $this->assertSame('minilesson', $lesson['modname']);
    }

    /**
     * Structural changes on a lesson with attempts carry a warning.
     */
    public function test_attempts_warning(): void {
        global $DB;
        [$a, $b] = $this->insert([$this->page('A'), $this->page('B')])['itemids'];
        $this->assertSame('', aigen_reorder_items::execute($this->cm->id, [$b, $a])['warning']);

        $DB->insert_record(constants::M_ATTEMPTSTABLE, (object) [
            'courseid' => $this->course->id,
            'moduleid' => $this->cm->instance,
            'userid' => 2,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertNotSame('', aigen_reorder_items::execute($this->cm->id, [$a, $b])['warning']);
        $this->assertNotSame('', $this->insert([$this->page('C')])['warning']);
    }

    /**
     * A Smart Frame's published widget goes with a copy, and is removed with a delete.
     */
    public function test_smartframe_copy_and_delete(): void {
        $manifest = json_encode([
            'schemaVersion' => 1,
            'id' => 'test-widget',
            'title' => 'Test widget',
            'entry' => 'index.html',
            'defaultUiLanguage' => 'en',
            'uiLanguages' => ['en'],
            'capabilities' => ['resize' => true, 'grade' => false],
            'minimumHostProtocol' => 1,
        ]);
        $html = "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
            . "<script type=\"application/json\" id=\"poodll-widget-manifest\">{$manifest}</script></head>"
            . "<body><p>Hello</p></body></html>\n";
        $item = ['type' => 'smartframe', 'name' => 'Widget', 'gradepolicy' => 0, 'uilanguage' => 'auto', 'widgethtml' => $html];
        [$a] = $this->insert([$item])['itemids'];
        $target = $this->create_lesson('Lesson two');

        $copy = aigen_copy_items::execute($target->id, $this->cm->id, [$a])['itemids'][0];
        $fs = get_file_storage();
        $store = \minilessonitem_smartframe\local\widgetstore::class;
        $targetcontext = \context_module::instance($target->id);
        $this->assertFalse($fs->is_area_empty($targetcontext->id, $store::COMPONENT, $store::FILEAREA, $copy));

        aigen_delete_items::execute($target->id, [$copy]);
        $this->assertTrue($fs->is_area_empty($targetcontext->id, $store::COMPONENT, $store::FILEAREA, $copy));
        $sourcecontext = \context_module::instance($this->cm->id);
        $this->assertFalse($fs->is_area_empty($sourcecontext->id, $store::COMPONENT, $store::FILEAREA, $a));
    }

    /**
     * The AI assistant asks the teacher before each change, but not before a dry run.
     */
    public function test_chatagent_approval(): void {
        $this->assertTrue(tools::needs_approval('mod_minilesson_aigen_delete_items', ['dryrun' => false]));
        $this->assertFalse(tools::needs_approval('mod_minilesson_aigen_delete_items', ['dryrun' => true]));
        $this->assertTrue(tools::needs_approval('mod_minilesson_aigen_put_item_files', ['dryrun' => true]));
        $this->assertFalse(tools::needs_approval('mod_minilesson_aigen_find_items'));
    }
}
