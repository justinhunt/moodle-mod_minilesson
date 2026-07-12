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

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_minilesson\aimanager;
use mod_minilesson\constants;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Translates a single message from a lesson item into the student's native language.
 *
 * Used as the server side fallback for mod_minilesson/translate when the browser
 * has no native translation API. The languages are derived server side from the
 * activity settings (and the user's native language preference), not from the client.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class translate_message extends external_api {
    /** @var int Longest message we will send for translation. */
    const MAX_TEXT_LENGTH = 2000;

    /** @var string[] Item types allowed to request translations. */
    const ALLOWED_ITEMTYPES = [constants::TYPE_FICTION];

    /**
     * Describes the parameters for the translate_message web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
            'itemid' => new external_value(PARAM_INT, 'The minilesson item id'),
            'text' => new external_value(PARAM_TEXT, 'The text to translate'),
        ]);
    }

    /**
     * Translates a message from a lesson item into the student's native language.
     *
     * @param int $cmid The course module id.
     * @param int $itemid The minilesson item id.
     * @param string $text The text to translate.
     * @return array success flag and the translated text.
     */
    public static function execute($cmid, $itemid, $text) {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'itemid' => $itemid, 'text' => $text]
        );

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/minilesson:view', $context);

        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        // The item must belong to this activity and be of a type that offers in-lesson translation.
        $item = $DB->get_record(
            constants::M_QTABLE,
            ['id' => $params['itemid'], constants::M_MODNAME => $moduleinstance->id],
            '*',
            MUST_EXIST
        );
        if (!in_array($item->type, self::ALLOWED_ITEMTYPES)) {
            throw new \invalid_parameter_exception('Item type does not support translation');
        }

        $failure = ['success' => false, 'translation' => ''];

        $text = trim($params['text']);
        if ($text === '' || \core_text::strlen($text) > self::MAX_TEXT_LENGTH) {
            return $failure;
        }

        // Languages come from the activity settings, as in the fiction itemtype's export_for_template.
        $fromlang = $moduleinstance->ttslanguage;
        $tolang = $moduleinstance->nativelang;
        if (get_config(constants::M_COMPONENT, 'setnativelanguage')) {
            $userprefnativelanguage = get_user_preferences(constants::NATIVELANG_PREF);
            if (!empty($userprefnativelanguage)) {
                $tolang = $userprefnativelanguage;
            }
        }
        if (empty($fromlang) || empty($tolang)) {
            return $failure;
        }

        $aimanager = new aimanager($context->id, $moduleinstance->region, $moduleinstance->ttslanguage);
        $translation = $aimanager->translate_text($text, $fromlang, $tolang);
        if ($translation === false) {
            return $failure;
        }

        return ['success' => true, 'translation' => $translation];
    }

    /**
     * Describes the return value of the translate_message web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'whether the translation succeeded'),
            'translation' => new external_value(PARAM_RAW, 'the translated text'),
        ]);
    }
}
