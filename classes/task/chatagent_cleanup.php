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

namespace mod_minilesson\task;

use mod_minilesson\constants;
use mod_minilesson\local\chatagent\conversation_store;

/**
 * Remove assistant conversations nobody has touched for a while.
 *
 * A conversation is working material, not a record of the lesson: once the items are made, the
 * chat that made them has served its purpose. Keeping them indefinitely would leave teacher
 * prompts and the text of attached documents sitting in the database for no benefit, so they
 * age out on the site's own schedule.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_cleanup extends \core\task\scheduled_task {
    /** @var int Days to keep a conversation when the site has not said otherwise. */
    const DEFAULT_RETAIN_DAYS = 30;

    /**
     * The name shown on the scheduled tasks page.
     *
     * @return string
     */
    public function get_name() {
        return get_string('chatagent_task_cleanup', constants::M_COMPONENT);
    }

    /**
     * Delete conversations older than the retention setting.
     *
     * @return void
     */
    public function execute() {
        $days = (int) get_config(constants::M_COMPONENT, 'chatagentretaindays');
        if ($days <= 0) {
            $days = self::DEFAULT_RETAIN_DAYS;
        }

        $deleted = conversation_store::delete_older_than(time() - ($days * DAYSECS));
        if ($deleted) {
            mtrace('Removed ' . $deleted . ' assistant conversation(s) older than ' . $days . ' days.');
        }
    }
}
