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

namespace minilessonitem_smartframe;

use minilessonitem_smartframe\local\package;
use minilessonitem_smartframe\local\package_exception;
use minilessonitem_smartframe\local\widgetstore;
use mod_minilesson\local\itemtype\item;
use mod_minilesson\constants;

/**
 * A widget item: an uploaded Poodll Widget Package (a ZIP of HTML, CSS and JS) that runs in a sandboxed iframe,
 * and may report a score. Its files are unpacked into Moodle and served by widget.php (see local\widgetstore).
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_CONTENT];

    /** @var string File area holding the widget's ZIP or HTML file. */
    public const FILE = 'customfile1';
    /** @var string The published revision (sha256 of the ZIP), '' until published. */
    public const REVISION = 'customtext1';
    /** @var string The interface language: 'auto' or a language tag from the manifest. */
    public const UILANGUAGE = 'customtext2';
    /** @var string The checked manifest, as JSON. */
    public const MANIFEST = 'customdata1';
    /** @var string What to do with a score the widget reports: one of the GRADE_* values. */
    public const GRADEPOLICY = 'customint2';

    /** @var int Ignore the widget's score. */
    public const GRADE_IGNORE = 0;
    /** @var int Save the widget's score with the attempt, but do not count it in the lesson grade. */
    public const GRADE_RECORD = 1;
    /** @var int Count the widget's score in the lesson grade, scaled to the item's total marks. */
    public const GRADE_USE = 2;

    /** @var string The interface language setting that picks a language for each learner. */
    public const UILANGUAGE_AUTO = 'auto';

    /**
     * This item type is experimental.
     *
     * @return bool
     */
    public static function is_experimental() {
        return true;
    }

    /**
     * The stored manifest of this item's widget.
     *
     * @return \stdClass|null
     */
    public function get_manifest(): ?\stdClass {
        $json = $this->itemrecord->{self::MANIFEST} ?? '';
        $manifest = $json ? json_decode($json) : null;
        return $manifest instanceof \stdClass ? $manifest : null;
    }

    /**
     * Export the data for the mustache template, and for the item's JS.
     *
     * @param \renderer_base $output renderer
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output) {
        global $CFG;

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        $itemrecord = $this->itemrecord;
        $testitem->widgeturl = '';
        $testitem->widgetbase = '';
        $testitem->widgettitle = '';
        $manifest = $this->get_manifest();
        $revision = $itemrecord->{self::REVISION} ?? '';
        $itemid = (int) ($itemrecord->id ?? 0);

        // A saved item whose widget files are missing (a restored, copied or failed item) gets them now.
        if ($manifest && $itemid && !widgetstore::is_published($this->context, $itemid, $revision, $manifest->entry)) {
            $revision = $this->publish_widget() ? $this->itemrecord->{self::REVISION} : '';
            $manifest = $this->get_manifest();
        }

        if ($manifest && widgetstore::is_revision($revision)) {
            $uilanguage = self::resolve_ui_language(
                $itemrecord->{self::UILANGUAGE} ?? self::UILANGUAGE_AUTO,
                $manifest,
                $this->moduleinstance
            );
            $parentorigin = self::origin_of($CFG->wwwroot);
            $url = new \moodle_url(widgetstore::entry_url($itemid, $revision, $manifest->entry), [
                'lang' => $uilanguage,
                'parentOrigin' => $parentorigin,
            ]);
            $testitem->widgeturl = $url->out(false);
            $testitem->widgetbase = widgetstore::base_url($itemid, $revision);
            $testitem->widgettitle = $manifest->title;
            $testitem->widgetresizes = !empty($manifest->capabilities->resize);
            $testitem->uilanguage = $uilanguage;
            $testitem->parentorigin = $parentorigin;
        }

        $policy = (int) ($itemrecord->{self::GRADEPOLICY} ?? self::GRADE_IGNORE);
        if ($policy !== self::GRADE_RECORD && $policy !== self::GRADE_USE) {
            $policy = self::GRADE_IGNORE;
        }
        // A widget that says it never reports a score is treated as ungraded, whatever the setting says.
        if (!$manifest || empty($manifest->capabilities->grade)) {
            $policy = self::GRADE_IGNORE;
        }
        $testitem->gradepolicy = $policy;
        $testitem->totalmarks = max(1, (int) ($itemrecord->{constants::TOTALMARKS} ?? 1));

        return $testitem;
    }

    /**
     * Pick the interface language for this learner, from the languages the widget offers.
     *
     * 'auto' tries, in order: the learner's own native language preference, the lesson's native language,
     * then the learner's Moodle language. Each is matched exactly, then by its base language (fr-CA takes fr).
     * When nothing matches, the widget's default language is used.
     *
     * @param string $setting the item's setting: 'auto' or a language tag
     * @param \stdClass $manifest the checked manifest
     * @param \stdClass|false $moduleinstance the minilesson
     * @return string a language tag from the manifest
     */
    public static function resolve_ui_language(string $setting, \stdClass $manifest, $moduleinstance): string {
        $offered = $manifest->uiLanguages ?? [];
        $default = $manifest->defaultUiLanguage ?? ($offered[0] ?? 'en');
        if ($setting !== self::UILANGUAGE_AUTO) {
            $wanted = [$setting];
        } else {
            $wanted = [
                get_user_preferences(constants::NATIVELANG_PREF),
                $moduleinstance->nativelang ?? null,
                current_language(),
            ];
        }
        foreach ($wanted as $lang) {
            $tag = is_string($lang) ? package::canonical_lang($lang) : false;
            if (!$tag) {
                continue;
            }
            $match = self::match_language($tag, $offered);
            if ($match) {
                return $match;
            }
        }
        return $default;
    }

    /**
     * Match a language tag to one of a list: exactly, then by base language.
     *
     * @param string $tag the wanted tag
     * @param array $offered the tags on offer
     * @return string|null the matching tag from $offered
     */
    protected static function match_language(string $tag, array $offered): ?string {
        foreach ($offered as $lang) {
            if (strcasecmp($lang, $tag) === 0) {
                return $lang;
            }
        }
        $base = strtolower(explode('-', $tag)[0]);
        foreach ($offered as $lang) {
            if (strtolower($lang) === $base) {
                return $lang;
            }
        }
        foreach ($offered as $lang) {
            if (strtolower(explode('-', $lang)[0]) === $base) {
                return $lang;
            }
        }
        return null;
    }

    /**
     * Save the item, then check and publish its widget.
     *
     * @return \stdClass result, as for the parent
     */
    public function update_insert_item() {
        $ret = parent::update_insert_item();
        if (!$ret->error && !empty($ret->item->id)) {
            $this->itemrecord->id = $ret->item->id;
            $this->publish_widget();
        }
        return $ret;
    }

    /**
     * The stored widget file (ZIP or HTML) of this item.
     *
     * @return \stored_file|null
     */
    protected function get_widget_file(): ?\stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            constants::M_COMPONENT,
            self::FILE,
            $this->itemrecord->id,
            'id DESC',
            false
        );
        foreach ($files as $file) {
            if (package::is_package_name($file->get_filename())) {
                return $file;
            }
        }
        return null;
    }

    /**
     * Check this item's widget file, publish it, and store its manifest and revision.
     *
     * The authoring form has already checked the file, so failing here means it changed some other way
     * (an import) or the files could not be stored. The item then stays saved, with no revision, and the
     * learner sees a "not available" message rather than a broken frame.
     *
     * @return bool true if the widget is published
     */
    public function publish_widget(): bool {
        global $DB;
        $file = $this->get_widget_file();
        $manifest = null;
        $revision = '';
        $ok = false;
        if ($file) {
            try {
                $package = package::from_stored_file($file);
                $manifest = $package->get_manifest();
                widgetstore::publish($package, $this->context, (int) $this->itemrecord->id);
                $revision = $package->get_revision();
                $ok = true;
            } catch (package_exception $e) {
                debugging(
                    'Widget not published for item ' . $this->itemrecord->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }
        $manifestjson = $manifest ? json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        // A failed retry when the lesson is shown changes nothing, so it writes nothing.
        if (
            ($this->itemrecord->{self::REVISION} ?? '') !== $revision
                || ($this->itemrecord->{self::MANIFEST} ?? '') !== $manifestjson
        ) {
            $DB->update_record(constants::M_QTABLE, (object) [
                'id' => $this->itemrecord->id,
                self::REVISION => $revision,
                self::MANIFEST => $manifestjson,
            ]);
        }
        $this->itemrecord->{self::REVISION} = $revision;
        $this->itemrecord->{self::MANIFEST} = $manifestjson;
        return $ok;
    }

    /**
     * The origin (scheme, host and port) of a URL.
     *
     * @param string $url the URL
     * @return string e.g. "https://school.example.org", or '' if it is not an http(s) URL
     */
    public static function origin_of(string $url): string {
        $bits = parse_url($url);
        if (!$bits || empty($bits['host']) || !in_array($bits['scheme'] ?? '', ['http', 'https'])) {
            return '';
        }
        $origin = $bits['scheme'] . '://' . strtolower($bits['host']);
        if (!empty($bits['port'])) {
            $origin .= ':' . $bits['port'];
        }
        return $origin;
    }

    /**
     * Columns for import and export.
     *
     * @return array
     */
    public static function get_keycolumns() {
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'type' => 'int', 'optional' => true, 'default' => 1,
            'dbname' => constants::TOTALMARKS];
        $keycols['int2'] = ['jsonname' => 'gradepolicy', 'type' => 'int', 'optional' => true,
            'default' => self::GRADE_IGNORE, 'dbname' => self::GRADEPOLICY];
        $keycols['text2'] = ['jsonname' => 'uilanguage', 'type' => 'string', 'optional' => true,
            'default' => self::UILANGUAGE_AUTO, 'dbname' => self::UILANGUAGE];
        $keycols[self::FILE] = ['jsonname' => self::FILE, 'type' => 'anonymousfile', 'optional' => true,
            'default' => null, 'dbname' => false];
        return $keycols;
    }

    /**
     * The widget's score shows as the item's score in the review, like any other item.
     *
     * @param \stdClass $result the step result
     * @param \stdClass $itemquizdata the item's quiz data
     */
    public function prepare_result(\stdClass $result, \stdClass $itemquizdata) {
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        $result->hasanswerdetails = false;
    }
}
