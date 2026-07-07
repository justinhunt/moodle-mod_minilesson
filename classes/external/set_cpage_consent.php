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
use mod_minilesson\local\cpage;
use mod_minilesson\utils;

/**
 * Web service that saves a student's consent to share their submission
 * on an item's community page. Only ever acts on the current user's own row.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_cpage_consent extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'The lesson item id'),
            'consent' => new external_value(PARAM_BOOL, 'Whether the user consents to sharing'),
        ]);
    }

    /**
     * Save the current user's sharing consent for an item.
     *
     * @param int $itemid the lesson item id
     * @param bool $consent
     * @return array success flag and the saved consent state
     */
    public static function execute($itemid, $consent) {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['itemid' => $itemid, 'consent' => $consent]
        );

        [$itemrecord, $moduleinstance, $cm, $context] = cpage::fetch_item_environment($params['itemid']);
        self::validate_context($context);
        require_capability('mod/minilesson:view', $context);

        $iteminstance = utils::fetch_item_from_itemrecord($itemrecord, $moduleinstance, $context);
        if (!$iteminstance->community_page_enabled()) {
            return ['success' => false, 'consent' => false];
        }

        $submission = cpage::set_consent($params['itemid'], $USER->id, !empty($params['consent']));
        return ['success' => true, 'consent' => !empty($submission->consent)];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the consent was saved'),
            'consent' => new external_value(PARAM_BOOL, 'The saved consent state'),
        ]);
    }
}
