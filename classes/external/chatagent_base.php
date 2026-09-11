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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\local\chatagent\controller;
use mod_minilesson\local\chatagent\conversation;
use mod_minilesson\local\chatagent\conversation_store;

/**
 * What the five chat agent web services have in common.
 *
 * They differ only in what they do to the conversation; the guards in front of them, the
 * envelope they return and the way they build it are identical, so those live here. The
 * guards matter: each one is the browser's only route into the loop, and the browser is not
 * trusted to say which conversation it is in.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class chatagent_base extends external_api {
    /**
     * Load a conversation the current user is allowed to be in, and set up its context.
     *
     * Ownership is checked rather than assumed: the conversation id comes from the browser, and
     * a conversation holds another teacher's transcript, their attachments, and the provider
     * handle that would replay their history.
     *
     * @param int $conversationid
     * @return array [conversation, \context_module]
     * @throws \moodle_exception if the conversation is missing, or is not this user's
     */
    protected static function load_own_conversation(int $conversationid): array {
        global $USER;

        $conversation = conversation_store::load($conversationid);
        if (!$conversation || $conversation->userid != $USER->id) {
            throw new \moodle_exception('chatagent_error_noconversation', constants::M_COMPONENT);
        }

        $context = self::require_lesson_access($conversation->cmid);
        return [$conversation, $context];
    }

    /**
     * Check that the current user may use AI generation on this lesson, right now.
     *
     * @param int $cmid
     * @return \context_module
     */
    protected static function require_lesson_access(int $cmid): \context_module {
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/minilesson:canuseaigen', $context);

        if (!\mod_minilesson\utils::chatagent_available()) {
            throw new \moodle_exception('chatagent_error_unavailable', constants::M_COMPONENT);
        }
        return $context;
    }

    /**
     * The provider driver this site is configured to use.
     *
     * @return \mod_minilesson\local\chatagent\provider_driver
     */
    protected static function driver() {
        return \mod_minilesson\utils::chatagent_driver();
    }

    /**
     * A controller bound to the configured provider.
     *
     * @return controller
     */
    protected static function agent(): controller {
        return new controller(self::driver());
    }

    /**
     * Save the conversation and turn the controller's envelope into a web service response.
     *
     * Messages are returned from the saved conversation rather than from the envelope, so they
     * carry their database ids - which is what lets the browser ask only for what it has not
     * seen. A long conversation would otherwise resend its whole transcript on every step.
     *
     * @param conversation $conversation
     * @param array $envelope what the controller returned
     * @param int $sinceid only return messages newer than this
     * @return array
     */
    protected static function respond(conversation $conversation, array $envelope, int $sinceid): array {
        conversation_store::save($conversation);

        $messages = [];
        foreach ($conversation->messages as $message) {
            if ((int) ($message['id'] ?? 0) <= $sinceid) {
                continue;
            }
            $messages[] = [
                'id' => (int) $message['id'],
                'role' => $message['role'],
                'content' => (string) $message['content'],
                'contenthtml' => self::to_html($message),
                'toolname' => (string) ($message['toolname'] ?? ''),
                'attachments' => json_encode(array_map(static function ($attachment) {
                    unset($attachment['data']);
                    return $attachment;
                }, $message['attachments'])),
            ];
        }

        $response = [
            'conversationid' => $conversation->id,
            'lessonchanged' => !empty($envelope['lessonchanged']),
            'status' => $envelope['status'],
            'state' => $conversation->state,
            'messages' => $messages,
            'error' => (string) ($envelope['error'] ?? ''),
            'errorcode' => (string) ($envelope['errorcode'] ?? ''),
        ];

        if (!empty($envelope['pending'])) {
            $response['pending'] = [
                'callid' => $envelope['pending']['callid'],
                'tool' => $envelope['pending']['tool'],
                'summary' => $envelope['pending']['summary'],
                // Arbitrary per-tool shapes cannot be described to the external API, so the
                // arguments travel as JSON for the approval card to render.
                'argsjson' => json_encode($envelope['pending']['args'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'needsapproval' => !empty($envelope['pending']['needsapproval']),
            ];
        }
        if (!empty($envelope['job'])) {
            $response['job'] = [
                'jobid' => (int) $envelope['job']['jobid'],
                'poll' => !empty($envelope['job']['poll']),
            ];
        }

        return $response;
    }

    /**
     * Render a message for display.
     *
     * The assistant writes markdown, so it is formatted here rather than in the browser: Moodle
     * already has a markdown formatter that sanitises as it goes, and model output is untrusted
     * text. A tool result is machine output that nobody needs to read as prose, so it is left
     * as plain text.
     *
     * @param array $message
     * @return string
     */
    protected static function to_html(array $message): string {
        if ($message['role'] === 'assistant') {
            return format_text($message['content'], FORMAT_MARKDOWN, ['noclean' => false, 'para' => false]);
        }
        return format_text($message['content'], FORMAT_PLAIN, ['para' => false]);
    }

    /**
     * The response every chat agent function returns.
     *
     * @return external_single_structure
     */
    public static function envelope_returns(): external_single_structure {
        return new external_single_structure([
            'conversationid' => new external_value(PARAM_INT, 'The conversation this reply belongs to'),
            'lessonchanged' => new external_value(
                PARAM_BOOL,
                'Whether this request changed the lesson\'s items, so the page should redraw its item list'
            ),
            'status' => new external_value(PARAM_ALPHAEXT, 'complete, requires_tool, requires_approval or error'),
            'state' => new external_value(PARAM_ALPHAEXT, 'The conversation state the reply left behind'),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Message id, to pass back as sinceid'),
                    'role' => new external_value(PARAM_ALPHA, 'user, assistant or tool'),
                    'content' => new external_value(PARAM_RAW, 'Message text, or the tool result'),
                    'contenthtml' => new external_value(PARAM_RAW, 'The same message, formatted and sanitised for display'),
                    'toolname' => new external_value(PARAM_RAW, 'Tool that produced this, on tool messages'),
                    'attachments' => new external_value(PARAM_RAW, 'JSON list of attached files, without their contents'),
                ]),
                'Messages added since sinceid, oldest first'
            ),
            'pending' => new external_single_structure([
                'callid' => new external_value(PARAM_RAW, 'Identifies this call when approving it'),
                'tool' => new external_value(PARAM_RAW, 'Function the assistant wants to run'),
                'summary' => new external_value(PARAM_RAW, 'One line describing what it would do'),
                'argsjson' => new external_value(PARAM_RAW, 'The arguments, as JSON, for the approval card'),
                'needsapproval' => new external_value(PARAM_BOOL, 'Whether it is waiting on the teacher'),
            ], 'A tool waiting to run or to be approved', VALUE_OPTIONAL),
            'job' => new external_single_structure([
                'jobid' => new external_value(PARAM_INT, 'Generation job to poll with aigen_fetch_create_items_status'),
                'poll' => new external_value(PARAM_BOOL, 'Whether the page should poll it'),
            ], 'A background generation job the page should follow', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_RAW, 'Message to show the teacher, when status is error'),
            'errorcode' => new external_value(PARAM_ALPHAEXT, 'auth, quota, connection, transport or unknown'),
        ]);
    }

    /**
     * The sinceid parameter, shared by every function that returns messages.
     *
     * @return external_value
     */
    protected static function sinceid_parameter(): external_value {
        return new external_value(
            PARAM_INT,
            'Only return messages newer than this id; 0 for the whole transcript',
            VALUE_DEFAULT,
            0
        );
    }
}
