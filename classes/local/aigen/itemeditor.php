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

namespace mod_minilesson\local\aigen;

use mod_minilesson\constants;
use mod_minilesson\import;
use mod_minilesson\local\itemtype\item;
use mod_minilesson\utils;

/**
 * Reads and changes the items of one lesson by their import field names.
 *
 * The aigen functions that read and edit existing items are thin wrappers around this class. Callers
 * name fields as the import spec does (what aigen_fetch_item_type_details documents), never by DB column.
 *
 * An update writes only the fields the caller changed, on top of the stored item. It does not export the
 * item and import it again: an export leaves out some of what is stored (it gives an unlisted voice as
 * "auto", which an import then picks at random), so that would change fields nobody asked to change.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemeditor {
    /** @var string An item was changed. */
    const STATUS_UPDATED = 'updated';
    /** @var string A dry run: the item would be changed. */
    const STATUS_WOULDUPDATE = 'wouldupdate';
    /** @var string The changes match what the item already holds. */
    const STATUS_UNCHANGED = 'unchanged';
    /** @var string The item was changed by someone else since the caller read it. */
    const STATUS_CONFLICT = 'conflict';
    /** @var string The changes were refused. */
    const STATUS_INVALID = 'invalid';
    /** @var string This lesson has no such item. */
    const STATUS_NOTFOUND = 'notfound';
    /** @var string The item could not be saved. */
    const STATUS_ERROR = 'error';

    /** @var string[] What a read adds to each item. They are not item fields, so an update passes over them. */
    const META_FIELDS = ['itemid', 'version', 'position'];

    /** @var string What stands in for the content of a file when a read leaves files out. */
    const FILE_PLACEHOLDER = 'QQQQ';

    /** @var string[] The TTS dialog and the options stored packed alongside it. */
    const DIALOG_FIELDS = [
        constants::TTSDIALOG,
        constants::TTSDIALOGVOICEA,
        constants::TTSDIALOGVOICEB,
        constants::TTSDIALOGVOICEC,
        constants::TTSDIALOGLABELA,
        constants::TTSDIALOGLABELB,
        constants::TTSDIALOGLABELC,
        constants::TTSDIALOGVISIBLE,
    ];

    /** @var string[] The TTS passage and the options stored packed alongside it. */
    const PASSAGE_FIELDS = [
        constants::TTSPASSAGE,
        constants::TTSPASSAGEVOICE,
        constants::TTSPASSAGESPEED,
    ];

    /** @var \stdClass the course module */
    protected $cm;

    /** @var \stdClass the lesson's record */
    protected $moduleinstance;

    /** @var \context_module the lesson's context */
    protected $context;

    /** @var import does the mapping between import field names and DB columns */
    protected $import;

    /** @var \stdClass the course's record */
    protected $course;

    /**
     * Set up for one lesson.
     *
     * @param \stdClass $cm the course module
     * @param \stdClass $moduleinstance the lesson's record
     * @param \stdClass $course the course's record
     * @param \context_module $context the lesson's context
     */
    public function __construct($cm, $moduleinstance, $course, \context_module $context) {
        $this->cm = $cm;
        $this->moduleinstance = $moduleinstance;
        $this->context = $context;
        $this->course = $course;
        $this->import = new import($moduleinstance, $context, $course, $cm);
    }

    /**
     * Set up for the lesson with this course module id.
     *
     * @param int $cmid the course module id
     * @return itemeditor
     */
    public static function for_cmid(int $cmid): itemeditor {
        global $DB;
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        return new itemeditor($cm, $moduleinstance, $course, \context_module::instance($cm->id));
    }

    /**
     * The lesson's context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * How many attempts learners have made at this lesson.
     *
     * An attempt stores its results by the item's place in the lesson, not by the item. Adding, removing or
     * moving items makes the results of earlier attempts line up with the wrong items.
     *
     * @return int
     */
    public function attempt_count(): int {
        global $DB;
        return $DB->count_records(constants::M_ATTEMPTSTABLE, ['moduleid' => $this->moduleinstance->id]);
    }

    /**
     * The warning to give with a change to the order of the items, when the lesson has attempts.
     *
     * @return string the warning, or an empty string
     */
    public function attempts_warning(): string {
        $count = $this->attempt_count();
        return $count ? get_string('edit:attemptswarning', constants::M_COMPONENT, $count) : '';
    }

    /**
     * The lesson's item ids in lesson order.
     *
     * @return int[]
     */
    public function get_order(): array {
        return array_map('intval', array_keys($this->get_item_records()));
    }

    /**
     * Put the lesson's items in a new order.
     *
     * @param int[] $itemids every item of the lesson, each once, in the new order
     * @param bool $logit fire the items_reordered event
     * @throws \moodle_exception if the list is not exactly the lesson's items
     */
    public function set_order(array $itemids, bool $logit = true): void {
        global $DB;
        $itemids = array_map('intval', $itemids);
        $current = $this->get_order();
        $sorted = $current;
        sort($sorted);
        $given = $itemids;
        sort($given);
        if ($sorted !== $given) {
            throw new \moodle_exception('edit:badorder', constants::M_COMPONENT);
        }
        if ($itemids === $current) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        foreach ($itemids as $index => $itemid) {
            $DB->set_field(constants::M_QTABLE, 'itemorder', $index + 1, ['id' => $itemid]);
        }
        $transaction->allow_commit();
        if ($logit) {
            \mod_minilesson\event\items_reordered::create_from_lesson($this->context, $this->moduleinstance->id, $itemids)
                ->trigger();
        }
    }

    /**
     * Move some items of the lesson to sit together after another item, in the order given.
     *
     * @param int[] $itemids the items to move
     * @param int $afteritemid the item they go after: 0 for the start of the lesson, -1 for the end
     * @throws \moodle_exception if the item to go after is not in the lesson, or is one of those moved
     */
    public function place_items(array $itemids, int $afteritemid): void {
        $itemids = array_map('intval', $itemids);
        $others = array_values(array_diff($this->get_order(), $itemids));
        if ($afteritemid === -1) {
            $order = array_merge($others, $itemids);
        } else if ($afteritemid === 0) {
            $order = array_merge($itemids, $others);
        } else {
            $at = array_search($afteritemid, $others, true);
            if ($at === false) {
                throw new \moodle_exception('edit:badafter', constants::M_COMPONENT, '', $afteritemid);
            }
            $order = array_merge(array_slice($others, 0, $at + 1), $itemids, array_slice($others, $at + 1));
        }
        $this->set_order($order, false);
    }

    /**
     * Check that the item to place new items after is in the lesson.
     *
     * @param int $afteritemid 0 for the start, -1 for the end, or an item id
     * @return bool
     */
    public function is_valid_after(int $afteritemid): bool {
        return $afteritemid === 0 || $afteritemid === -1 || in_array($afteritemid, $this->get_order(), true);
    }

    /**
     * Check new items as an import would, without adding them.
     *
     * @param \stdClass $payload {items, files} in the import format
     * @return \stdClass[] failure entries ({itemnum, type, name, message}) for the items that would be refused
     */
    public function check_new_items(\stdClass $payload): array {
        $this->import->set_reader($payload, true);
        return $this->import->find_content_problems(false);
    }

    /**
     * Add new items to the lesson, as an import does, and place them.
     *
     * @param \stdClass $payload {items, files, fileprompts} in the import format
     * @param int $afteritemid the item the new items go after: 0 for the start, -1 for the end
     * @return \stdClass the import results (see import::import_process()), plus itemids (the new items, in the
     *         order sent) and imagefailures
     */
    public function insert_items(\stdClass $payload, int $afteritemid): \stdClass {
        global $DB;
        $before = $this->get_order();
        $imagefailures = $this->import->generate_images_from_prompts($payload);
        $this->import->set_reader($payload, true);
        $results = $this->import->import_process();
        $newids = array_values(array_diff($this->get_order(), $before));
        sort($newids);
        if ($newids) {
            $this->place_items($newids, $afteritemid);
            foreach ($newids as $itemid) {
                \mod_minilesson\event\item_created::create_from_item(
                    $DB->get_record(constants::M_QTABLE, ['id' => $itemid]),
                    $this->context
                )->trigger();
            }
        }
        $results->itemids = $newids;
        $results->imagefailures = $imagefailures;
        return $results;
    }

    /**
     * Copy items of another lesson (or this one) into this lesson, with their files, and place them.
     *
     * @param itemeditor $source the lesson to copy from
     * @param int[] $itemids the items to copy, which must be in the source lesson
     * @param int $afteritemid the item the copies go after: 0 for the start, -1 for the end
     * @return \stdClass with itemids (the copies, in the source lesson's order) and notfound (item ids)
     */
    public function copy_items(itemeditor $source, array $itemids, int $afteritemid): \stdClass {
        global $DB, $USER, $CFG;
        $result = (object) ['itemids' => [], 'notfound' => []];
        $sourcerecords = $source->get_item_records();
        $wanted = [];
        foreach (array_map('intval', $itemids) as $itemid) {
            if (isset($sourcerecords[$itemid])) {
                $wanted[$itemid] = true;
            } else {
                $result->notfound[] = $itemid;
            }
        }
        $fs = get_file_storage();
        $sourcecontext = $source->get_context();
        foreach (array_intersect_key($sourcerecords, $wanted) as $record) {
            $oldid = (int) $record->id;
            $copy = clone $record;
            unset($copy->id, $copy->position);
            $copy->minilesson = $this->moduleinstance->id;
            $copy->itemorder = count($this->get_order()) + 1;
            $copy->rsquestionkey = item::create_itemkey();
            $copy->timecreated = time();
            $copy->timemodified = time();
            $copy->createdby = $USER->id;
            $copy->modifiedby = $USER->id;
            $this->fit_voices_to_language($copy);
            $copy->id = $DB->insert_record(constants::M_QTABLE, $copy);

            foreach (item::get_item_fileareas($sourcecontext, $oldid) as $filearea) {
                foreach ($fs->get_area_files($sourcecontext->id, constants::M_COMPONENT, $filearea, $oldid, 'id', false) as $file) {
                    $fs->create_file_from_storedfile([
                        'contextid' => $this->context->id,
                        'itemid' => $copy->id,
                        'userid' => $USER->id,
                    ], $file);
                }
            }

            // The item works on this record itself, so what it sets on it can be read back.
            $workrecord = clone $copy;
            $theitem = utils::fetch_item_from_itemrecord($workrecord, $this->moduleinstance, $this->context);
            if ($theitem) {
                // The speech model is made for the lesson's language and region, which may not be the source's.
                // It is only made again when the copy needs a different one.
                $theitem->update_create_langmodel($copy);
                $passagehash = $workrecord->passagehash ?? $copy->passagehash;
                if ((string) $passagehash !== (string) $copy->passagehash) {
                    $DB->set_field(constants::M_QTABLE, 'passagehash', $passagehash, ['id' => $copy->id]);
                }
                $theitem->files_changed();
            }
            \mod_minilesson\event\item_created::create_from_item(
                $DB->get_record(constants::M_QTABLE, ['id' => $copy->id]),
                $this->context
            )->trigger();
            $result->itemids[] = (int) $copy->id;
        }
        if ($result->itemids) {
            $this->place_items($result->itemids, $afteritemid);
        }
        return $result;
    }

    /**
     * Give an item copied from a lesson in another language this lesson's auto voice in place of each voice that
     * does not suit this lesson's language, as an import does. That covers the voice columns of the item type and
     * the voices packed into the TTS dialog and passage options.
     *
     * @param \stdClass $record the item record, changed in place
     */
    protected function fit_voices_to_language(\stdClass $record): void {
        $langcode = $this->moduleinstance->ttslanguage;
        $autovoice = function () use ($langcode) {
            $name = utils::fetch_auto_voice($langcode);
            foreach (constants::ALL_VOICES[$langcode] ?? [] as $code => $voicename) {
                if ($voicename === $name) {
                    return $code;
                }
            }
            return $name;
        };

        $classname = utils::fetch_itemtype_classname($record->type);
        $keycolumns = $classname && class_exists($classname) ? $classname::get_keycolumns() : item::get_keycolumns();
        foreach ($keycolumns as $column) {
            $dbname = $column['dbname'] ?? '';
            if (($column['type'] ?? '') !== 'voice' || !property_exists($record, $dbname)) {
                continue;
            }
            if (!utils::voice_fits_language($record->{$dbname}, $langcode)) {
                $record->{$dbname} = $autovoice();
            }
        }

        $packed = [
            constants::TTSDIALOGOPTS => [constants::TTSDIALOGVOICEA, constants::TTSDIALOGVOICEB, constants::TTSDIALOGVOICEC],
            constants::TTSPASSAGEOPTS => [constants::TTSPASSAGEVOICE],
        ];
        foreach ($packed as $optsfield => $voicefields) {
            if (empty($record->{$optsfield}) || !utils::is_json($record->{$optsfield})) {
                continue;
            }
            $opts = json_decode($record->{$optsfield});
            if (!is_object($opts)) {
                continue;
            }
            $changed = false;
            foreach ($voicefields as $voicefield) {
                if (isset($opts->{$voicefield}) && !utils::voice_fits_language($opts->{$voicefield}, $langcode)) {
                    $opts->{$voicefield} = $autovoice();
                    $changed = true;
                }
            }
            if ($changed) {
                $record->{$optsfield} = json_encode($opts);
            }
        }
    }

    /**
     * Delete items of this lesson, with their files.
     *
     * @param int[] $itemids the items
     * @param bool $dryrun list what would be deleted without deleting it
     * @return \stdClass with deleted (each {itemid, name, type, position}) and notfound (item ids)
     */
    public function delete_items(array $itemids, bool $dryrun): \stdClass {
        $result = (object) ['deleted' => [], 'notfound' => []];
        $records = $this->get_item_records();
        foreach (array_map('intval', $itemids) as $itemid) {
            if (!isset($records[$itemid])) {
                $result->notfound[] = $itemid;
                continue;
            }
            $record = $records[$itemid];
            if (!$dryrun) {
                $snapshot = clone $record;
                unset($snapshot->position);
                item::delete_item($itemid, $this->context);
                \mod_minilesson\event\item_deleted::create_from_item($snapshot, $this->context)->trigger();
            }
            $result->deleted[] = (object) [
                'itemid' => $itemid,
                'name' => (string) $record->name,
                'type' => (string) $record->type,
                'position' => (int) $record->position,
            ];
        }
        return $result;
    }

    /**
     * The file areas of an item that a caller may put files in: those its import takes files for.
     *
     * @param \stdClass $record the item's record
     * @return string[]
     */
    public static function upload_fileareas(\stdClass $record): array {
        $itemtypeclass = item::get_itemtype_class($record->type);
        if (!$itemtypeclass) {
            return [];
        }
        $areas = [];
        foreach ($itemtypeclass::get_keycolumns() as $coldef) {
            if ($coldef['type'] === 'anonymousfile') {
                $areas[] = $coldef['jsonname'];
            }
        }
        return array_values(array_unique($areas));
    }

    /**
     * Put files in one file area of an item, adding to or replacing what it holds.
     *
     * @param int $itemid the item
     * @param string $filearea the file area, one of upload_fileareas()
     * @param array $files file name => base64 content
     * @param bool $replace remove the files already in the area first. Otherwise a file of the same name is
     *        replaced and the others stay.
     * @return \stdClass with status (updated, invalid or notfound), message, version and files (the area's
     *         files after, each {filename, size, mimetype})
     */
    public function put_files(int $itemid, string $filearea, array $files, bool $replace): \stdClass {
        global $DB, $USER, $CFG;
        $result = (object) ['status' => self::STATUS_INVALID, 'message' => '', 'version' => 0, 'files' => []];
        $record = $DB->get_record(constants::M_QTABLE, ['id' => $itemid, 'minilesson' => $this->moduleinstance->id]);
        if (!$record) {
            $result->status = self::STATUS_NOTFOUND;
            $result->message = get_string('edit:notfound', constants::M_COMPONENT, $itemid);
            return $result;
        }
        $result->version = (int) $record->timemodified;
        $allowed = self::upload_fileareas($record);
        if (!in_array($filearea, $allowed, true)) {
            $result->message = get_string('edit:badfilearea', constants::M_COMPONENT, (object) [
                'filearea' => s($filearea),
                'type' => s($record->type),
                'allowed' => implode(', ', $allowed),
            ]);
            return $result;
        }
        if (!$files) {
            $result->message = get_string('edit:nofilesgiven', constants::M_COMPONENT);
            return $result;
        }
        $maxbytes = get_max_upload_file_size($CFG->maxbytes, $this->course->maxbytes);
        $decoded = [];
        foreach ($files as $filename => $content) {
            $filename = (string) $filename;
            if ($filename === '' || clean_param($filename, PARAM_FILE) !== $filename) {
                $result->message = get_string('edit:badfilename', constants::M_COMPONENT, s($filename));
                return $result;
            }
            $bytes = is_string($content) ? base64_decode($content, true) : false;
            if ($bytes === false || $bytes === '') {
                $result->message = get_string('error:importbase64', 'minilessonitem_smartframe', s($filename));
                return $result;
            }
            if ($maxbytes && strlen($bytes) > $maxbytes) {
                $result->message = get_string('edit:filetoobig', constants::M_COMPONENT, (object) [
                    'filename' => s($filename),
                    'max' => display_size($maxbytes),
                ]);
                return $result;
            }
            $decoded[$filename] = $bytes;
        }

        $fs = get_file_storage();
        if ($replace) {
            $fs->delete_area_files($this->context->id, constants::M_COMPONENT, $filearea, $itemid);
        }
        foreach ($decoded as $filename => $bytes) {
            $existing = $fs->get_file($this->context->id, constants::M_COMPONENT, $filearea, $itemid, '/', $filename);
            if ($existing) {
                $existing->delete();
            }
            $fs->create_file_from_string([
                'contextid' => $this->context->id,
                'component' => constants::M_COMPONENT,
                'filearea' => $filearea,
                'itemid' => $itemid,
                'filepath' => '/',
                'filename' => $filename,
                'userid' => $USER->id,
            ], $bytes);
        }

        $version = max(time(), (int) $record->timemodified + 1);
        $DB->update_record(constants::M_QTABLE, (object) [
            'id' => $itemid,
            'timemodified' => $version,
            'modifiedby' => $USER->id,
        ]);
        $record = $DB->get_record(constants::M_QTABLE, ['id' => $itemid]);
        $theitem = utils::fetch_item_from_itemrecord(clone $record, $this->moduleinstance, $this->context);
        if ($theitem) {
            $theitem->files_changed();
        }
        \mod_minilesson\event\item_updated::create_from_item($record, $this->context, [$filearea])->trigger();

        foreach ($fs->get_area_files($this->context->id, constants::M_COMPONENT, $filearea, $itemid, 'filename', false) as $file) {
            $result->files[] = (object) [
                'filename' => $file->get_filename(),
                'size' => (int) $file->get_filesize(),
                'mimetype' => (string) $file->get_mimetype(),
            ];
        }
        $result->status = self::STATUS_UPDATED;
        $result->version = $version;
        return $result;
    }

    /**
     * The media files of an item, with pictures made small enough for an AI to look at.
     *
     * @param int $itemid the item
     * @param string $filearea one file area, or '' for all of them
     * @param int $maxwidth the widest a picture is given, in pixels
     * @return \stdClass[]|null each {filearea, filename, mimetype, size, url, width, height, content, contentmimetype},
     *         or null if this lesson has no such item. A picture's content is base64 (JPEG or PNG, scaled down),
     *         an SVG's is its text, and other files have none.
     */
    public function get_media(int $itemid, string $filearea, int $maxwidth): ?array {
        global $DB;
        if (!$DB->record_exists(constants::M_QTABLE, ['id' => $itemid, 'minilesson' => $this->moduleinstance->id])) {
            return null;
        }
        $areas = item::get_item_fileareas($this->context, $itemid);
        if ($filearea !== '') {
            $areas = array_intersect($areas, [$filearea]);
        }
        $fs = get_file_storage();
        $media = [];
        foreach ($areas as $area) {
            foreach ($fs->get_area_files($this->context->id, constants::M_COMPONENT, $area, $itemid, 'filename', false) as $file) {
                $entry = (object) [
                    'filearea' => $area,
                    'filename' => $file->get_filename(),
                    'mimetype' => (string) $file->get_mimetype(),
                    'size' => (int) $file->get_filesize(),
                    'url' => \moodle_url::make_pluginfile_url(
                        $this->context->id,
                        constants::M_COMPONENT,
                        $area,
                        $itemid,
                        '/',
                        $file->get_filename()
                    )->out(false),
                    'width' => 0,
                    'height' => 0,
                    'content' => '',
                    'contentmimetype' => '',
                ];
                if ($entry->mimetype === 'image/svg+xml') {
                    if ($entry->size <= 200000) {
                        $entry->content = $file->get_content();
                        $entry->contentmimetype = 'image/svg+xml';
                    }
                } else if (strpos($entry->mimetype, 'image/') === 0) {
                    self::add_scaled_picture($entry, $file, $maxwidth);
                }
                $media[] = $entry;
            }
        }
        return $media;
    }

    /**
     * Add a picture's content to a media entry, scaled down to a width, as JPEG (or PNG when it has transparency).
     *
     * @param \stdClass $entry the media entry, changed in place
     * @param \stored_file $file the picture
     * @param int $maxwidth the widest it is given, in pixels
     */
    protected static function add_scaled_picture(\stdClass $entry, \stored_file $file, int $maxwidth): void {
        if (!function_exists('imagecreatefromstring') || $entry->size > 20 * 1024 * 1024) {
            return;
        }
        $original = @imagecreatefromstring($file->get_content());
        if (!$original) {
            return;
        }
        $width = imagesx($original);
        $height = imagesy($original);
        $scale = min(1, $maxwidth / max(1, $width));
        $newwidth = max(1, (int) round($width * $scale));
        $newheight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($newwidth, $newheight);
        // A photo saved as PNG is far smaller as JPEG. Keep PNG only for a picture that is see-through somewhere.
        $png = self::has_transparency($original, $width, $height);
        if ($png) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 255, 255, 255, 127));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }
        imagecopyresampled($canvas, $original, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        ob_start();
        if ($png) {
            imagepng($canvas, null, 9);
        } else {
            imagejpeg($canvas, null, 80);
        }
        $bytes = ob_get_clean();
        $entry->width = $newwidth;
        $entry->height = $newheight;
        $entry->content = base64_encode($bytes);
        $entry->contentmimetype = $png ? 'image/png' : 'image/jpeg';
    }

    /**
     * The lesson's item records in lesson order, each with its 1-based position.
     *
     * @return \stdClass[] keyed by item id
     */
    protected function get_item_records(): array {
        global $DB;
        $records = $DB->get_records(
            constants::M_QTABLE,
            ['minilesson' => $this->moduleinstance->id],
            'itemorder ASC, id ASC'
        );
        $position = 0;
        foreach ($records as $record) {
            $record->position = ++$position;
        }
        return $records;
    }

    /**
     * Export items of this lesson in the import format, each with its itemid, version and position.
     *
     * @param int[] $itemids the items wanted, or none for every item
     * @param string[] $fields the item fields wanted, or none for every field. The type, the name and what
     *        a read adds are always given.
     * @param bool $excludefiles give a placeholder in place of each file's content
     * @return \stdClass with items, files (by filesid, then file area, then file name) and notfound (item ids)
     */
    public function export_items(array $itemids = [], array $fields = [], bool $excludefiles = true): \stdClass {
        $records = $this->get_item_records();
        $result = (object) ['items' => [], 'files' => new \stdClass(), 'notfound' => []];
        if ($itemids) {
            $wanted = [];
            foreach ($itemids as $itemid) {
                if (isset($records[$itemid])) {
                    $wanted[$itemid] = $records[$itemid];
                } else {
                    $result->notfound[] = (int) $itemid;
                }
            }
            // Lesson order, whatever order they were asked for in.
            $records = array_intersect_key($records, $wanted);
        }

        foreach ($records as $record) {
            $position = $record->position;
            unset($record->position);
            $itemobj = $this->import->export_item_as_jsonobj($record);
            if (!$itemobj) {
                // An item of a type that is no longer installed.
                $result->notfound[] = (int) $record->id;
                continue;
            }
            $files = $itemobj->files;
            // Stored translations are of no use to a caller editing the item, and an update never takes them.
            unset($itemobj->files, $itemobj->localtranslations);
            self::tidy_lines($itemobj);

            if ($fields) {
                $keep = array_merge(['type', 'name'], $fields);
                foreach (array_keys((array) $itemobj) as $field) {
                    if (!in_array($field, $keep)) {
                        unset($itemobj->{$field});
                    }
                }
                $files = array_intersect_key($files, array_flip($fields));
            }

            $itemobj->itemid = (int) $record->id;
            $itemobj->version = (int) $record->timemodified;
            $itemobj->position = $position;
            if ($files) {
                if ($excludefiles) {
                    foreach ($files as $filearea => $areafiles) {
                        $files[$filearea] = array_fill_keys(array_keys($areafiles), self::FILE_PLACEHOLDER);
                    }
                }
                $itemobj->filesid = (int) $record->id;
                $result->files->{$record->id} = $files;
            }
            $result->items[] = $itemobj;
        }
        return $result;
    }

    /**
     * Change some fields of one item, leaving the rest of it as stored.
     *
     * @param int $itemid the item
     * @param \stdClass $changes the new value of each field to change, by import field name
     * @param int|null $version the version the caller read. If the item has changed since, nothing is saved.
     * @param bool $dryrun work out and report the changes without saving them
     * @return \stdClass with itemid, status (a STATUS_ constant), message, version (as stored after the call),
     *         changes (an array of {field, before, after}) and warnings (an array of strings)
     */
    public function update_item(int $itemid, \stdClass $changes, ?int $version = null, bool $dryrun = false): \stdClass {
        global $DB;

        $result = (object) [
            'itemid' => $itemid,
            'status' => self::STATUS_INVALID,
            'message' => '',
            'version' => 0,
            'changes' => [],
            'warnings' => [],
        ];

        $old = $DB->get_record(constants::M_QTABLE, ['id' => $itemid, 'minilesson' => $this->moduleinstance->id]);
        if (!$old) {
            $result->status = self::STATUS_NOTFOUND;
            $result->message = get_string('edit:notfound', constants::M_COMPONENT, $itemid);
            return $result;
        }
        $result->version = (int) $old->timemodified;

        if ($version && $version !== (int) $old->timemodified) {
            $result->status = self::STATUS_CONFLICT;
            $result->message = get_string('edit:conflict', constants::M_COMPONENT);
            return $result;
        }

        $itemtypeclass = item::get_itemtype_class($old->type);
        if (!$itemtypeclass) {
            $result->message = get_string('error:invaliditemtype', constants::M_COMPONENT);
            return $result;
        }
        $keycolumns = $itemtypeclass::get_keycolumns();
        $coldefs = [];
        $aliases = [];
        foreach ($keycolumns as $coldef) {
            $coldefs[$coldef['jsonname']] = $coldef;
            if (!empty($coldef['jsonalias'])) {
                $aliases[$coldef['jsonalias']] = $coldef['jsonname'];
            }
        }

        // The item as an export gives it. Fields the caller does not send keep these values while the
        // changes are checked. What is saved for them is the stored record, not this.
        $full = $this->import->export_item_as_jsonobj(clone $old);
        $files = $full->files;
        unset($full->files, $full->localtranslations);
        self::tidy_lines($full);
        $full = json_decode(json_encode($full));

        // Tidy the changes: drop what a read added, and give each field its current name.
        $changes = clone $changes;
        foreach (array_merge(self::META_FIELDS, ['filesid', 'localtranslations']) as $notafield) {
            unset($changes->{$notafield});
        }
        // The packed options are made from the dialog and passage fields, and are not set on their own.
        foreach ([constants::TTSDIALOGOPTS, constants::TTSPASSAGEOPTS] as $packedcol) {
            foreach ($keycolumns as $coldef) {
                if ($coldef['dbname'] === $packedcol) {
                    unset($changes->{$coldef['jsonname']});
                }
            }
        }
        foreach ($aliases as $alias => $jsonname) {
            if (property_exists($changes, $alias)) {
                if (!property_exists($changes, $jsonname)) {
                    $changes->{$jsonname} = $changes->{$alias};
                }
                unset($changes->{$alias});
            }
        }
        if (property_exists($changes, 'type')) {
            if ((string) $changes->type !== (string) $old->type) {
                $result->message = get_string('edit:typechange', constants::M_COMPONENT);
                return $result;
            }
            unset($changes->type);
        }
        foreach ($changes as $field => $value) {
            $coldef = $coldefs[$field] ?? null;
            if ($coldef && in_array($coldef['type'], ['file', 'anonymousfile'])) {
                // A caller that sends back a whole item it read sends its file names too. That is no change.
                if ($coldef['dbname'] && self::canonical($value) === self::canonical($full->{$field} ?? null)) {
                    unset($changes->{$field});
                    continue;
                }
                $result->message = get_string('edit:nofiles', constants::M_COMPONENT, s($field));
                return $result;
            }
        }

        // Map the item with the changes to DB columns, as an import does. This also refuses field names the
        // item type does not have.
        $merged = clone $full;
        foreach ($changes as $field => $value) {
            $merged->{$field} = $value;
        }
        [$newrecord, $failure] = $this->prepare($merged, $files);
        if ($failure) {
            $result->message = $failure->message;
            return $result;
        }

        // The stored record, with the packed dialog and passage options unpacked to the fields they hold.
        $current = clone $old;
        if (!empty($current->{constants::TTSDIALOG})) {
            $current = utils::unpack_ttsdialogopts($current);
        }
        if (!empty($current->{constants::TTSPASSAGE})) {
            $current = utils::unpack_ttspassageopts($current);
        }

        // Which of the fields the caller sent hold a new value.
        $changedcols = [];
        foreach ($changes as $field => $value) {
            $coldef = $coldefs[$field] ?? null;
            if (!$coldef || !$coldef['dbname']) {
                continue;
            }
            $dbname = $coldef['dbname'];
            $before = $full->{$field} ?? self::export_default($coldef);
            if ($coldef['type'] === 'voice' && in_array(strtolower(trim((string) $value)), ['', 'auto'])) {
                // Asking for "auto" means any voice of the lesson's language, and the item has one already.
                continue;
            }
            if (property_exists($current, $dbname)) {
                $changed = self::canonical($newrecord->{$dbname} ?? null, $coldef['type'])
                    !== self::canonical($current->{$dbname}, $coldef['type']);
            } else {
                // Not a stored column (the item type turns it into something else when it saves), so the
                // only thing to compare with is what the export gives.
                $changed = self::canonical($value) !== self::canonical($before);
            }
            if ($changed) {
                $changedcols[$dbname] = true;
                $result->changes[] = (object) [
                    'field' => $field,
                    'before' => self::display($before),
                    'after' => self::display($value),
                ];
            }
        }

        if (!$changedcols) {
            $result->status = self::STATUS_UNCHANGED;
            return $result;
        }

        // The item type's own content checks. An item that already failed them the same way before the
        // changes is let through with a warning, or it could never be edited at all.
        $failure = $this->import->check_record($newrecord);
        if ($failure) {
            [$baserecord, $basefailure] = $this->prepare(clone $full, $files);
            $basefailure = $basefailure ?: $this->import->check_record($baserecord);
            if (!$basefailure || $basefailure->message !== $failure->message) {
                $result->message = $failure->message;
                return $result;
            }
            $result->warnings[] = $failure->message;
        }
        foreach ($itemtypeclass::import_warnings($newrecord, $this->cm) as $warning) {
            $result->warnings[] = $warning;
        }

        if ($dryrun) {
            $result->status = self::STATUS_WOULDUPDATE;
            return $result;
        }

        // What is saved: the stored record, with the changed columns laid over it.
        $save = clone $old;
        $save->itemid = $old->id;
        foreach (array_keys($changedcols) as $dbname) {
            $save->{$dbname} = $newrecord->{$dbname};
        }
        $this->set_packed_group(
            $save,
            $current,
            $newrecord,
            $changedcols,
            self::DIALOG_FIELDS,
            constants::TTSDIALOG,
            constants::TTSDIALOGOPTS
        );
        $this->set_packed_group(
            $save,
            $current,
            $newrecord,
            $changedcols,
            self::PASSAGE_FIELDS,
            constants::TTSPASSAGE,
            constants::TTSPASSAGEOPTS
        );

        $transaction = $DB->start_delegated_transaction();
        try {
            // The same steps, in the same order, as the item form and the import.
            $theitem = utils::fetch_item_from_itemrecord($save, $this->moduleinstance, $this->context);
            $theitem->deaccent();
            $theitem->update_create_langmodel($old);
            $theitem->update_create_phonetic($old);
            $saved = $theitem->update_insert_item();
            if (!$saved || !empty($saved->error)) {
                throw new \moodle_exception('edit:savefailed', constants::M_COMPONENT, '', $saved->message ?? '');
            }
            // The save writes an empty string to the text columns it is given as null. Put those back, so that
            // a column nobody changed is stored exactly as it was.
            $stored = $DB->get_record(constants::M_QTABLE, ['id' => $old->id], '*', MUST_EXIST);
            $restore = [];
            foreach ($old as $column => $value) {
                if ($value === null && $stored->{$column} === '') {
                    $restore[$column] = null;
                }
            }
            if ($restore) {
                $DB->update_record(constants::M_QTABLE, (object) (['id' => $old->id] + $restore));
            }
            // The version is the time modified, in seconds. Make sure it moves on even for two saves in the
            // same second, or a caller holding the older one would not be told of the newer.
            $newversion = (int) $stored->timemodified;
            if ($newversion <= (int) $old->timemodified) {
                $newversion = (int) $old->timemodified + 1;
                $DB->set_field(constants::M_QTABLE, 'timemodified', $newversion, ['id' => $old->id]);
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            try {
                $transaction->rollback($e);
            } catch (\Throwable $rolledback) {
                // The rollback throws the exception it was given back at us.
                unset($rolledback);
            }
            $result->status = self::STATUS_ERROR;
            $result->message = $e->getMessage();
            return $result;
        }

        \mod_minilesson\event\item_updated::create_from_item(
            $DB->get_record(constants::M_QTABLE, ['id' => $old->id]),
            $this->context,
            array_column($result->changes, 'field')
        )->trigger();

        $result->status = self::STATUS_UPDATED;
        $result->version = $newversion;
        return $result;
    }

    /**
     * Map an item in the import format to a DB ready record, with its files attached.
     *
     * @param \stdClass $itemobj the item
     * @param array $files the item's files, by file area then file name
     * @return array [\stdClass|null the record, \stdClass|null a failure entry]
     */
    protected function prepare(\stdClass $itemobj, array $files): array {
        $itemobj->filesid = 1;
        $this->import->set_reader((object) ['items' => [], 'files' => (object) [1 => $files]], true);
        return $this->import->prepare_record($itemobj);
    }

    /**
     * Make the record to save carry a TTS dialog or passage only when it is to be changed.
     *
     * A dialog (or passage) is stored with its options packed in a second column, which the save packs again
     * from the separate option fields whenever the dialog is on the record. The stored record does not have
     * those fields. So when nothing of the group changes, the group is taken off the record and the save leaves
     * both columns alone. When something does change, the option fields that did not change are filled in from
     * the stored options.
     *
     * @param \stdClass $save the record to save, changed in place
     * @param \stdClass $current the stored record, with its packed options unpacked
     * @param \stdClass $newrecord the item with the changes, as DB columns
     * @param array $changedcols the changed DB columns, as keys
     * @param string[] $groupfields the group's fields: the text first, then its options
     * @param string $textcol the column holding the dialog or passage
     * @param string $packedcol the column holding the packed options
     */
    protected function set_packed_group(
        \stdClass $save,
        \stdClass $current,
        \stdClass $newrecord,
        array $changedcols,
        array $groupfields,
        string $textcol,
        string $packedcol
    ): void {
        if (!array_intersect_key($changedcols, array_flip($groupfields))) {
            unset($save->{$textcol}, $save->{$packedcol});
            return;
        }
        foreach ($groupfields as $fieldname) {
            if ($fieldname === $textcol || isset($changedcols[$fieldname])) {
                continue;
            }
            // The stored option if there is one, or else the default the import gave it.
            $save->{$fieldname} = property_exists($current, $fieldname)
                ? $current->{$fieldname}
                : ($newrecord->{$fieldname} ?? null);
        }
    }

    /**
     * Whether a picture is see-through anywhere, judged from a grid of points across it.
     *
     * @param \GdImage $image the picture
     * @param int $width its width
     * @param int $height its height
     * @return bool
     */
    protected static function has_transparency($image, int $width, int $height): bool {
        if (imagecolortransparent($image) >= 0) {
            return true;
        }
        if (imageistruecolor($image) === false) {
            return false;
        }
        $step = max(1, (int) floor(min($width, $height) / 40));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Take the carriage return off the end of each line of an item's list fields.
     *
     * Text saved from a form has Windows line ends, and the export splits it into lines on the newline alone.
     *
     * @param \stdClass $itemobj an exported item, changed in place
     */
    protected static function tidy_lines(\stdClass $itemobj): void {
        foreach ($itemobj as $field => $value) {
            if (is_array($value)) {
                $itemobj->{$field} = array_map(function ($line) {
                    return is_string($line) ? rtrim($line, "\r") : $line;
                }, $value);
            }
        }
    }

    /**
     * The value an export gives a field that it leaves out (an optional field holding its default).
     *
     * @param array $coldef the field's key column definition
     * @return mixed
     */
    protected static function export_default(array $coldef) {
        switch ($coldef['type']) {
            case 'stringarray':
                return (array) $coldef['default'];
            case 'boolean':
                return $coldef['default'] ? 'yes' : 'no';
            case 'voice':
                return 'auto';
            case 'voiceopts':
                return 'normal';
            case 'layout':
                return 'auto';
            default:
                return $coldef['default'];
        }
    }

    /**
     * A value in a form that compares equal whenever a save would store the same thing.
     *
     * @param mixed $value
     * @param string $type the field's key column type, when the value is a DB value
     * @return string
     */
    protected static function canonical($value, string $type = ''): string {
        if (is_array($value) || is_object($value)) {
            $value = implode("\n", array_map('strval', array_values((array) $value)));
        }
        if (is_bool($value)) {
            $value = (int) $value;
        }
        if (in_array($type, ['int', 'boolean', 'voiceopts', 'layout'])) {
            return (string) (int) $value;
        }
        return trim(str_replace("\r\n", "\n", (string) $value));
    }

    /**
     * A value as text, to report what changed.
     *
     * @param mixed $value
     * @return string
     */
    protected static function display($value): string {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
