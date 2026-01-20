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

namespace mod_minilesson\courseformat;

use core\output\action_link;
use core\output\local\properties\button;
use core\output\local\properties\text_align;
use core\url;
use core_courseformat\local\overview\overviewitem;
use cm_info;
use mod_minilesson\constants;

// Check if the base class exists before defining the class.
// This ensures backward compatibility with Moodle versions prior to 4.4.
if (class_exists('\core_courseformat\activityoverviewbase')) {

    /**
     * Class overview
     *
     * @package    mod_minilesson
     * @copyright  2025 Poodll
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    class overview extends \core_courseformat\activityoverviewbase {

        /** @var \stdClass $minilesson the minilesson instance. */
        private \stdClass $minilesson;

        /**
         * Constructor.
         *
         * @param cm_info $cm the course module instance.
         * @param \core\output\renderer_helper $rendererhelper the renderer helper.
         * @param \core_string_manager $stringmanager the string manager.
         */
        public function __construct(
            cm_info $cm,
            /** @var \core\output\renderer_helper $rendererhelper the renderer helper */
            protected readonly \core\output\renderer_helper $rendererhelper,
            /** @var \core_string_manager $sm the string manager */
            protected readonly \core_string_manager $stringmanager,
        ) {
            parent::__construct($cm);
            $this->minilesson = $this->cm->get_instance_record();
        }

        #[\Override]
        public function get_actions_overview(): ?overviewitem {
            $url = new url('/mod/minilesson/view.php', ['id' => $this->cm->id]);

            $content = new action_link(
                url: $url,
                text: $this->stringmanager->get_string('view', 'mod_minilesson'),
                attributes: ['class' => button::BODY_OUTLINE->classes()],
            );

            return new overviewitem(
                name: $this->stringmanager->get_string('actions'),
                value: '',
                content: $content,
                textalign: text_align::CENTER,
            );
        }

        #[\Override]
        public function get_extra_overview_items(): array {
            global $DB;

            $items = [];

            // Target Language (ttslanguage)
            if (!empty($this->minilesson->ttslanguage)) {
                $items['targetlanguage'] = new overviewitem(
                    name: $this->stringmanager->get_string('ttslanguage', 'mod_minilesson'),
                    value: $this->minilesson->ttslanguage,
                    content: $this->minilesson->ttslanguage,
                );
            }

            // Total items.
            $totalitems = $DB->count_records('minilesson_rsquestions', ['minilesson' => $this->minilesson->id]);
            $items['totalitems'] = new overviewitem(
                name: $this->stringmanager->get_string('itemcount', 'mod_minilesson'),
                value: $totalitems,
                content: $totalitems,
            );

            // Item types list.
            if (has_capability('mod/minilesson:manage', $this->context)) {
                $sql = "SELECT type, count(id) as count FROM {minilesson_rsquestions} WHERE minilesson = :minilesson GROUP BY type";
                $typerecords = $DB->get_records_sql($sql, ['minilesson' => $this->minilesson->id]);
                $typestrings = [];
                foreach ($typerecords as $record) {
                    $typename = $this->stringmanager->get_string($record->type, 'mod_minilesson');
                    // Fallback if string not found, just use type
                    if (empty($typename)) {
                        $typename = $record->type;
                    }
                    $typestrings[] = "{$typename} ({$record->count})";
                }

                if (!empty($typestrings)) {
                    $items['itemtypes'] = new overviewitem(
                        name: $this->stringmanager->get_string('itemtypes', 'mod_minilesson'),
                        value: implode(', ', $typestrings),
                        content: implode(', ', $typestrings),
                    );
                }
            }

            // Total Attempts.
            if (has_capability('mod/minilesson:manage', $this->context)) {
                $sql = "SELECT COUNT(DISTINCT userid) FROM {minilesson_attempt} WHERE moduleid = :moduleid";
                $studentcount = $DB->count_records_sql($sql, ['moduleid' => $this->minilesson->id]);
 
                $items['totalattempts'] = new overviewitem(
                    name: $this->stringmanager->get_string('totalattempts', 'mod_minilesson'),
                    value: $studentcount,
                    content: $studentcount,
                );
            }

            return $items;
        }
    }
}
