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
 * A widget item: an uploaded Poodll Widget Package (one HTML file, or a ZIP of HTML, CSS, JS and media) that
 * runs in a sandboxed iframe, and may report a score. Its files are unpacked into Moodle and served by widget.php
 * (see local\widgetstore).
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
    /** @var string Import only: the widget as HTML text, saved as the item's widget file. Not a database column. */
    public const WIDGETHTML = 'widgethtml';

    /** @var int Ignore the widget's score. */
    public const GRADE_IGNORE = 0;
    /** @var int Save the widget's score with the attempt, but do not count it in the lesson grade. */
    public const GRADE_RECORD = 1;
    /** @var int Count the widget's score in the lesson grade, scaled to the item's total marks. */
    public const GRADE_USE = 2;

    /** @var string The interface language setting that picks a language for each learner. */
    public const UILANGUAGE_AUTO = 'auto';

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
     * An import may give the widget as HTML text in "widgethtml" (see get_keycolumns()). It becomes the item's
     * widget file here, exactly as if a teacher had uploaded it.
     *
     * @return \stdClass result, as for the parent
     */
    public function update_insert_item() {
        $html = $this->itemrecord->{self::WIDGETHTML} ?? '';
        unset($this->itemrecord->{self::WIDGETHTML});
        if (is_string($html) && $html !== '' && empty($this->itemrecord->{self::FILE})) {
            $this->itemrecord->{self::FILE} = [self::html_filename($html) => base64_encode($html)];
        }
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
        // The widget as HTML text, for agents: base64 costs a model far more to write, and breaks more easily.
        // It is not a column: update_insert_item() saves it as the widget file, and the export writes an HTML
        // widget here (see export_item_adjust()).
        $keycols[self::WIDGETHTML] = ['jsonname' => self::WIDGETHTML, 'type' => 'string', 'optional' => true,
            'default' => '', 'dbname' => self::WIDGETHTML];
        return $keycols;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'Runs an interactive activity that you write yourself as one self-contained HTML page (HTML, CSS and '
            . 'JavaScript): a game, a simulation, a drag and drop or sorting task, an interactive diagram, anything '
            . 'the other item types can not express. It runs in a sandbox with no network and no storage, and it '
            . 'can report a score to the lesson. Prefer the native item types for what they already do (quizzes, '
            . 'gap fills, speaking, listening, TTS audio): they need no code and teachers can edit them in Moodle, '
            . 'while a widget can only be changed by editing its file. Read the authoring guide before you write one.';
    }

    /**
     * How to write a widget, for an agent that composes one for an import: the instructions teachers give
     * their AI chat (ai/widget-instructions.md), after a preface on how they apply to an import.
     *
     * @return string|null the guide as markdown, or null if the file is missing
     */
    public static function aigen_fetch_authoring_guide() {
        $guidefile = __DIR__ . '/../ai/widget-instructions.md';
        if (!is_readable($guidefile)) {
            return null;
        }
        $guide = file_get_contents($guidefile);
        if ($guide === false) {
            return null;
        }
        $preface = <<<'MD'
# Composing a Smart Frame item for an import

The guide below was written for a teacher's AI chat, where the teacher tries the widget in the chat's preview
and then downloads the file. When you compose a widget item for an import instead:

1. **Write the widget exactly as the guide says:** one self-contained HTML file that follows sections 2 to 8,
   with the manifest block (section 3) and the protocol code (section 6) in it from the start.
2. **Let the teacher try it first if you can.** If you can show an HTML preview (an HTML artifact or canvas),
   show it and make the changes they ask for before you import. Once imported, a teacher can only change a
   widget by downloading its file and editing it with an AI tool.
3. **Go through the export checklist (section 9)** and fix anything that fails.
4. **Put the whole HTML file, as text, in the item's `widgethtml` field.** Escape it as any JSON string
   (quotes, backslashes, newlines). Never base64 it, and do not use the payload's `files` object for it.
5. **Only HTML widgets can be imported.** Draw pictures as inline SVG, CSS or emoji. Do not put photos, audio
   or video in the file as `data:` URIs: they make it huge and slow to send. If the activity needs recordings,
   video or photos, tell the teacher it has to be a ZIP widget (section 10), which they upload on the item's
   form.
6. **Set the score fields:** for a widget that reports a score, set `gradepolicy` to 2 and `totalmarks` to the
   points it is worth in the lesson. For one with no score, leave `gradepolicy` at 0.
7. **To change a widget that is already in a lesson,** export the lesson's items: an HTML widget comes back
   with its page in `widgethtml`. Change it, keeping the manifest block and the protocol code, and import it.
   The import adds a new item, so tell the teacher to delete the old one.

The import checks the file the same way the upload form does, and refuses the item with the reason if
anything is wrong (no manifest, invalid JSON, not UTF-8, too big, ...). Fix the file and import again.

---


MD;
        return $preface . $guide;
    }

    /**
     * The agent-facing import field spec for widget items.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions']);
        $fields['type']['example'] = 'smartframe';

        $ownfields = [
            'gradepolicy' => [
                'description' => 'What the lesson does with the score the widget reports: 0 = ignore it (for a widget '
                    . 'with no score), 1 = save it with the attempt without counting it in the lesson grade, '
                    . '2 = count it in the lesson grade, scaled to totalmarks. Use 2 for a widget that reports a score.',
                'options' => [
                    ['value' => (string) self::GRADE_IGNORE, 'meaning' => 'Ignore the score (default)'],
                    ['value' => (string) self::GRADE_RECORD, 'meaning' => 'Save the score, but do not count it'],
                    ['value' => (string) self::GRADE_USE, 'meaning' => 'Count the score in the lesson grade'],
                ],
                'example' => '2',
            ],
            'totalmarks' => [
                'description' => 'The points the widget\'s score is worth in the lesson grade when gradepolicy is 2. '
                    . 'The widget reports earned/possible, and the item grade is that ratio times totalmarks.',
                'example' => '4',
            ],
            'uilanguage' => [
                'description' => 'The language of the widget\'s own interface (instructions, buttons, feedback), not '
                    . 'the language being taught. "auto" (the default) picks one of the widget\'s uiLanguages for each '
                    . 'learner: their native language, then the lesson\'s native language, then their Moodle language, '
                    . 'then the widget\'s defaultUiLanguage. Otherwise give one of the tags in the widget manifest\'s '
                    . 'uiLanguages, e.g. "ja".',
                'example' => 'auto',
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        $fields[self::WIDGETHTML] = static::aigen_seed_field_spec(self::WIDGETHTML, [
            'required' => true,
            'description' => 'The whole widget, written as the authoring guide says: one self-contained HTML file '
                . 'with its manifest block, as plain text in a JSON string (not base64). At most '
                . display_size(package::MAX_FILE_BYTES) . '. Required.',
            'example' => '<!doctype html><html lang="en"><head><meta charset="utf-8">...</html>',
        ]);

        return [
            'usage' => 'Compose one item per widget. Write the widget as the authoring guide says (one '
                . 'self-contained HTML file with its manifest block) and put the whole file, as text, in widgethtml. '
                . 'Do not base64 it and do not put it in the files object. The import checks it like the upload form '
                . 'does and refuses the item, with the reason, if it is not a valid widget. For a widget that '
                . 'reports a score set gradepolicy to 2 and totalmarks.',
            'fields' => array_values($fields),
            'fileareas' => [],
            'example' => [
                'items' => [
                    [
                        'type' => 'smartframe',
                        'name' => 'Māori greetings',
                        'instructions' => 'Choose the right greeting for each picture.',
                        'gradepolicy' => 2,
                        'totalmarks' => 4,
                        'uilanguage' => 'auto',
                        self::WIDGETHTML => "<!doctype html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n"
                            . "<script type=\"application/json\" id=\"poodll-widget-manifest\">{...}</script>\n"
                            . "... the rest of the widget ...\n</html>\n",
                    ],
                ],
            ],
        ];
    }

    /**
     * Open the widget of an import record (its widgethtml, or its one widget file), checking it as the upload
     * form does.
     *
     * @param \stdClass $newrecord the db-ready import record, with the payload's files attached as filearea arrays
     * @return package
     * @throws package_exception if there is not exactly one valid widget file
     */
    protected static function import_package($newrecord): package {
        $files = (array) ($newrecord->{self::FILE} ?? []);
        $html = (string) ($newrecord->{self::WIDGETHTML} ?? '');
        if ($html !== '') {
            if ($files) {
                throw new package_exception('error:importboth');
            }
            $path = make_request_directory() . '/widget.html';
            file_put_contents($path, $html);
            return new package($path, 'widget.html');
        }
        if (!$files) {
            throw new package_exception('error:importnofile');
        }
        if (count($files) > 1) {
            throw new package_exception('error:importonefile');
        }
        $filename = (string) array_key_first($files);
        if (!package::is_package_name($filename)) {
            throw new package_exception('error:importnofile');
        }
        $content = base64_decode((string) reset($files), true);
        if ($content === false || $content === '') {
            throw new package_exception('error:importbase64', s($filename));
        }
        $path = make_request_directory() . '/' . (package::is_html_name($filename) ? 'widget.html' : 'widget.zip');
        file_put_contents($path, $content);
        return new package($path, $filename);
    }

    /**
     * Refuse an imported widget item that would not work: a missing or invalid widget file, or a bad gradepolicy.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        $policy = $newrecord->{self::GRADEPOLICY} ?? self::GRADE_IGNORE;
        if (!in_array((int) $policy, [self::GRADE_IGNORE, self::GRADE_RECORD, self::GRADE_USE], true)) {
            $error->col = self::GRADEPOLICY;
            $error->message = get_string('error:importgradepolicy', 'minilessonitem_smartframe');
            return $error;
        }
        try {
            self::import_package($newrecord);
        } catch (package_exception $e) {
            $error->col = empty($newrecord->{self::FILE}) ? self::WIDGETHTML : self::FILE;
            $error->message = $e->getMessage();
            return $error;
        }
        return false;
    }

    /**
     * Warn when an imported item asks for an interface language its widget does not have.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return array warning messages
     */
    public static function import_warnings($newrecord, $cm) {
        $setting = (string) ($newrecord->{self::UILANGUAGE} ?? self::UILANGUAGE_AUTO);
        if ($setting === '' || $setting === self::UILANGUAGE_AUTO) {
            return [];
        }
        try {
            $offered = self::import_package($newrecord)->get_manifest()->uiLanguages ?? [];
        } catch (package_exception $e) {
            return [];
        }
        $tag = package::canonical_lang($setting);
        if ($tag && self::match_language($tag, $offered)) {
            return [];
        }
        $a = (object) ['lang' => s($setting), 'languages' => s(implode(', ', $offered))];
        return [get_string('warning:importuilanguage', 'minilessonitem_smartframe', $a)];
    }

    /**
     * A file name for a widget given as HTML text: its manifest id, if it makes a file name, else "widget".
     *
     * @param string $html the widget HTML
     * @return string e.g. "maori-greetings.html"
     */
    protected static function html_filename(string $html): string {
        try {
            $path = make_request_directory() . '/widget.html';
            file_put_contents($path, $html);
            $id = (string) ((new package($path, 'widget.html'))->get_manifest()->id ?? '');
        } catch (package_exception $e) {
            $id = '';
        }
        $name = trim(clean_param(substr($id, 0, 60), PARAM_FILE), '. ');
        return ($name !== '' ? $name : 'widget') . '.html';
    }

    /**
     * Export an HTML widget as text in widgethtml, the way agents are asked to import one, so an agent can read
     * and change it. A ZIP widget stays a base64 file. The stored revision and manifest are left out: the
     * import works them out again from the widget.
     *
     * @param \stdClass $itemobj the item's export object, with its files
     * @param \stdClass $itemrecord the item's record
     * @return \stdClass
     */
    public static function export_item_adjust(\stdClass $itemobj, \stdClass $itemrecord): \stdClass {
        $keycols = static::get_keycolumns();
        foreach ([self::REVISION, self::MANIFEST] as $dbname) {
            foreach ($keycols as $keycol) {
                if ($keycol['dbname'] === $dbname) {
                    unset($itemobj->{$keycol['jsonname']});
                }
            }
        }
        $files = $itemobj->files[self::FILE] ?? [];
        if (count($files) === 1 && package::is_html_name((string) array_key_first($files))) {
            $html = base64_decode((string) reset($files));
            if (is_string($html) && mb_check_encoding($html, 'UTF-8')) {
                $itemobj->{self::WIDGETHTML} = $html;
                unset($itemobj->files[self::FILE]);
            }
        }
        return $itemobj;
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
