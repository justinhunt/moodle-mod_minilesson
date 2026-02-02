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

namespace mod_minilesson\local\aiactions;

use core_ai\aiactions\responses\response_base;

/**
 * Class common_action
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait common_functions {

    public function get_prompt(): string {
        return $this->prompttext;
    }

    public static function get_basename(): string {
        return basename(str_replace('\\', '/', static::get_parent_actionclass(true)));
    }

    public static function get_parent_actionclass(bool $considerself = false): ?string {
        foreach (class_parents(static::class) as $classname) {
            if (strpos($classname, 'core_ai') === 0) {
                return $classname;
            }
        }
        return $considerself ? static::class : null;
    }

    public static function get_response_classname(): string {
        $classnames[] = static::get_parent_actionclass(true);
        if (!in_array(static::class, $classnames)) {
            $classnames[] = static::class;
        }
        $classnames = array_reverse($classnames);
        foreach ($classnames as $classname) {
            $responseclass = responses::class . '\\response_' . $classname::get_basename();
            if (strpos($classname, 'core_ai') === 0) {
                $responseclass = $classname::get_response_classname();
            }
            if (class_exists($responseclass)) {
                return $responseclass;
            }
        }
        return response_base::class;
    }

    public static function get_model_parameters(string $provider): array {
        return [];
    }

}
