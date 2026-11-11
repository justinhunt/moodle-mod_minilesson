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
        $this->import = new import($moduleinstance, $context, $course, $cm);
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
