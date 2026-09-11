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

namespace mod_minilesson\event;

use mod_minilesson\constants;

/**
 * Logged when the chat agent creates or changes a lesson on a teacher's behalf.
 *
 * Only the actions a teacher had to approve are recorded - creating a lesson, running a
 * generation template, importing items - not every message. What this answers is the question
 * an administrator actually asks later: what did the assistant do here, for whom, and on whose
 * say-so. Ordinary conversation is not that, and logging it would bury the answer.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_action_performed extends \core\event\base {
    /**
     * Record one approved action.
     *
     * @param \context_module $modulecontext the lesson the action was performed on
     * @param int $conversationid the conversation it came from
     * @param string $tool the web service function that ran
     * @param string $summary the one-line description the teacher approved
     * @return self
     */
    public static function create_from_action(
        \context_module $modulecontext,
        int $conversationid,
        string $tool,
        string $summary
    ): self {
        /** @var self $event */
        $event = self::create([
            'context' => $modulecontext,
            'objectid' => $conversationid,
            'other' => ['tool' => $tool, 'summary' => $summary],
        ]);
        return $event;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = constants::M_CHATAGENTCONV_TABLE;
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Describe what happened.
     *
     * @return string
     */
    public function get_description() {
        $tool = $this->other['tool'] ?? '';
        $summary = $this->other['summary'] ?? '';
        return "The user with id '$this->userid' approved the chat agent action '$tool' ($summary) "
            . "in the minilesson with course module id '$this->contextinstanceid', "
            . "from conversation with id '$this->objectid'.";
    }

    /**
     * The event's display name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:chatagent_action_performed', constants::M_COMPONENT);
    }

    /**
     * Where to go to see it.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(constants::M_URL . '/chatagent.php', ['id' => $this->contextinstanceid]);
    }
}
