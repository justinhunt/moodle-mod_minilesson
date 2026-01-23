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

namespace mod_minilesson\local\progress;

use mod_minilesson\utils;

/**
 * Class db_updater
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class db_updater extends \core\progress\db_updater
{
    protected $printtime = [];

    public function start_progress($description, $max = self::INDETERMINATE, $parentcount = 1)
    {
        parent::start_progress($description, $max, $parentcount);
        $lastkey = utils::array_key_last($this->currents);
        mtrace("{$description} --> {$lastkey} : start_progress");
        $this->printtime[$lastkey] = microtime();
    }

    public function end_progress()
    {
        $lastkey = utils::array_key_last($this->currents);
        $currentdesc = end($this->descriptions);
        mtrace("{$currentdesc} <-- {$lastkey} : end_progress");
        parent::end_progress();
        $difftime = microtime_diff($this->printtime[$lastkey], microtime());
        mtrace("{$currentdesc} , {$lastkey} : Execution took {$difftime} seconds");
    }
}
