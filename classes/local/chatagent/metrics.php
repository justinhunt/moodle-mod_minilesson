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
 * What the chat agent actually did, recorded so it can be counted later.
 *
 * Two questions need answering from real use and neither can be answered by impression. Is the
 * agent behaving well enough to build on - does it get through a lesson without going round in
 * circles, does it compose items the import accepts, how often does a conversation have to be
 * replayed? And how much does a lesson cost in calls and bytes, which is what decides whether
 * brokering the model call through a shared server is affordable.
 *
 * One row per model call, because the interesting figures are distributions rather than totals:
 * a median turn and a worst turn tell different stories, and only the worst one sizes anything.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class metrics {
    /** @var string The model asked for a tool this agent does not offer. */
    const REJECT_NOTALLOWED = 'notallowed';

    /** @var string The teacher no longer has the capability the tool needs. */
    const REJECT_NOCAPABILITY = 'nocapability';

    /** @var string The tool was aimed at a lesson other than this conversation's. */
    const REJECT_WRONGLESSON = 'wronglesson';

    /** @var string The web service itself refused the call. */
    const REJECT_FAILED = 'failed';

    /**
     * Record one model call, and whatever tool ran before it.
     *
     * Deliberately forgiving: measurement must never be the thing that breaks a teacher's
     * conversation, so a failure to record is swallowed rather than raised.
     *
     * @param conversation $conversation
     * @param array $fields any of toolname, toolrejected, rejectreason, requestbytes,
     *        attachmentbytes, inputtokens, outputtokens, replayed, errorcode
     * @return void
     */
    public static function record(conversation $conversation, array $fields = []): void {
        global $DB;

        try {
            $DB->insert_record(constants::M_CHATAGENTMETRIC_TABLE, (object) array_merge([
                'conversationid' => $conversation->id,
                'cmid' => $conversation->cmid,
                'turn' => $conversation->turncount,
                'toolname' => null,
                'toolrejected' => 0,
                'rejectreason' => null,
                'requestbytes' => 0,
                'attachmentbytes' => 0,
                'inputtokens' => 0,
                'outputtokens' => 0,
                'replayed' => 0,
                'errorcode' => null,
                'timecreated' => time(),
            ], $fields));
        } catch (\Throwable $e) {
            debugging('mod_minilesson chat agent: could not record metrics: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * How big an input is, and how much of that is attachments.
     *
     * Measured after trimming, because what matters is what actually goes over the wire.
     *
     * @param array $input the input blocks about to be sent
     * @return array [total bytes, attachment bytes]
     */
    public static function measure_input(array $input): array {
        $attachments = 0;
        foreach ($input as $block) {
            if (!empty($block['data'])) {
                $attachments += strlen($block['data']);
            }
        }
        $total = strlen(json_encode($input, JSON_UNESCAPED_UNICODE) ?: '');
        return [$total, $attachments];
    }

    /**
     * Remove the rows belonging to a set of conversations.
     *
     * @param array $conversationids
     * @return void
     */
    public static function delete_for(array $conversationids): void {
        global $DB;

        if (!$conversationids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($conversationids);
        $DB->delete_records_select(constants::M_CHATAGENTMETRIC_TABLE, "conversationid $insql", $inparams);
    }
}
