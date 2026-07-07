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
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\local\cpage;
use mod_minilesson\utils;

/**
 * Web service that toggles the current user's like on a community page
 * submission. One like per user per submission; liking again unlikes.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_cpage_like extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'The community page submission id'),
        ]);
    }

    /**
     * Toggle the current user's like on a submission.
     *
     * @param int $submissionid
     * @return array success flag, liked state and new like count
     */
    public static function execute($submissionid) {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['submissionid' => $submissionid]
        );

        $submission = $DB->get_record(
            constants::M_CPAGESUBMISSIONS_TABLE,
            ['id' => $params['submissionid']],
            '*',
            MUST_EXIST
        );

        [$itemrecord, $moduleinstance, $cm, $context] = cpage::fetch_item_environment($submission->itemid);
        self::validate_context($context);
        require_capability('mod/minilesson:view', $context);

        $iteminstance = utils::fetch_item_from_itemrecord($itemrecord, $moduleinstance, $context);
        if (!$iteminstance->community_page_enabled() || !$iteminstance->community_likes_enabled()) {
            return ['success' => false, 'liked' => false, 'likes' => $submission->likes];
        }

        // No liking your own submission, unshared submissions, or (when groups
        // apply) submissions the viewer should not be able to see.
        if ($submission->userid == $USER->id || empty($submission->consent)) {
            return ['success' => false, 'liked' => false, 'likes' => $submission->likes];
        }
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode != NOGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
            $viewergroups = groups_get_all_groups($moduleinstance->course, $USER->id, $cm->groupingid);
            $ownergroups = groups_get_all_groups($moduleinstance->course, $submission->userid, $cm->groupingid);
            if (!array_intersect_key($viewergroups, $ownergroups)) {
                return ['success' => false, 'liked' => false, 'likes' => $submission->likes];
            }
        }

        [$liked, $likecount] = cpage::toggle_like($submission->id, $USER->id);
        return ['success' => true, 'liked' => $liked, 'likes' => $likecount];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the like was toggled'),
            'liked' => new external_value(PARAM_BOOL, 'Whether the current user now likes the submission'),
            'likes' => new external_value(PARAM_INT, 'The new like count'),
        ]);
    }
}
