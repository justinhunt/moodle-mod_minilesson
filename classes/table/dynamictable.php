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

namespace mod_minilesson\table;

use core_table\dynamic;
use html_writer;
use table_sql;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class dynamictable
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dynamictable extends table_sql implements dynamic {

    public function __construct($uniqueid = null) {
        if (is_null($uniqueid)) {
            $classparts = explode('\\', get_class($this));
            $uniqueid = html_writer::random_id(end($classparts));
        }
        parent::__construct($uniqueid);
    }

    public static function get_filterset_object(): filterset {
        $filterclass = static::get_filterset_class();
        return new $filterclass;
    }

    public static function get_filterset_class(): string {
        return static::class . '_filterset';
    }

    public function render($pagesize = 30, $useinitialsbar = false, $downloadhelpbutton=''): string {
        ob_start();
        $this->out($pagesize, $useinitialsbar, $downloadhelpbutton);
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    }

}
