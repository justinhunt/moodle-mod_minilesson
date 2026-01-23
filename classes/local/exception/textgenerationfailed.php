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

namespace mod_minilesson\local\exception;

use mod_minilesson\constants;
use mod_minilesson\utils;
use moodle_exception;

/**
 * Class textgeneration
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class textgenerationfailed extends moodle_exception
{
    public function __construct(int $itemindex, string $itemtype, string $debug = '')
    {
        parent::__construct('textgenerationfailed', constants::M_COMPONENT, '', [
            'itemindex' => utils::ordinalsuffix($itemindex), 'itemtype' => $itemtype
        ], $debug);
    }
}
