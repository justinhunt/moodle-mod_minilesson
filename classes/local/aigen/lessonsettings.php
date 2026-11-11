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
use mod_minilesson\utils;

/**
 * The lesson settings the aigen web service may read and change.
 *
 * A short list, each named, described and checked. Settings that do more than store a value when they change
 * (the language and region, which need new speech models; the grade, which changes the gradebook) are left out:
 * a teacher changes those on the settings page.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonsettings {
    /** @var string Plain text, one line. */
    const TYPE_TEXT = 'text';
    /** @var string HTML. */
    const TYPE_HTML = 'html';
    /** @var string 1 or 0. */
    const TYPE_BOOL = 'boolean';
    /** @var string A whole number, 0 or more. */
    const TYPE_COUNT = 'count';
    /** @var string One of a list of values. */
    const TYPE_CHOICE = 'choice';

    /**
     * The settings, by name.
     *
     * @return array name => [type, description, options (value => label, for a choice)]
     */
    public static function definitions(): array {
        $finishoptions = [];
        foreach (utils::fetch_options_finishscreen() as $value => $label) {
            $finishoptions[(string) $value] = $label;
        }
        return [
            'name' => [self::TYPE_TEXT, 'The lesson\'s name, as shown on the course page.', []],
            'intro' => [self::TYPE_HTML, 'The lesson\'s description (HTML).', []],
            'visible' => [self::TYPE_BOOL, '1 if learners can see the lesson on the course page, 0 if it is hidden.', []],
            'showqtitles' => [self::TYPE_BOOL, '1 to show each item\'s name as a title above it.', []],
            'showitemreview' => [self::TYPE_BOOL, '1 to let learners review each item\'s answers after an attempt.', []],
            'allowcontinueattempts' => [self::TYPE_BOOL, '1 to let a learner continue an attempt they left unfinished.', []],
            'nativetranslation' => [self::TYPE_BOOL, '1 to let learners translate item text into their native language.', []],
            'maxattempts' => [self::TYPE_COUNT, 'How many attempts a learner may make. 0 means no limit.', []],
            'pagelayout' => [
                self::TYPE_CHOICE,
                'The Moodle page layout the lesson is shown in.',
                utils::fetch_pagelayout_options(),
            ],
            'containerwidth' => [self::TYPE_CHOICE, 'How wide the lesson is on the page.', utils::get_containerwidth_options()],
            'nextbuttonalign' => [
                self::TYPE_CHOICE,
                'Where the next button sits under each item.',
                utils::get_nextbuttonalign_options(),
            ],
            'finishscreen' => [self::TYPE_CHOICE, 'What learners see when they finish.', $finishoptions],
            'finishscreencustom' => [
                self::TYPE_HTML,
                'The finish screen, when finishscreen is the custom one (a Mustache template in HTML).',
                [],
            ],
            'nativelang' => [self::TYPE_CHOICE, 'The learners\' native language, by default.', utils::get_nativelang_options()],
        ];
    }

    /**
     * The current value of every setting.
     *
     * @param \cm_info|\stdClass $cm the course module
     * @param \stdClass $moduleinstance the lesson's record
     * @return array name => value, as a string
     */
    public static function get_values($cm, \stdClass $moduleinstance): array {
        $values = [];
        foreach (array_keys(self::definitions()) as $name) {
            $values[$name] = $name === 'visible' ? (string) (int) $cm->visible : (string) ($moduleinstance->{$name} ?? '');
        }
        return $values;
    }

    /**
     * Check and change some settings.
     *
     * @param \stdClass $cm the course module
     * @param \stdClass $moduleinstance the lesson's record
     * @param array $changes name => new value
     * @param bool $dryrun check and report without saving
     * @return \stdClass with status (updated, wouldupdate, unchanged or invalid), message and changes (each
     *         {setting, before, after})
     */
    public static function apply(\stdClass $cm, \stdClass $moduleinstance, array $changes, bool $dryrun): \stdClass {
        global $DB;
        $result = (object) ['status' => itemeditor::STATUS_INVALID, 'message' => '', 'changes' => []];
        $definitions = self::definitions();
        $current = self::get_values($cm, $moduleinstance);
        $new = [];
        foreach ($changes as $name => $value) {
            if (!isset($definitions[$name])) {
                $result->message = get_string('edit:unknownsetting', constants::M_COMPONENT, (object) [
                    'setting' => s($name),
                    'allowed' => implode(', ', array_keys($definitions)),
                ]);
                return $result;
            }
            [$type, , $options] = $definitions[$name];
            $clean = self::clean($type, $value, $options);
            if ($clean === null) {
                $result->message = get_string('edit:badsetting', constants::M_COMPONENT, (object) [
                    'setting' => s($name),
                    'allowed' => $type === self::TYPE_CHOICE ? implode(', ', array_keys($options)) : $type,
                ]);
                return $result;
            }
            if ($clean !== $current[$name]) {
                $new[$name] = $clean;
                $result->changes[] = (object) ['setting' => $name, 'before' => $current[$name], 'after' => $clean];
            }
        }
        if (!$new) {
            $result->status = itemeditor::STATUS_UNCHANGED;
            return $result;
        }
        if ($dryrun) {
            $result->status = itemeditor::STATUS_WOULDUPDATE;
            return $result;
        }

        $record = (object) ['id' => $moduleinstance->id, 'timemodified' => time()];
        foreach ($new as $name => $value) {
            if ($name === 'visible') {
                continue;
            }
            $record->{$name} = $value;
            if ($name === 'intro') {
                $record->introformat = FORMAT_HTML;
            }
        }
        $DB->update_record(constants::M_TABLE, $record);
        if (isset($new['visible'])) {
            set_coursemodule_visible($cm->id, (int) $new['visible']);
        }
        rebuild_course_cache($cm->course, true);
        $cminfo = get_fast_modinfo($cm->course)->get_cm($cm->id);
        \core\event\course_module_updated::create_from_cm($cminfo)->trigger();

        $result->status = itemeditor::STATUS_UPDATED;
        return $result;
    }

    /**
     * A setting's value, checked and in the form it is stored in.
     *
     * @param string $type the setting's type
     * @param mixed $value the value given
     * @param array $options the allowed values, for a choice
     * @return string|null the value as stored, or null if it is not allowed
     */
    protected static function clean(string $type, $value, array $options): ?string {
        if (is_bool($value)) {
            $value = (int) $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);
        switch ($type) {
            case self::TYPE_TEXT:
                $value = clean_param($value, PARAM_TEXT);
                return ($value === '' || \core_text::strlen($value) > 255) ? null : $value;
            case self::TYPE_HTML:
                return $value;
            case self::TYPE_BOOL:
                $map = ['1' => '1', '0' => '0', 'yes' => '1', 'no' => '0', 'true' => '1', 'false' => '0'];
                return $map[strtolower($value)] ?? null;
            case self::TYPE_COUNT:
                return preg_match('/^\d+$/', $value) ? (string) (int) $value : null;
            case self::TYPE_CHOICE:
                return array_key_exists($value, $options) ? $value : null;
        }
        return null;
    }
}
