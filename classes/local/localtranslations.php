<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_minilesson\local;

use mod_minilesson\aimanager;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Local translations: an item's native language text, translated ahead of time and stored on the item.
 *
 * Some text in an item is written in the learner's native language: a gap-fill hint, the translation
 * on a card. The author writes it in one language. This class keeps that text translated into the
 * languages the site has chosen, so that a learner who picked another native language sees it in theirs,
 * with no AI call at view time.
 *
 * The item type says what its translatable text is (get_local_translatables()) and where a translation
 * goes (apply_local_translations()). Everything else is here, and is the same for every item type.
 *
 * Translations are stored in the item's localtranslations column, keyed by a hash of the source text:
 * {"v": 1, "t": {"<md5>": {"src": "text", "lang": "en", "tr": {"ja-JP": "..."}}}, "tried": {"ja-JP": time}}
 * "lang" is the language the AI found the source text to be in, and "keep": true marks text in the
 * language being taught, which is never translated.
 * Keying by the text rather than by position means a lookup needs no sentence index, reordering lines
 * costs nothing, and the translations stay right wherever the item is copied to.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class localtranslations {
    /** @var int version of the stored json */
    const VERSION = 1;

    /** @var int languages asked for in one request, so that a long reply is not cut off */
    const LANGS_PER_REQUEST = 10;

    /** @var int how long before a language that came back empty is asked for again from a page view */
    const RETRY_AFTER = DAYSECS;

    /**
     * The writing system a language uses, as a PCRE script property, for those not in Latin.
     * Keyed by base language. An answer must hold at least one character of its own script.
     */
    const LANG_SCRIPTS = [
        'am' => '\p{Ethiopic}',
        'ar' => '\p{Arabic}',
        'bg' => '\p{Cyrillic}',
        'bn' => '\p{Bengali}',
        'el' => '\p{Greek}',
        'fa' => '\p{Arabic}',
        'he' => '\p{Hebrew}',
        'hi' => '\p{Devanagari}',
        'ja' => '\p{Hiragana}\p{Katakana}\p{Han}',
        'ka' => '\p{Georgian}',
        'ko' => '\p{Hangul}\p{Han}',
        'mk' => '\p{Cyrillic}',
        'ps' => '\p{Arabic}',
        'ru' => '\p{Cyrillic}',
        'sr' => '\p{Cyrillic}\p{Latin}',
        'ta' => '\p{Tamil}',
        'te' => '\p{Telugu}',
        'th' => '\p{Thai}',
        'ti' => '\p{Ethiopic}',
        'uk' => '\p{Cyrillic}',
        'ur' => '\p{Arabic}',
        'zh' => '\p{Han}',
    ];

    /**
     * Is the feature on for the site?
     *
     * @return bool
     */
    public static function is_site_enabled() {
        return !empty(get_config(constants::M_COMPONENT, 'enablelocaltranslations'));
    }

    /**
     * Is the feature on for this activity? It follows the activity's native language features setting.
     *
     * @param \stdClass $moduleinstance
     * @return bool
     */
    public static function is_enabled($moduleinstance) {
        return self::is_site_enabled() && !empty($moduleinstance->nativetranslation);
    }

    /**
     * The languages the site makes translations in.
     *
     * @return string[] native language option codes
     */
    public static function get_site_langs() {
        $setting = (string) get_config(constants::M_COMPONENT, 'localtranslationlangs');
        $langs = [];
        foreach (explode(',', $setting) as $code) {
            $code = self::normalise_lang($code);
            if ($code !== '') {
                $langs[$code] = $code;
            }
        }
        return array_values($langs);
    }

    /**
     * The languages to make translations in for one activity.
     *
     * Only the language being taught is left out, because a translation into it is no help. The activity's
     * native language stays in: the text is not always written in it (an item imported from a lesson for
     * Japanese speakers into one for Ukrainian speakers keeps its Japanese hints), and text that is already
     * written in a language needs no translation into it anyway (see is_missing()).
     *
     * @param \stdClass $moduleinstance
     * @return string[]
     */
    public static function get_activity_langs($moduleinstance) {
        $target = self::base_lang($moduleinstance->ttslanguage);
        return array_values(array_filter(self::get_site_langs(), function ($lang) use ($target) {
            return self::base_lang($lang) !== $target;
        }));
    }

    /**
     * Turn any language code we meet into one of the native language option codes.
     *
     * Activities hold locale codes (ja-JP) and sometimes bare ones (en). The native language user
     * preference is shared with mod_wordcards, which writes its own codes (ja, rus, zh_tw).
     *
     * @param string|null $code
     * @return string the option code, or '' if there is no such language
     */
    public static function normalise_lang($code) {
        $code = trim((string) $code);
        if ($code === '' || $code === '0') {
            return '';
        }
        $options = array_keys(self::get_lang_names());
        if (in_array($code, $options, true)) {
            return $code;
        }
        $flat = function ($c) {
            return strtolower(str_replace('_', '-', $c));
        };
        foreach ($options as $option) {
            if ($flat($option) === $flat($code)) {
                return $option;
            }
        }
        $locale = utils::lang_to_locale($code);
        if (in_array($locale, $options, true)) {
            return $locale;
        }
        // Traditional Chinese is another script from the Simplified of zh-CN, so it does not fall back to it.
        if (preg_match('/^zh[-_](tw|hk|mo|hant)/i', $code)) {
            return '';
        }
        $base = self::base_lang($code);
        foreach ($options as $option) {
            if (self::base_lang($option) === $base) {
                return $option;
            }
        }
        return '';
    }

    /**
     * The language part of a code: ja-JP and ja are both ja.
     *
     * @param string|null $code
     * @return string
     */
    public static function base_lang($code) {
        $base = strtolower(preg_split('/[-_]/', trim((string) $code))[0]);
        return $base === 'rus' ? 'ru' : $base;
    }

    /**
     * Native language option code => English name, for prompts.
     *
     * A bare code is ambiguous to a model (so, ti), so prompts name each language. The names are in
     * English whatever the site's language, because the prompt is in English.
     *
     * @return array
     */
    public static function get_lang_names() {
        static $names = null;
        if ($names === null) {
            $names = [];
            $sm = get_string_manager();
            foreach (utils::get_nativelang_options() as $code => $label) {
                $key = strtolower($code);
                $names[$code] = $sm->string_exists($key, constants::M_COMPONENT)
                    ? $sm->get_string($key, constants::M_COMPONENT, null, 'en')
                    : $label;
            }
        }
        return $names;
    }

    /**
     * The English name of a language, for a prompt.
     *
     * @param string $code
     * @return string
     */
    public static function lang_name($code) {
        $names = self::get_lang_names();
        $option = self::normalise_lang($code);
        return $names[$option] ?? (string) $code;
    }

    /**
     * The hash a piece of source text is stored under.
     *
     * @param string $text
     * @return string
     */
    public static function hash_text($text) {
        return md5(utils::super_trim((string) $text));
    }

    /**
     * Read the stored json, always giving back the full shape.
     *
     * @param string|null $json
     * @return array
     */
    public static function decode($json) {
        $data = !empty($json) ? json_decode($json, true) : null;
        if (!is_array($data) || ($data['v'] ?? 0) != self::VERSION) {
            $data = [];
        }
        return [
            'v' => self::VERSION,
            't' => isset($data['t']) && is_array($data['t']) ? $data['t'] : [],
            'tried' => isset($data['tried']) && is_array($data['tried']) ? $data['tried'] : [],
        ];
    }

    /**
     * Write the json to store, or null when there is nothing in it.
     *
     * @param array $data
     * @return string|null
     */
    public static function encode(array $data) {
        if (empty($data['t'])) {
            return null;
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The item's translatable text, keyed by hash.
     *
     * @param \stdClass $itemrecord the item's row
     * @return array hash => ['text' => string, 'context' => string]
     */
    public static function get_translatables($itemrecord) {
        $itemclass = utils::fetch_itemtype_classname($itemrecord->type);
        if (!$itemclass) {
            return [];
        }
        $translatables = [];
        foreach ($itemclass::get_local_translatables($itemrecord) as $translatable) {
            $text = utils::super_trim((string) ($translatable['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $hash = self::hash_text($text);
            if (!isset($translatables[$hash])) {
                $context = utils::super_trim((string) ($translatable['context'] ?? ''));
                $translatables[$hash] = ['text' => $text, 'context' => $context];
            }
        }
        return $translatables;
    }

    /**
     * After an item is saved: drop translations of text that is gone, and queue translations of new text.
     *
     * @param int $itemid
     */
    public static function item_saved($itemid) {
        global $DB, $USER;

        if (!self::is_site_enabled()) {
            return;
        }
        $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid]);
        if (!$item) {
            return;
        }
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $item->minilesson]);
        if (!$moduleinstance || !self::is_enabled($moduleinstance)) {
            return;
        }

        $translatables = self::get_translatables($item);
        $data = self::decode($item->localtranslations ?? null);
        $before = self::encode($data);
        $data = self::rekey($data, $translatables);
        $data['t'] = array_intersect_key($data['t'], $translatables);
        if (self::encode($data) !== $before) {
            $DB->set_field(constants::M_QTABLE, 'localtranslations', self::encode($data), ['id' => $item->id]);
        }

        if (self::get_missing_langs($data, $translatables, self::get_activity_langs($moduleinstance))) {
            \mod_minilesson\task\generate_local_translations::queue_for_item($item->id, $USER->id);
        }
    }

    /**
     * Keep the translations of text that was replaced by one of its own translations.
     *
     * When a lesson is moved to another native language (translate and import, or a teacher pasting in a
     * stored translation) a hint changes from, say, the Japanese "ノート" to the Spanish "cuaderno", which
     * was already its stored es-ES translation. Rather than drop the entry and translate everything again,
     * the entry moves to the new text: Spanish becomes the source, and the old Japanese text becomes its
     * Japanese translation (when the old entry knows what language it was in).
     *
     * @param array $data the decoded json
     * @param array $translatables from get_translatables(), the item's text as it is now
     * @return array the decoded json, with matched entries moved to their new hash
     */
    protected static function rekey(array $data, array $translatables) {
        $gone = array_diff_key($data['t'], $translatables);
        if (!$gone) {
            return $data;
        }
        $normalise = function ($text) {
            return \core_text::strtolower(utils::super_trim((string) $text));
        };
        foreach ($translatables as $hash => $translatable) {
            if (isset($data['t'][$hash])) {
                continue;
            }
            $text = $normalise($translatable['text']);
            foreach ($gone as $oldhash => $entry) {
                if (!empty($entry['keep'])) {
                    continue;
                }
                foreach ($entry['tr'] ?? [] as $lang => $translation) {
                    if ($normalise($translation) !== $text) {
                        continue;
                    }
                    $tr = $entry['tr'];
                    unset($tr[$lang]);
                    $oldlang = self::normalise_lang($entry['lang'] ?? '');
                    if ($oldlang !== '' && !isset($tr[$oldlang])) {
                        $tr[$oldlang] = $entry['src'];
                    }
                    $data['t'][$hash] = ['src' => $translatable['text'], 'lang' => self::base_lang($lang), 'tr' => $tr];
                    unset($data['t'][$oldhash], $gone[$oldhash]);
                    continue 3;
                }
            }
        }
        return $data;
    }

    /**
     * The translations as they go into an export: what is stored, without what only means something on
     * this site (when each language was last tried).
     *
     * @param string|null $json the stored json
     * @return array|null
     */
    public static function export_data($json) {
        $data = self::decode($json);
        if (empty($data['t'])) {
            return null;
        }
        return ['v' => $data['v'], 't' => $data['t']];
    }

    /**
     * Turn translations from an import into json to store, keeping only what is well formed.
     *
     * An import file may come from another site or from an AI, so every entry is checked: hashes are made
     * again from the source text, language codes must be ones we know, translations lose any markup, and
     * a translation in the wrong script is dropped.
     *
     * @param mixed $value the "localtranslations" of an imported item: decoded json, or a json string
     * @return string|null json to store, or null if nothing usable was there
     */
    public static function sanitise($value) {
        $value = is_string($value) ? json_decode($value, true) : json_decode(json_encode($value), true);
        if (!is_array($value) || ($value['v'] ?? 0) != self::VERSION || !is_array($value['t'] ?? null)) {
            return null;
        }
        $data = ['v' => self::VERSION, 't' => [], 'tried' => []];
        foreach ($value['t'] as $entry) {
            if (!is_array($entry) || !is_string($entry['src'] ?? null)) {
                continue;
            }
            $src = utils::super_trim($entry['src']);
            if ($src === '') {
                continue;
            }
            $clean = ['src' => $src];
            if (is_string($entry['lang'] ?? null) && preg_match('/^[a-z]{2,3}$/', $entry['lang'])) {
                $clean['lang'] = $entry['lang'];
            }
            if (!empty($entry['keep'])) {
                $clean['keep'] = true;
            }
            $clean['tr'] = [];
            foreach ((array) ($entry['tr'] ?? []) as $lang => $translation) {
                $lang = self::normalise_lang($lang);
                if ($lang === '' || !is_string($translation)) {
                    continue;
                }
                $translation = utils::super_trim(strip_tags($translation));
                if ($translation !== '' && !self::is_wrong_script($lang, $translation)) {
                    $clean['tr'][$lang] = $translation;
                }
            }
            $data['t'][self::hash_text($src)] = $clean;
        }
        return self::encode($data);
    }

    /**
     * Source text => its stored translation into one language, from the items of an export.
     *
     * Given to the AI that translates a whole lesson, so that it uses the translations the items already
     * hold. Its output then matches them, and rekey() keeps every other language's translations.
     *
     * @param array $items items of an export, each possibly holding "localtranslations"
     * @param string $tolang the language the lesson is being translated into
     * @return array source text => translation
     */
    public static function get_glossary(array $items, $tolang) {
        $tolang = self::normalise_lang($tolang);
        $glossary = [];
        if ($tolang === '') {
            return $glossary;
        }
        foreach ($items as $item) {
            $data = json_decode(json_encode(is_object($item) ? ($item->localtranslations ?? null)
                : ($item['localtranslations'] ?? null)), true);
            foreach ((is_array($data) && is_array($data['t'] ?? null)) ? $data['t'] : [] as $entry) {
                if (!is_array($entry) || !is_string($entry['src'] ?? null) || !empty($entry['keep'])) {
                    continue;
                }
                $translation = self::find_translation((array) ($entry['tr'] ?? []), $tolang);
                if (is_string($translation)) {
                    $glossary[$entry['src']] = $translation;
                }
            }
        }
        return $glossary;
    }

    /**
     * Does this item type have native language text for local translations?
     *
     * @param string $type the item type
     * @return bool true if its itemtype class overrides get_local_translatables()
     */
    public static function item_type_has_translatables($type) {
        $itemclass = utils::fetch_itemtype_classname($type);
        if (!$itemclass) {
            return false;
        }
        $method = new \ReflectionMethod($itemclass, 'get_local_translatables');
        return $method->getDeclaringClass()->getName() !== \mod_minilesson\local\itemtype\item::class;
    }

    /**
     * The item's text and its stored translations, for the item form: one block per language, since a
     * teacher checks the languages they can read, and a column per language does not fit twenty of them.
     *
     * @param \stdClass $itemrecord the item's row
     * @param \stdClass $moduleinstance
     * @return array context for the mod_minilesson/localtranslations_table template
     */
    public static function get_table_data($itemrecord, $moduleinstance) {
        $langs = self::get_activity_langs($moduleinstance);
        $names = utils::get_nativelang_options();
        $translatables = self::get_translatables($itemrecord);
        $data = self::decode($itemrecord->localtranslations ?? null);

        $kept = [];
        $translated = [];
        foreach ($translatables as $hash => $translatable) {
            if (!empty($data['t'][$hash]['keep'])) {
                $kept[] = ['text' => $translatable['text']];
            } else {
                $translated[$hash] = $translatable;
            }
        }

        $langlist = [];
        $hasmissing = false;
        foreach ($langs as $lang) {
            $rows = [];
            $made = 0;
            foreach ($translated as $hash => $translatable) {
                $entry = $data['t'][$hash] ?? [];
                // Text already written in this language is shown as it is.
                $samelang = !empty($entry['lang']) && $entry['lang'] === self::base_lang($lang);
                $text = $samelang ? $translatable['text'] : self::find_translation($entry['tr'] ?? [], $lang);
                if ($text !== null) {
                    $made++;
                }
                $rows[] = ['source' => $translatable['text'], 'text' => (string) $text, 'missing' => $text === null];
            }
            $hasmissing = $hasmissing || $made < count($rows);
            $langlist[] = [
                'code' => $lang,
                'name' => $names[$lang] ?? $lang,
                'dir' => utils::get_text_direction($lang),
                'made' => $made,
                'total' => count($rows),
                'complete' => $made === count($rows),
                'rows' => $rows,
            ];
        }
        return [
            'haslangs' => !empty($langs),
            'hasrows' => !empty($translatables),
            'hastranslated' => !empty($translated),
            'kept' => $kept,
            'haskept' => !empty($kept),
            'langs' => $langlist,
            'hasmissing' => $hasmissing,
        ];
    }

    /**
     * The languages that some translatable text has no translation in yet.
     *
     * @param array $data the decoded json
     * @param array $translatables from get_translatables()
     * @param string[] $langs
     * @return string[]
     */
    public static function get_missing_langs(array $data, array $translatables, array $langs) {
        $missing = [];
        foreach ($langs as $lang) {
            foreach (array_keys($translatables) as $hash) {
                if (self::is_missing($data, $hash, $lang)) {
                    $missing[] = $lang;
                    break;
                }
            }
        }
        return $missing;
    }

    /**
     * Does this text still need a translation in this language?
     *
     * Text in the language being taught is marked "keep" and never needs one: in a matching game a pair
     * like "hat|hit", or a synonym, is the exercise itself, and translating it would break the item.
     * Nor does text into the language it is written in, as after a lesson is moved to another native language.
     *
     * @param array $data the decoded json
     * @param string $hash
     * @param string $lang
     * @return bool
     */
    protected static function is_missing(array $data, $hash, $lang) {
        if (!empty($data['t'][$hash]['keep'])) {
            return false;
        }
        // Text already written in the language needs no translation into it.
        if (!empty($data['t'][$hash]['lang']) && $data['t'][$hash]['lang'] === self::base_lang($lang)) {
            return false;
        }
        return self::find_translation($data['t'][$hash]['tr'] ?? [], $lang) === null;
    }

    /**
     * Put what translate() made into the json, keeping any translation already there.
     *
     * @param array $data the decoded json
     * @param array $translatables from get_translatables(), for the source text of new entries
     * @param array $made from translate()
     * @return int the number of translations added
     */
    protected static function add_translations(array &$data, array $translatables, array $made) {
        $count = 0;
        foreach (['tr', 'keep'] as $part) {
            foreach (array_keys($made[$part]) as $hash) {
                if (isset($translatables[$hash]) && !isset($data['t'][$hash])) {
                    $data['t'][$hash] = ['src' => $translatables[$hash]['text'], 'tr' => []];
                }
            }
        }
        foreach ($made['keep'] as $hash => $unused) {
            if (isset($data['t'][$hash])) {
                $data['t'][$hash]['keep'] = true;
            }
        }
        foreach ($made['lang'] as $hash => $code) {
            if (isset($data['t'][$hash]) && empty($data['t'][$hash]['lang'])) {
                $data['t'][$hash]['lang'] = $code;
            }
        }
        foreach ($made['tr'] as $hash => $translations) {
            if (!isset($data['t'][$hash])) {
                continue;
            }
            foreach ($translations as $lang => $translation) {
                if (!isset($data['t'][$hash]['tr'][$lang])) {
                    $data['t'][$hash]['tr'][$lang] = $translation;
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * The translation for a language, or one for the same base language (en-GB finds en-US).
     *
     * @param array $translations language code => translation
     * @param string $lang
     * @return string|null
     */
    public static function find_translation(array $translations, $lang) {
        if (isset($translations[$lang]) && $translations[$lang] !== '') {
            return $translations[$lang];
        }
        $base = self::base_lang($lang);
        foreach ($translations as $code => $translation) {
            if ($translation !== '' && self::base_lang($code) === $base) {
                return $translation;
            }
        }
        return null;
    }

    /**
     * What an item needs at view time to show its text in the learner's language.
     *
     * The learner's own native language is used if translations are made in it. If not, the activity's
     * native language is, since the lesson says that is what its learners speak.
     *
     * When a translation the learner should see is missing, a fill is queued for the item, at most once
     * a day per language, and the learner sees the original text meanwhile.
     *
     * @param \stdClass $itemrecord the item's row
     * @param \stdClass $moduleinstance
     * @param string $learnerlang the learner's native language
     * @return array|null [callable text => ?translation, language code], or null if nothing is to change
     */
    public static function get_lookup($itemrecord, $moduleinstance, $learnerlang) {
        if (!self::is_enabled($moduleinstance) || empty($itemrecord->id)) {
            return null;
        }
        // The first of these that translations are made in for this activity.
        $activitybases = array_map([self::class, 'base_lang'], self::get_activity_langs($moduleinstance));
        $lang = '';
        foreach ([$learnerlang, $moduleinstance->nativelang ?? ''] as $candidate) {
            $candidate = self::normalise_lang($candidate);
            if ($candidate !== '' && in_array(self::base_lang($candidate), $activitybases, true)) {
                $lang = $candidate;
                break;
            }
        }
        if ($lang === '') {
            return null;
        }

        $translatables = self::get_translatables($itemrecord);
        if (!$translatables) {
            return null;
        }
        $data = self::decode($itemrecord->localtranslations ?? null);
        if (self::get_missing_langs($data, $translatables, [$lang])) {
            $tried = (int) ($data['tried'][$lang] ?? 0);
            if ($tried < time() - self::RETRY_AFTER) {
                $userid = !empty($itemrecord->modifiedby) ? $itemrecord->modifiedby : ($itemrecord->createdby ?? 0);
                \mod_minilesson\task\generate_local_translations::queue_for_item($itemrecord->id, $userid);
            }
        }

        $lookup = function ($text) use ($data, $lang) {
            $hash = self::hash_text($text);
            if (!empty($data['t'][$hash]['keep'])) {
                return null;
            }
            return self::find_translation($data['t'][$hash]['tr'] ?? [], $lang);
        };
        return [$lookup, $lang];
    }

    /**
     * Make the item's missing translations, and store them.
     *
     * @param int $itemid
     * @return string what was done, for the task log
     */
    public static function generate_for_item($itemid) {
        global $DB;

        $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid]);
        if (!$item) {
            return 'item no longer exists';
        }
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $item->minilesson]);
        if (!$moduleinstance || !self::is_enabled($moduleinstance)) {
            return 'local translations are off';
        }
        $translatables = self::get_translatables($item);
        if (!$translatables) {
            return 'no translatable text';
        }
        $data = self::decode($item->localtranslations);
        $langs = self::get_missing_langs($data, $translatables, self::get_activity_langs($moduleinstance));
        if (!$langs) {
            return 'nothing missing';
        }

        $cm = get_coursemodule_from_instance(constants::M_MODNAME, $moduleinstance->id, $moduleinstance->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $aimanager = new aimanager($context->id, $moduleinstance->region, $moduleinstance->ttslanguage);

        // A model sometimes leaves a piece out of a long reply, so whatever is still missing after the
        // first pass is asked for once more, on its own.
        $working = $data;
        $made = ['tr' => [], 'keep' => [], 'lang' => []];
        for ($pass = 1; $pass <= 2; $pass++) {
            $passlangs = self::get_missing_langs($working, $translatables, $langs);
            if (!$passlangs) {
                break;
            }
            foreach (array_chunk($passlangs, self::LANGS_PER_REQUEST) as $chunk) {
                // Ask only for the text that is missing in at least one of the languages.
                $wanted = [];
                foreach ($translatables as $hash => $translatable) {
                    foreach ($chunk as $lang) {
                        if (self::is_missing($working, $hash, $lang)) {
                            $wanted[$hash] = $translatable;
                            break;
                        }
                    }
                }
                $result = self::translate($aimanager, $moduleinstance, $wanted, $chunk);
                self::add_translations($working, $translatables, $result);
                foreach ($result['tr'] as $hash => $translations) {
                    $made['tr'][$hash] = ($made['tr'][$hash] ?? []) + $translations;
                }
                $made['keep'] += $result['keep'];
                $made['lang'] += $result['lang'];
            }
        }

        // The item may have been edited while the AI was working, so merge into the row as it is now.
        // A translation already stored stays: it may have been made or fixed since this run began.
        $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid]);
        if (!$item) {
            return 'item deleted while translating';
        }
        $translatables = self::get_translatables($item);
        $data = self::decode($item->localtranslations);
        $data['t'] = array_intersect_key($data['t'], $translatables);
        $count = self::add_translations($data, $translatables, $made);
        foreach ($langs as $lang) {
            $data['tried'][$lang] = time();
        }
        $DB->set_field(constants::M_QTABLE, 'localtranslations', self::encode($data), ['id' => $item->id]);
        return $count . ' translation(s) made in ' . implode(', ', $langs) . ', '
            . count($made['keep']) . ' piece(s) kept as written';
    }

    /**
     * One request: translate some pieces of text into some languages.
     *
     * Each piece goes with its context, the text in the lesson language it belongs to, so that the AI can
     * tell which meaning of a word is meant. The source language is only a hint: authors do not always
     * write in the activity's native language, so the AI translates from whatever language the text is in.
     *
     * @param aimanager $aimanager
     * @param \stdClass $moduleinstance
     * @param array $translatables hash => ['text', 'context']
     * @param string[] $langs
     * @return array ['tr' => hash => [language code => translation], 'keep' => hash => true,
     *                'lang' => hash => the ISO 639-1 code of the language the text is in]
     */
    protected static function translate(aimanager $aimanager, $moduleinstance, array $translatables, array $langs) {
        $results = ['tr' => [], 'keep' => [], 'lang' => []];
        if (!$translatables || !$langs) {
            return $results;
        }
        $targetname = self::lang_name($moduleinstance->ttslanguage);
        $sourcelang = !empty($moduleinstance->nativelang)
            && self::base_lang($moduleinstance->nativelang) !== self::base_lang($moduleinstance->ttslanguage)
            ? $moduleinstance->nativelang : $moduleinstance->ttslanguage;
        $sourcename = self::lang_name($sourcelang);

        // Short ids rather than hashes: fewer tokens, and nothing for the model to mistype.
        $ids = [];
        $pieces = [];
        foreach ($translatables as $hash => $translatable) {
            $id = (string) (count($ids) + 1);
            $ids[$id] = $hash;
            $piece = ['id' => $id, 'text' => $translatable['text']];
            if ($translatable['context'] !== '') {
                $piece['context'] = $translatable['context'];
            }
            $pieces[] = $piece;
        }
        $described = [];
        foreach ($langs as $lang) {
            $described[] = $lang . ' (' . self::lang_name($lang) . ')';
        }

        $prompt = "You are translating short pieces of text in a $targetname language learning activity."
            . " The pieces are hints, translations or definitions shown to the learner. Most are written in"
            . " $sourcename, but authors do not always write in the language they are expected to." . PHP_EOL;
        $prompt .= "For each piece, first decide which language its text is actually written in, and give that as"
            . " a two letter ISO 639-1 code." . PHP_EOL;
        $prompt .= "If the text is written in $targetname, the language being learned, it is part of the exercise"
            . " (for example a matching word, a synonym or a definition in $targetname): give no translations"
            . " for it." . PHP_EOL;
        $prompt .= "Otherwise translate the text into each of these languages: " . implode(', ', $described)
            . "." . PHP_EOL;
        $prompt .= "A piece may have a context: the $targetname text it belongs to. Use the context only to"
            . " decide which meaning is intended. Do not translate the context." . PHP_EOL;
        $prompt .= "Keep each translation as short and simple as the original: a word stays a word or a short"
            . " phrase. Use each language's own script and do not transliterate. If you cannot translate a piece"
            . " reliably into a language, leave that language out rather than guess." . PHP_EOL;
        $prompt .= 'Reply with a JSON object of the format {"pieces": {"<id>": {"language": "<code>",'
            . ' "translations": {"<language code>": "translation"}}}}, using the ids and language codes exactly'
            . ' as given, and no other comment.' . PHP_EOL;
        $prompt .= json_encode($pieces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ret = $aimanager->generate_structured_content($prompt);
        if (empty($ret->success) || !is_object($ret->payload)) {
            return $results;
        }
        $reply = json_decode(json_encode($ret->payload), true);
        $targetbase = self::base_lang($moduleinstance->ttslanguage);
        foreach ((array) ($reply['pieces'] ?? []) as $id => $piece) {
            $id = (string) $id;
            if (!isset($ids[$id]) || !is_array($piece)) {
                continue;
            }
            if (is_string($piece['language'] ?? null) && preg_match('/^[a-z]{2,3}$/', strtolower($piece['language']))) {
                $results['lang'][$ids[$id]] = strtolower($piece['language']);
            }
            // Text in the language being taught is kept as written. The model names the language and the
            // decision is made here, because asked to decide for itself it would translate such text anyway.
            if (is_string($piece['language'] ?? null) && self::base_lang($piece['language']) === $targetbase) {
                $results['keep'][$ids[$id]] = true;
                continue;
            }
            foreach ((array) ($piece['translations'] ?? []) as $lang => $translation) {
                if (!in_array($lang, $langs, true) || !is_string($translation)) {
                    continue;
                }
                // Some item types put hints into the page as HTML, so markup from the AI is not kept.
                $translation = utils::super_trim(strip_tags($translation));
                // Leaving out an answer in the wrong script lets a later run try again, where storing it
                // shows a learner something they cannot read.
                if ($translation === '' || self::is_wrong_script($lang, $translation)) {
                    continue;
                }
                $results['tr'][$ids[$id]][$lang] = $translation;
            }
        }
        return $results;
    }

    /**
     * Is this text written in the wrong script for its language?
     *
     * The same test mod_wordcards applies to its translations. Text with no letters is left alone, since
     * a numeral is a fine translation of a number.
     *
     * @param string $lang
     * @param string $text
     * @return bool true if it should not be stored
     */
    public static function is_wrong_script($lang, $text) {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        $hasletter = preg_match('/\p{L}/u', $text);
        if (!$hasletter) {
            return false;
        }
        $base = self::base_lang($lang);
        if (isset(self::LANG_SCRIPTS[$base])) {
            $inscript = preg_match('/[' . self::LANG_SCRIPTS[$base] . ']/u', $text);
            return $inscript === false ? false : !$inscript;
        }
        // A language written in Latin script. Any letter that is not Latin is another language's word.
        $foreign = preg_match('/(?!\p{Latin})\p{L}/u', $text);
        return $foreign === false ? false : (bool) $foreign;
    }
}
