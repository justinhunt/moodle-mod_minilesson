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

namespace mod_minilesson\local\chatagent;

use mod_minilesson\constants;

/**
 * Reads and writes conversations.
 *
 * The controller deliberately knows nothing about storage - it mutates a conversation and
 * leaves saving to whoever called it - so this is the only place that touches the two agent
 * tables. That split is what lets the CLI harness drive the same loop from a JSON file.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation_store {
    /**
     * The teacher's conversation about this lesson, started if they have not had one yet.
     *
     * @param int $userid
     * @param int $cmid
     * @param int $courseid
     * @return conversation
     */
    public static function get_or_create(int $userid, int $cmid, int $courseid): conversation {
        global $DB;

        // Most recent first: one conversation per teacher per lesson is the model, but reading
        // the newest rather than asserting uniqueness leaves room to offer several later.
        $records = $DB->get_records(
            constants::M_CHATAGENTCONV_TABLE,
            ['userid' => $userid, 'cmid' => $cmid],
            'timemodified DESC',
            '*',
            0,
            1
        );
        $record = reset($records);
        if ($record) {
            return self::load($record->id);
        }

        $conversation = new conversation();
        $conversation->userid = $userid;
        $conversation->cmid = $cmid;
        $conversation->courseid = $courseid;
        self::save($conversation);
        return $conversation;
    }

    /**
     * Load one conversation, with its transcript.
     *
     * @param int $id
     * @return conversation|null
     */
    public static function load(int $id): ?conversation {
        global $DB;

        $record = $DB->get_record(constants::M_CHATAGENTCONV_TABLE, ['id' => $id]);
        if (!$record) {
            return null;
        }

        $conversation = conversation::from_array([
            'id' => $record->id,
            'userid' => $record->userid,
            'cmid' => $record->cmid,
            'provider' => (string) $record->provider,
            'interactionid' => (string) $record->interactionid,
            'pendingcallid' => (string) $record->pendingcallid,
            'pendingtool' => (string) $record->pendingtool,
            'pendingargs' => json_decode((string) $record->pendingargs, true) ?: [],
            'state' => $record->state,
            'toolcalls' => $record->toolcalls,
            'turncount' => $record->turncount,
        ]);
        $conversation->courseid = (int) $record->courseid;

        $messages = $DB->get_records(constants::M_CHATAGENTMSG_TABLE, ['conversationid' => $record->id], 'id ASC');
        foreach ($messages as $message) {
            $conversation->messages[] = [
                'id' => (int) $message->id,
                'role' => $message->role,
                'content' => (string) $message->content,
                'toolname' => $message->toolname,
                'attachments' => json_decode((string) $message->attachments, true) ?: [],
            ];
        }

        return $conversation;
    }

    /**
     * Write the conversation back, inserting any messages added since it was loaded.
     *
     * @param conversation $conversation updated in place with the ids of anything inserted
     * @return void
     */
    public static function save(conversation $conversation): void {
        global $DB;

        $now = time();
        $record = (object) [
            'userid' => $conversation->userid,
            'cmid' => $conversation->cmid,
            'courseid' => $conversation->courseid,
            'provider' => $conversation->provider,
            'interactionid' => $conversation->interactionid,
            'pendingcallid' => $conversation->pendingcallid,
            'pendingtool' => $conversation->pendingtool,
            'pendingargs' => json_encode($conversation->pendingargs),
            'state' => $conversation->state,
            'toolcalls' => $conversation->toolcalls,
            'turncount' => $conversation->turncount,
            'timemodified' => $now,
        ];

        if ($conversation->id) {
            $record->id = $conversation->id;
            $DB->update_record(constants::M_CHATAGENTCONV_TABLE, $record);
        } else {
            $record->timecreated = $now;
            $conversation->id = (int) $DB->insert_record(constants::M_CHATAGENTCONV_TABLE, $record);
        }

        // Messages are only ever appended, so anything without an id is new.
        foreach ($conversation->messages as $index => $message) {
            if (!empty($message['id'])) {
                continue;
            }
            $conversation->messages[$index]['id'] = (int) $DB->insert_record(constants::M_CHATAGENTMSG_TABLE, (object) [
                'conversationid' => $conversation->id,
                'role' => $message['role'],
                'content' => $message['content'],
                'toolname' => $message['toolname'],
                'toolargs' => null,
                'attachments' => json_encode(self::strip_attachment_data($message['attachments'])),
                'timecreated' => $now,
            ]);
        }
    }

    /**
     * Keep the record of which files were attached, without the files themselves.
     *
     * The bytes belong in the module's file area, not in a text column: a single PDF would
     * outweigh the whole rest of the conversation, and the same file would be stored again on
     * every message that mentioned it. What is kept is enough to find the file again and to
     * show the teacher what they attached.
     *
     * @param array $attachments
     * @return array
     */
    protected static function strip_attachment_data(array $attachments): array {
        foreach ($attachments as $index => $attachment) {
            unset($attachment['data']);
            $attachments[$index] = $attachment;
        }
        return $attachments;
    }

    /**
     * Whether this teacher has anything waiting for them in this lesson, for the resume badge.
     *
     * @param int $userid
     * @param int $cmid
     * @return \stdClass|null the session record, or null when there is nothing to come back to
     */
    public static function pending_for(int $userid, int $cmid): ?\stdClass {
        global $DB;

        $records = $DB->get_records_select(
            constants::M_CHATAGENTCONV_TABLE,
            'userid = ? AND cmid = ? AND state = ?',
            [$userid, $cmid, conversation::STATE_AWAITING_APPROVAL],
            'timemodified DESC',
            'id, state, pendingtool, timemodified',
            0,
            1
        );
        $record = reset($records);
        return $record ?: null;
    }

    /**
     * How many turns this teacher has taken across all their conversations since a given time.
     *
     * Counted from the messages rather than from a counter on the conversation, because the
     * limit caps a person's use of the AI service overall: opening a second conversation should
     * not hand out a fresh allowance.
     *
     * @param int $userid
     * @param int $since timestamp
     * @return int
     */
    public static function user_turns_since(int $userid, int $since): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {" . constants::M_CHATAGENTMSG_TABLE . "} msg
                  JOIN {" . constants::M_CHATAGENTCONV_TABLE . "} conv ON conv.id = msg.conversationid
                 WHERE conv.userid = :userid
                   AND msg.role = :role
                   AND msg.timecreated >= :since";
        return (int) $DB->count_records_sql($sql, ['userid' => $userid, 'role' => 'user', 'since' => $since]);
    }

    /**
     * Delete conversations and their transcripts.
     *
     * @param array $conditions field => value, applied to the session table
     * @return void
     */
    public static function delete_where(array $conditions): void {
        global $DB;

        $conversationids = $DB->get_fieldset_select(
            constants::M_CHATAGENTCONV_TABLE,
            'id',
            self::where_clause($conditions),
            array_values($conditions)
        );
        if (!$conversationids) {
            return;
        }
        self::delete_conversations($conversationids);
    }

    /**
     * Delete a set of conversations, their messages and their attachments.
     *
     * @param array $conversationids
     * @return void
     */
    protected static function delete_conversations(array $conversationids): void {
        global $DB;

        // Files first: once the rows are gone there is nothing left to say which context and
        // itemid the attachments were filed under, and they would sit there forever.
        $cmids = $DB->get_records_list(constants::M_CHATAGENTCONV_TABLE, 'id', $conversationids, '', 'id, cmid');
        foreach ($cmids as $conversation) {
            attachments::delete_for((int) $conversation->cmid, (int) $conversation->id);
        }
        metrics::delete_for($conversationids);

        [$insql, $inparams] = $DB->get_in_or_equal($conversationids);
        $DB->delete_records_select(constants::M_CHATAGENTMSG_TABLE, "conversationid $insql", $inparams);
        $DB->delete_records_select(constants::M_CHATAGENTCONV_TABLE, "id $insql", $inparams);
    }

    /**
     * Delete conversations untouched since a given time, for the cleanup task.
     *
     * @param int $before delete anything not modified since this timestamp
     * @return int how many conversations were removed
     */
    public static function delete_older_than(int $before): int {
        global $DB;

        $conversationids = $DB->get_fieldset_select(constants::M_CHATAGENTCONV_TABLE, 'id', 'timemodified < ?', [$before]);
        if (!$conversationids) {
            return 0;
        }
        self::delete_conversations($conversationids);
        return count($conversationids);
    }

    /**
     * Build a WHERE clause with placeholders from a field => value map.
     *
     * @param array $conditions
     * @return string
     */
    protected static function where_clause(array $conditions): string {
        $parts = [];
        foreach (array_keys($conditions) as $field) {
            $parts[] = $field . ' = ?';
        }
        return implode(' AND ', $parts);
    }
}
