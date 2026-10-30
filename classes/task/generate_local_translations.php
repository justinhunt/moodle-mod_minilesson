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

namespace mod_minilesson\task;

use core\task\adhoc_task;
use mod_minilesson\constants;
use mod_minilesson\local\localtranslations;

/**
 * Make the local translations an item is missing.
 *
 * The custom data is only the item id. The task reads the item when it runs, so an item edited again
 * before then is translated as it is now, and two requests for one item are one task.
 *
 * It runs as the user who saved the item, so that the AI is used on their behalf. A language that
 * comes back empty is not retried here: localtranslations notes when it was tried, and a page view
 * asks again a day later.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_local_translations extends adhoc_task {
    /**
     * A name for the admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_generate_local_translations', constants::M_COMPONENT);
    }

    /**
     * Translate the item.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->itemid)) {
            mtrace('No item id in the custom data, nothing to do.');
            return;
        }
        mtrace('Item ' . $data->itemid . ': ' . localtranslations::generate_for_item($data->itemid));
    }

    /**
     * Ask for an item to be translated, unless that is already on the queue.
     *
     * @param int $itemid
     * @param int $userid the user the AI is used on behalf of
     */
    public static function queue_for_item($itemid, $userid) {
        $task = new self();
        $task->set_custom_data((object) ['itemid' => (int) $itemid]);
        if (!empty($userid)) {
            $task->set_userid($userid);
        }
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
