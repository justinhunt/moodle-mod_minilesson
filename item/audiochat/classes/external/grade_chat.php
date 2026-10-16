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

namespace minilessonitem_audiochat\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\aimanager;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Grades a finished audio chat from its transcript.
 *
 * Used by the Gemini driver (Gemini and Cloud Poodll providers). The Gemini Live models
 * can only reply in audio, so a grade asked for inside the live session comes back as
 * a transcript of spoken JSON, which loses its structure. So the transcript is graded
 * by a text model on Cloud Poodll instead.
 *
 * The prompt is built here from the item, never taken from the browser, so the call
 * cannot be used to put arbitrary prompts to the AI.
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_chat extends external_api {
    /** @var int Most transcript items we will accept. */
    const MAX_ITEMS = 300;

    /** @var int Longest transcript, in characters, we will send for grading. */
    const MAX_TRANSCRIPT_LENGTH = 40000;

    /** @var int How many times to ask Cloud Poodll before giving up. */
    const ATTEMPTS = 2;

    /**
     * Describes the parameters for the grade_chat web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
            'itemid' => new external_value(PARAM_INT, 'The minilesson item id'),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'user or assistant'),
                    'text' => new external_value(PARAM_RAW, 'what was said'),
                ]),
                'the chat transcript, oldest first'
            ),
        ]);
    }

    /**
     * Grades a finished audio chat.
     *
     * @param int $cmid
     * @param int $itemid
     * @param array $items
     * @return array
     */
    public static function execute($cmid, $itemid, $items) {
        global $DB;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'itemid' => $itemid, 'items' => $items]
        );

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/minilesson:view', $context);

        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $item = $DB->get_record(
            constants::M_QTABLE,
            ['id' => $params['itemid'], constants::M_MODNAME => $moduleinstance->id, 'type' => 'audiochat'],
            '*',
            MUST_EXIST
        );

        $failure = ['success' => false, 'score' => 0, 'gradeexplanation' => '', 'feedback' => ''];

        if (count($params['items']) > self::MAX_ITEMS) {
            return $failure;
        }
        $transcript = self::build_transcript($params['items']);
        if ($transcript === '' || \core_text::strlen($transcript) > self::MAX_TRANSCRIPT_LENGTH) {
            return $failure;
        }

        /** @var \minilessonitem_audiochat\itemtype $itemtype */
        $itemtype = utils::fetch_item_from_itemrecord($item, $moduleinstance, $context);
        $criteria = $itemtype->fetch_grade_criteria();
        if ($criteria === '') {
            return $failure;
        }

        $instructions = [
            'criteria' => $criteria,
            'feedbacklanguage' => $itemtype->fetch_student_native_language(),
            'targetlanguage' => $moduleinstance->ttslanguage,
        ];
        $cpparams = [
            'action' => 'grade_chat',
            'subject' => $transcript,
            'prompt' => json_encode($instructions),
            'language' => $moduleinstance->ttslanguage,
            'region' => $moduleinstance->region,
        ];

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $grade = self::parse_grade(aimanager::call_cp_api($cpparams));
            if ($grade) {
                return ['success' => true] + $grade;
            }
        }
        return $failure;
    }

    /**
     * Turn the transcript items into labelled lines, one per turn.
     *
     * @param array $items the transcript items
     * @return string
     */
    protected static function build_transcript(array $items): string {
        $lines = [];
        foreach ($items as $item) {
            $text = trim(preg_replace('/\s+/u', ' ', $item['text']));
            if ($text === '') {
                continue;
            }
            $label = $item['role'] === 'user' ? 'Student' : 'AI';
            $lines[] = $label . ': ' . $text;
        }
        return implode("\n", $lines);
    }

    /**
     * Pull a usable grade out of a Cloud Poodll reply.
     *
     * @param mixed $response the decoded reply, or null/false if the call failed
     * @return array|null score, gradeexplanation and feedback, or null if unusable
     */
    protected static function parse_grade($response): ?array {
        if (!$response || !isset($response->returnCode) || $response->returnCode != '0') {
            return null;
        }
        $grade = json_decode((string) ($response->returnMessage ?? ''));
        if (!$grade || !isset($grade->score) || !is_numeric($grade->score)) {
            return null;
        }
        return [
            'score' => max(0, min(100, (int) round((float) $grade->score))),
            'gradeexplanation' => (string) ($grade->gradeexplanation ?? ''),
            'feedback' => (string) ($grade->feedback ?? ''),
        ];
    }

    /**
     * Describes the return value of the grade_chat web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'whether a grade was produced'),
            'score' => new external_value(PARAM_INT, 'the score, 0-100'),
            'gradeexplanation' => new external_value(PARAM_RAW, 'why the score was given, for teachers'),
            'feedback' => new external_value(PARAM_RAW, 'feedback for the student'),
        ]);
    }
}
