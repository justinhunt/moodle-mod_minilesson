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

use mod_minilesson\constants;
use mod_minilesson\import;
use mod_minilesson\local\itemtype\item;
use mod_minilesson\utils;

/**
 * Tests for reading and updating existing items through the aigen web service.
 *
 * @package    mod_minilesson
 * @category   test
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\external\aigen_get_items
 * @covers     \mod_minilesson\external\aigen_update_items
 * @covers     \mod_minilesson\local\aigen\itemeditor
 */
final class aigen_edit_test extends \advanced_testcase {
    /** @var string A one pixel PNG, as base64. */
    const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var \stdClass the course */
    protected $course;

    /** @var \stdClass the lesson's course module */
    protected $cm;

    /** @var \stdClass the lesson's record */
    protected $moduleinstance;

    /**
     * Make a course with one empty lesson, as an editing teacher.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->create_lesson('Lesson one');
        $this->moduleinstance = $DB->get_record('minilesson', ['id' => $this->cm->instance], '*', MUST_EXIST);
    }

    /**
     * Create an empty lesson in the test course.
     *
     * @param string $name the lesson's name
     * @return \stdClass the course module
     */
    protected function create_lesson(string $name): \stdClass {
        $moduledata = (object) [
            'name' => $name,
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
        $cmid = utils::create_instance($moduledata, $this->course, 0);
        return get_coursemodule_from_id('minilesson', $cmid, 0, false, MUST_EXIST);
    }

    /**
     * Import items into the lesson.
     *
     * @param array $items the items, in the import format
     * @param array $files the payload's files, by filesid
     * @return \stdClass[] the item records in lesson order, keyed 0, 1, 2 ...
     */
    protected function import_items(array $items, array $files = []): array {
        global $DB;
        $payload = json_decode(json_encode(['items' => $items, 'files' => (object) $files]));
        $import = new import(
            $this->moduleinstance,
            \context_module::instance($this->cm->id),
            $this->course,
            $this->cm
        );
        $import->set_reader($payload, true);
        $results = $import->import_process();
        $this->assertSame([], $results->errors, 'The test items should import cleanly');
        return array_values($DB->get_records(
            constants::M_QTABLE,
            ['minilesson' => $this->moduleinstance->id],
            'itemorder ASC, id ASC'
        ));
    }

    /**
     * A multiple choice item, in the import format.
     *
     * @return array
     */
    protected function multichoice(): array {
        return [
            'type' => 'multichoice',
            'name' => 'Capital cities',
            'instructions' => 'Choose the right answer.',
            'text' => 'What is the capital of France?',
            'answers' => ['London', 'Paris', 'Berlin', 'Madrid'],
            'correctanswer' => 2,
            'correctfeedback' => 'Paris is the capital of France.',
            'promptvoice' => 'Joey',
            'hideanswertext' => 0,
        ];
    }

    /**
     * Call aigen_update_items and return its cleaned result.
     *
     * @param array $updates the updates
     * @param bool $dryrun
     * @param int|null $cmid the lesson, by default the test lesson
     * @return array
     */
    protected function update(array $updates, bool $dryrun = false, ?int $cmid = null): array {
        $result = aigen_update_items::execute($cmid ?? $this->cm->id, json_encode($updates), $dryrun);
        return \core_external\external_api::clean_returnvalue(aigen_update_items::execute_returns(), $result);
    }

    /**
     * Call aigen_get_items and return the decoded items payload.
     *
     * @param int[] $itemids
     * @param string[] $fields
     * @param bool $excludefiles
     * @return \stdClass with items and files
     */
    protected function get_items(array $itemids = [], array $fields = [], bool $excludefiles = true): \stdClass {
        $result = aigen_get_items::execute($this->cm->id, $itemids, $fields, $excludefiles);
        $result = \core_external\external_api::clean_returnvalue(aigen_get_items::execute_returns(), $result);
        $this->assertTrue($result['success']);
        $payload = json_decode($result['itemsjson']);
        $payload->notfound = $result['notfound'];
        return $payload;
    }

    /**
     * The columns of an item that differ between two reads of it.
     *
     * @param \stdClass $before
     * @param \stdClass $after
     * @return string[] column names
     */
    protected function changed_columns(\stdClass $before, \stdClass $after): array {
        $changed = [];
        foreach ($before as $column => $value) {
            // Strict, so that a null turned into an empty string counts as a change.
            if ($value !== $after->{$column}) {
                $changed[] = $column;
            }
        }
        sort($changed);
        return $changed;
    }

    /**
     * Read an item's record.
     *
     * @param int $itemid
     * @return \stdClass
     */
    protected function item_record(int $itemid): \stdClass {
        global $DB;
        return $DB->get_record(constants::M_QTABLE, ['id' => $itemid], '*', MUST_EXIST);
    }

    /**
     * A read gives each item its id, version and position, and can be limited to some items and fields.
     */
    public function test_get_items(): void {
        $second = $this->multichoice();
        $second['name'] = 'Second item';
        [$one, $two] = $this->import_items([$this->multichoice(), $second]);

        $payload = $this->get_items();
        $this->assertCount(2, $payload->items);
        $this->assertSame((int) $one->id, $payload->items[0]->itemid);
        $this->assertSame((int) $one->timemodified, $payload->items[0]->version);
        $this->assertSame(1, $payload->items[0]->position);
        $this->assertSame(2, $payload->items[1]->position);
        $this->assertSame(['London', 'Paris', 'Berlin', 'Madrid'], $payload->items[0]->answers);
        $this->assertSame('Joey', $payload->items[0]->promptvoice);

        $payload = $this->get_items([$two->id, 999999], ['correctfeedback']);
        $this->assertCount(1, $payload->items);
        $this->assertSame([999999], $payload->notfound);
        $fields = array_keys((array) $payload->items[0]);
        sort($fields);
        $this->assertSame(['correctfeedback', 'itemid', 'name', 'position', 'type', 'version'], $fields);
        $this->assertSame(2, $payload->items[0]->position);
    }

    /**
     * A read leaves file content out unless asked, but still names the files.
     */
    public function test_get_items_files(): void {
        $page = ['type' => 'page', 'name' => 'A page', 'text' => 'Look at this', 'filesid' => 1];
        [$item] = $this->import_items([$page], [1 => ['itemmedia' => ['dot.png' => self::PNG]]]);

        $payload = $this->get_items();
        $this->assertSame((int) $item->id, $payload->items[0]->filesid);
        $this->assertSame('QQQQ', $payload->files->{$item->id}->itemmedia->{'dot.png'});

        $payload = $this->get_items([], [], false);
        $this->assertSame(self::PNG, $payload->files->{$item->id}->itemmedia->{'dot.png'});
    }

    /**
     * An update changes the fields sent and no others.
     */
    public function test_update_changes_only_the_fields_sent(): void {
        [$before] = $this->import_items([$this->multichoice()]);

        $sink = $this->redirectEvents();
        $result = $this->update([[
            'itemid' => $before->id,
            'version' => (int) $before->timemodified,
            'changes' => ['correctfeedback' => 'Yes: Paris.', 'hideanswertext' => 2],
        ]]);
        $events = array_filter($sink->get_events(), function ($event) {
            return $event instanceof \mod_minilesson\event\item_updated;
        });
        $sink->close();

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame('updated', $result['results'][0]['status']);
        $this->assertSame(
            ['correctfeedback', 'hideanswertext'],
            array_column($result['results'][0]['changes'], 'field')
        );
        $this->assertSame('Paris is the capital of France.', $result['results'][0]['changes'][0]['before']);
        $this->assertSame('Yes: Paris.', $result['results'][0]['changes'][0]['after']);

        $after = $this->item_record($before->id);
        $keycolumns = \minilessonitem_multichoice\itemtype::get_keycolumns();
        $expected = ['timemodified'];
        foreach ($keycolumns as $coldef) {
            if (in_array($coldef['jsonname'], ['correctfeedback', 'hideanswertext'])) {
                $expected[] = $coldef['dbname'];
            }
        }
        sort($expected);
        $this->assertSame($expected, $this->changed_columns($before, $after));
        $this->assertSame((int) $after->timemodified, $result['results'][0]['version']);
        $this->assertGreaterThan((int) $before->timemodified, (int) $after->timemodified);

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame(['correctfeedback', 'hideanswertext'], $event->other['fields']);
        $this->assertEquals($before->id, $event->objectid);
    }

    /**
     * A list field is replaced as a whole, and an unchanged list is not a change.
     */
    public function test_update_list_field(): void {
        [$before] = $this->import_items([$this->multichoice()]);

        $result = $this->update([[
            'itemid' => $before->id,
            'changes' => [
                'answers' => ['London', 'Paris', 'Rome', 'Madrid'],
                'text' => 'What is the capital of France?',
            ],
        ]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $this->assertSame(['answers'], array_column($result['results'][0]['changes'], 'field'));

        $payload = $this->get_items([$before->id]);
        $this->assertSame(['London', 'Paris', 'Rome', 'Madrid'], $payload->items[0]->answers);
    }

    /**
     * Sending back a whole item just as it was read changes nothing, voices included.
     */
    public function test_update_with_the_whole_item_unchanged(): void {
        global $DB;
        [$before] = $this->import_items([$this->multichoice()]);
        // A voice the import names by a display name that is not its code.
        $DB->set_field(constants::M_QTABLE, 'itemttsvoice', 'en-US-Whisper-nova', ['id' => $before->id]);
        $before = $this->item_record($before->id);

        $whole = $this->get_items([$before->id])->items[0];
        $result = $this->update([['itemid' => $before->id, 'changes' => $whole]]);
        $this->assertSame('unchanged', $result['results'][0]['status']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame([], $this->changed_columns($before, $this->item_record($before->id)));

        // A voice of "auto" asks for any voice, so the one the item has stays.
        $result = $this->update([['itemid' => $before->id, 'changes' => ['ttsvoice' => 'auto', 'name' => 'New name']]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $this->assertSame(['name', 'timemodified'], $this->changed_columns($before, $this->item_record($before->id)));
    }

    /**
     * A voice can be set by its display name or its code.
     */
    public function test_update_voice(): void {
        [$before] = $this->import_items([$this->multichoice()]);
        $name = constants::ALL_VOICES[constants::M_LANG_ENUS]['en-US-Whisper-nova'];

        $result = $this->update([['itemid' => $before->id, 'changes' => ['promptvoice' => $name]]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $this->assertSame($name, $this->get_items([$before->id])->items[0]->promptvoice);

        $result = $this->update([['itemid' => $before->id, 'changes' => ['promptvoice' => 'en-US-Whisper-alloy']]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $keycolumns = \minilessonitem_multichoice\itemtype::get_keycolumns();
        foreach ($keycolumns as $coldef) {
            if ($coldef['jsonname'] === 'promptvoice') {
                $this->assertSame('en-US-Whisper-alloy', $this->item_record($before->id)->{$coldef['dbname']});
            }
        }
    }

    /**
     * A dry run reports the changes and saves nothing.
     */
    public function test_dry_run_saves_nothing(): void {
        [$before] = $this->import_items([$this->multichoice()]);

        $result = $this->update([['itemid' => $before->id, 'changes' => ['name' => 'Renamed']]], true);
        $this->assertTrue($result['dryrun']);
        $this->assertSame('wouldupdate', $result['results'][0]['status']);
        $this->assertSame('Capital cities', $result['results'][0]['changes'][0]['before']);
        $this->assertSame('Renamed', $result['results'][0]['changes'][0]['after']);
        $this->assertSame([], $this->changed_columns($before, $this->item_record($before->id)));
    }

    /**
     * An item edited since it was read is not changed.
     */
    public function test_version_conflict(): void {
        [$before] = $this->import_items([$this->multichoice()]);

        $first = $this->update([[
            'itemid' => $before->id,
            'version' => (int) $before->timemodified,
            'changes' => ['name' => 'First edit'],
        ]]);
        $this->assertSame('updated', $first['results'][0]['status']);

        // The same version again: it is now out of date, even within the same second.
        $second = $this->update([[
            'itemid' => $before->id,
            'version' => (int) $before->timemodified,
            'changes' => ['name' => 'Second edit'],
        ]]);
        $this->assertSame('conflict', $second['results'][0]['status']);
        $this->assertSame(1, $second['failed']);
        $this->assertSame('First edit', $this->item_record($before->id)->name);
        $this->assertSame($first['results'][0]['version'], $second['results'][0]['version']);
    }

    /**
     * Changes that would lose content or break the item are refused, and the item is left alone.
     */
    public function test_refused_changes(): void {
        [$before] = $this->import_items([$this->multichoice()]);

        $refused = [
            'an unknown field' => [['nmae' => 'Renamed'], 'Did you mean "name"'],
            'a change of type' => [['type' => 'page'], 'type of an item cannot be changed'],
            'a media file' => [['itemmedia' => ['dot.png' => 'QQQQ']], 'media files'],
            'a failed content check' => [['correctanswer' => 9], ''],
        ];
        foreach ($refused as $what => [$changes, $message]) {
            $result = $this->update([['itemid' => $before->id, 'changes' => $changes]]);
            $this->assertSame('invalid', $result['results'][0]['status'], $what);
            $this->assertNotSame('', $result['results'][0]['message'], $what);
            if ($message !== '') {
                $this->assertStringContainsString($message, $result['results'][0]['message'], $what);
            }
            $this->assertSame([], $this->changed_columns($before, $this->item_record($before->id)), $what);
        }
    }

    /**
     * One bad item in a batch is refused on its own, and the others are saved.
     */
    public function test_one_bad_item_does_not_stop_the_batch(): void {
        $second = $this->multichoice();
        $second['name'] = 'Second item';
        [$one, $two] = $this->import_items([$this->multichoice(), $second]);

        $result = $this->update([
            ['itemid' => $one->id, 'changes' => ['nosuchfield' => 1]],
            ['itemid' => $two->id, 'changes' => ['name' => 'Second, renamed']],
            ['itemid' => 999999, 'changes' => ['name' => 'Nobody']],
            ['itemid' => $two->id],
        ]);
        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(3, $result['failed']);
        $this->assertSame(['invalid', 'updated', 'notfound', 'invalid'], array_column($result['results'], 'status'));
        $this->assertSame('Second, renamed', $this->item_record($two->id)->name);
        $this->assertSame('Capital cities', $this->item_record($one->id)->name);
    }

    /**
     * An item of another lesson cannot be changed by naming this lesson.
     */
    public function test_item_of_another_lesson(): void {
        [$item] = $this->import_items([$this->multichoice()]);
        $othercm = $this->create_lesson('Lesson two');

        $result = $this->update([['itemid' => $item->id, 'changes' => ['name' => 'Renamed']]], false, $othercm->id);
        $this->assertSame('notfound', $result['results'][0]['status']);
        $this->assertSame('Capital cities', $this->item_record($item->id)->name);
    }

    /**
     * An update leaves the item's media in place.
     */
    public function test_update_keeps_media(): void {
        $page = ['type' => 'page', 'name' => 'A page', 'text' => 'Look at this', 'filesid' => 1];
        [$item] = $this->import_items([$page], [1 => ['itemmedia' => ['dot.png' => self::PNG]]]);

        $result = $this->update([['itemid' => $item->id, 'changes' => ['text' => 'Look at that']]]);
        $this->assertSame('updated', $result['results'][0]['status']);

        $payload = $this->get_items([], [], false);
        $this->assertSame('Look at that', $payload->items[0]->text);
        $this->assertSame(self::PNG, $payload->files->{$item->id}->itemmedia->{'dot.png'});
    }

    /**
     * One option of a TTS dialog can be changed, and the dialog is left alone when something else is.
     */
    public function test_update_dialog_options(): void {
        $page = [
            'type' => 'page',
            'name' => 'A dialog',
            'ttsdialog' => ['A) Hello there.', 'B) Hi, how are you?'],
            'ttsdialogvoicea' => 'Joey',
            'ttsdialogvoiceb' => 'Salli',
            'ttsdialoglabela' => 'Man',
            'ttsdialoglabelb' => 'Woman',
            'ttsdialogvisible' => 'yes',
        ];
        [$before] = $this->import_items([$page]);
        $optsbefore = json_decode($before->{constants::TTSDIALOGOPTS});

        // Something else changes: the dialog and its options stay exactly as stored.
        $this->update([['itemid' => $before->id, 'changes' => ['name' => 'Still a dialog']]]);
        $after = $this->item_record($before->id);
        $this->assertSame($before->{constants::TTSDIALOGOPTS}, $after->{constants::TTSDIALOGOPTS});
        $this->assertSame($before->{constants::TTSDIALOG}, $after->{constants::TTSDIALOG});

        // One label changes: the other options keep their values.
        $result = $this->update([['itemid' => $before->id, 'changes' => ['ttsdialoglabela' => 'Customer']]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $this->assertSame(['ttsdialoglabela'], array_column($result['results'][0]['changes'], 'field'));
        $after = $this->item_record($before->id);
        $optsafter = json_decode($after->{constants::TTSDIALOGOPTS});
        $this->assertSame('Customer', $optsafter->{constants::TTSDIALOGLABELA});
        $this->assertSame('Woman', $optsafter->{constants::TTSDIALOGLABELB});
        $this->assertSame($optsbefore->{constants::TTSDIALOGVOICEA}, $optsafter->{constants::TTSDIALOGVOICEA});
        $this->assertSame($optsbefore->{constants::TTSDIALOGVOICEB}, $optsafter->{constants::TTSDIALOGVOICEB});
        $this->assertEquals($optsbefore->{constants::TTSDIALOGVISIBLE}, $optsafter->{constants::TTSDIALOGVISIBLE});
        $this->assertSame($before->{constants::TTSDIALOG}, $after->{constants::TTSDIALOG});

        // The dialog's lines change: the options stay.
        $result = $this->update([[
            'itemid' => $before->id,
            'changes' => ['ttsdialog' => ['A) Good morning.', 'B) Hi, how are you?']],
        ]]);
        $this->assertSame('updated', $result['results'][0]['status']);
        $after = $this->item_record($before->id);
        $this->assertStringContainsString('Good morning', $after->{constants::TTSDIALOG});
        $optsafter = json_decode($after->{constants::TTSDIALOGOPTS});
        $this->assertSame('Customer', $optsafter->{constants::TTSDIALOGLABELA});
        $this->assertSame($optsbefore->{constants::TTSDIALOGVOICEB}, $optsafter->{constants::TTSDIALOGVOICEB});
    }

    /**
     * A one file HTML widget for a Smart Frame item.
     *
     * @param string $body what the widget shows
     * @return string
     */
    protected function widget_html(string $body): string {
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
        return "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
            . "<script type=\"application/json\" id=\"poodll-widget-manifest\">{$manifest}</script>\n"
            . "</head>\n<body><p>{$body}</p></body>\n</html>\n";
    }

    /**
     * A Smart Frame's widget is given as HTML text. Changing something else leaves it alone, and new HTML
     * takes the place of the old widget.
     */
    public function test_update_smartframe_widget(): void {
        $fs = get_file_storage();
        $contextid = \context_module::instance($this->cm->id)->id;
        $itemclass = \minilessonitem_smartframe\itemtype::class;
        [$before] = $this->import_items([[
            'type' => 'smartframe',
            'name' => 'A widget',
            'gradepolicy' => 0,
            'uilanguage' => 'auto',
            'widgethtml' => $this->widget_html('First version'),
        ]]);
        $revision = $before->{$itemclass::REVISION};
        $this->assertNotEmpty($revision);
        $this->assertStringContainsString('First version', $this->get_items()->items[0]->widgethtml);

        $result = $this->update([['itemid' => $before->id, 'changes' => ['name' => 'Renamed widget']]]);
        $this->assertSame('updated', $result['results'][0]['status'], $result['results'][0]['message']);
        $after = $this->item_record($before->id);
        $this->assertSame(['name', 'timemodified'], $this->changed_columns($before, $after));

        $result = $this->update([[
            'itemid' => $before->id,
            'changes' => ['widgethtml' => $this->widget_html('Second version')],
        ]]);
        $this->assertSame('updated', $result['results'][0]['status'], $result['results'][0]['message']);
        $this->assertSame(['widgethtml'], array_column($result['results'][0]['changes'], 'field'));
        $after = $this->item_record($before->id);
        $this->assertNotSame($revision, $after->{$itemclass::REVISION});
        $this->assertNotEmpty($after->{$itemclass::REVISION});
        $files = $fs->get_area_files($contextid, constants::M_COMPONENT, $itemclass::FILE, $before->id, 'id', false);
        $this->assertCount(1, $files);
        $this->assertStringContainsString('Second version', reset($files)->get_content());
        $this->assertStringContainsString('Second version', $this->get_items()->items[0]->widgethtml);

        // A widget that would not work is refused, and the one in place stays.
        $result = $this->update([['itemid' => $before->id, 'changes' => ['widgethtml' => '<p>No manifest</p>']]]);
        $this->assertSame('invalid', $result['results'][0]['status']);
        $this->assertStringContainsString('Second version', $this->get_items()->items[0]->widgethtml);
    }

    /**
     * Updates need the manage capability, and reads the export capability.
     */
    public function test_capabilities(): void {
        [$item] = $this->import_items([$this->multichoice()]);

        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($student);
        try {
            $this->update([['itemid' => $item->id, 'changes' => ['name' => 'Renamed']]]);
            $this->fail('A student must not be able to update items');
        } catch (\required_capability_exception $e) {
            $this->assertSame('Capital cities', $this->item_record($item->id)->name);
        }
        try {
            aigen_get_items::execute($this->cm->id);
            $this->fail('A student must not be able to read items for editing');
        } catch (\required_capability_exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // A teacher of another course has no access to this lesson at all.
        $othercourse = $this->getDataGenerator()->create_course();
        $otherteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $this->setUser($otherteacher);
        try {
            $this->update([['itemid' => $item->id, 'changes' => ['name' => 'Renamed']]]);
            $this->fail('A teacher of another course must not be able to update items');
        } catch (\moodle_exception $e) {
            $this->assertSame('Capital cities', $this->item_record($item->id)->name);
        }

        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);
        $result = $this->update([['itemid' => $item->id, 'changes' => ['name' => 'Renamed']]]);
        $this->assertSame('updated', $result['results'][0]['status']);
    }

    /**
     * The updates must be a JSON array of updates.
     */
    public function test_bad_updates_json(): void {
        $result = aigen_update_items::execute($this->cm->id, 'not json', false);
        $this->assertFalse($result['success']);
        $this->assertNotSame('', $result['errormsg']);
    }

    /**
     * The item types that document an import example.
     *
     * @return array
     */
    public static function itemtype_provider(): array {
        $cases = [];
        foreach (glob(__DIR__ . '/../../item/*', GLOB_ONLYDIR) as $dir) {
            $cases[basename($dir)] = [basename($dir)];
        }
        return $cases;
    }

    /**
     * For every item type: sending its example item back unchanged changes nothing, and renaming it changes
     * only its name.
     *
     * @dataProvider itemtype_provider
     * @param string $type the item type
     */
    public function test_every_item_type(string $type): void {
        $itemtypeclass = item::get_itemtype_class($type);
        $spec = $itemtypeclass ? $itemtypeclass::aigen_fetch_import_spec() : null;
        if (!$spec || empty($spec['example']['items'])) {
            $this->markTestSkipped($type . ' has no import example');
        }
        $example = json_decode(json_encode($spec['example']));
        $payload = json_decode(json_encode(['items' => [$example->items[0]], 'files' => $example->files ?? new \stdClass()]));
        $import = new import($this->moduleinstance, \context_module::instance($this->cm->id), $this->course, $this->cm);
        $import->set_reader($payload, true);
        $results = $import->import_process();
        if ($results->imported !== 1) {
            $this->markTestSkipped($type . ' example does not import here: ' . ($results->errors[0]->message ?? ''));
        }
        $before = $this->item_record($this->get_items()->items[0]->itemid);

        $whole = $this->get_items([$before->id])->items[0];
        $result = $this->update([['itemid' => $before->id, 'changes' => $whole]]);
        $this->assertSame('unchanged', $result['results'][0]['status'], $result['results'][0]['message']);
        $this->assertSame([], $this->changed_columns($before, $this->item_record($before->id)));

        $result = $this->update([['itemid' => $before->id, 'changes' => ['name' => 'Renamed item']]]);
        $this->assertSame('updated', $result['results'][0]['status'], $result['results'][0]['message']);
        $this->assertSame(['name', 'timemodified'], $this->changed_columns($before, $this->item_record($before->id)));
    }
}
