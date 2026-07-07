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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\local\cpage;
use mod_minilesson\utils;

/**
 * Web service that returns the eligible shared submissions for an item's
 * community page. Evaluations/feedback are never included, and group
 * restrictions are applied server side.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_cpage_submissions extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'The lesson item id'),
            'sort' => new external_value(PARAM_ALPHA, 'Sort order: date (default) or likes', VALUE_DEFAULT, cpage::SORT_DATE),
        ]);
    }

    /**
     * Fetch the community page entries for an item.
     *
     * @param int $itemid the lesson item id
     * @param string $sort date|likes
     * @return array
     */
    public static function execute($itemid, $sort = cpage::SORT_DATE) {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['itemid' => $itemid, 'sort' => $sort]
        );

        [$itemrecord, $moduleinstance, $cm, $context] = cpage::fetch_item_environment($params['itemid']);
        self::validate_context($context);
        require_capability('mod/minilesson:view', $context);

        $iteminstance = utils::fetch_item_from_itemrecord($itemrecord, $moduleinstance, $context);
        if (!$iteminstance->community_page_enabled()) {
            return ['success' => false, 'likesenabled' => false, 'myconsent' => false, 'canshare' => false,
                'canexpand' => false, 'entries' => []];
        }

        $sortorder = $params['sort'] === cpage::SORT_LIKES ? cpage::SORT_LIKES : cpage::SORT_DATE;
        $mingrade = $iteminstance->community_eligibility_grade();
        $requiremedia = $iteminstance->community_needs_media();
        $entries = cpage::fetch_community_entries(
            $itemrecord,
            $moduleinstance,
            $cm,
            $context,
            $USER->id,
            $sortorder,
            $mingrade,
            $requiremedia
        );

        $mysubmission = cpage::get_submission($params['itemid'], $USER->id);
        return [
            'success' => true,
            'likesenabled' => $iteminstance->community_likes_enabled(),
            'myconsent' => $mysubmission && !empty($mysubmission->consent),
            'canshare' => cpage::can_share($itemrecord, $moduleinstance, $USER->id, $mingrade, $requiremedia),
            // Written item types let viewers expand the shortened text with a "more" link.
            'canexpand' => !$requiremedia,
            'entries' => array_map(function ($entry) {
                return (array) $entry;
            }, $entries),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the community page is available'),
            'likesenabled' => new external_value(PARAM_BOOL, 'Whether likes are enabled on this item'),
            'myconsent' => new external_value(PARAM_BOOL, 'The current user\'s sharing consent'),
            'canshare' => new external_value(PARAM_BOOL, 'Whether the current user has a shareable submission'),
            'canexpand' => new external_value(PARAM_BOOL, 'Whether a "more" link can expand the shortened transcripts'),
            'entries' => new external_multiple_structure(
                new external_single_structure([
                    'submissionid' => new external_value(PARAM_INT, 'The submission id'),
                    'fullname' => new external_value(PARAM_TEXT, 'The submitting user\'s full name'),
                    'profileimageurl' => new external_value(PARAM_URL, 'The submitting user\'s profile picture url'),
                    'country' => new external_value(PARAM_TEXT, 'The submitting user\'s country'),
                    'transcript' => new external_value(PARAM_TEXT, 'The submission transcript, truncated'),
                    'mediaurl' => new external_value(PARAM_URL, 'The audio recording url'),
                    'likes' => new external_value(PARAM_INT, 'The like count'),
                    'timemodified' => new external_value(PARAM_INT, 'Submission timestamp'),
                    'submitdate' => new external_value(PARAM_TEXT, 'Submission date for display'),
                    'isowner' => new external_value(PARAM_BOOL, 'Whether this entry belongs to the current user'),
                    'likedbyme' => new external_value(PARAM_BOOL, 'Whether the current user liked this entry'),
                ])
            ),
        ]);
    }
}
