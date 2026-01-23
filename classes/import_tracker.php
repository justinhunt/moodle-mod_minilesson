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

namespace mod_minilesson;

/**
 * Class import_tracker
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_tracker
{
    /** @var array */
    protected $_row;
/**
     * The columns shown on the table.
     * @var array
     */
    public $columns = [];
/** @var array column headers */
    protected $headers = [];

    /**
     * uu_progress_tracker constructor.
     */
    public function __construct($keycolumns)
    {
        $baseheaders = ['id' => 'ID', 'line' => 'Line', 'status' => 'Status'];
        $headers = array_merge($baseheaders, $keycolumns);
        $this->columns = array_keys($headers);
        $this->headers = array_keys($headers);
    }

    /**
     * Print table header.
     * @return void
     */
    public function start()
    {
        $ci = 0;
        $this->do_echo('<table id="iiresults" class="generaltable boxaligncenter flexible-wrap" summary="' .
                get_string('importitemsresult', constants::M_COMPONENT) . '">');
        $this->do_echo('<tr class="heading r0">');
        foreach ($this->headers as $key => $header) {
            $this->do_echo('<th class="header c' . $ci++ . '" scope="col">' . $header . '</th>');
        }
        $this->do_echo('</tr>');
        $this->_row = null;
    }

    /**
     * Flush previous line and start a new one.
     * @return void
     */
    public function flush()
    {
        if (empty($this->_row) || empty($this->_row['line']['normal'])) {
// Nothing to print - each line has to have at least number.
            $this->_row = [];
            foreach ($this->columns as $col) {
                $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
            }
            return;
        }
        $ci = 0;
        $ri = 1;
        $this->do_echo('<tr class="r' . $ri . '">');
        foreach ($this->_row as $key => $field) {
            foreach ($field as $type => $content) {
                if ($field[$type] !== '') {
                    $field[$type] = '<span class="ii' . $type . '">' . $field[$type] . '</span>';
                } else {
                    unset($field[$type]);
                }
            }
            $this->do_echo('<td class="cell c' . $ci++ . '">');
            if (!empty($field)) {
                $this->do_echo(implode('<br />', $field));
            } else {
                $this->do_echo('&nbsp;');
            }
            $this->do_echo('</td>');
        }
        $this->do_echo('</tr>');
        foreach ($this->columns as $col) {
            $this->_row[$col] = ['normal' => '', 'info' => '', 'warning' => '', 'error' => ''];
        }
    }

    /**
     * Add tracking info
     * @param string $col name of column
     * @param string $msg message
     * @param string $level 'normal', 'warning' or 'error'
     * @param bool $merge true means add as new line, false means override all previous text of the same type
     * @return void
     */
    public function track($col, $msg, $level = 'normal', $merge = true)
    {
        if (empty($this->_row)) {
            $this->flush();
// Init arrays.
        }
        if (!in_array($col, $this->columns)) {
            debugging('Incorrect column:' . $col);
            return;
        }
        if ($merge) {
            if ($this->_row[$col][$level] != '') {
                $this->_row[$col][$level] .= '<br />';
            }
            $this->_row[$col][$level] .= $msg;
        } else {
            $this->_row[$col][$level] = $msg;
        }
    }

    /**
     * Print the table end
     * @return void
     */
    public function close()
    {
        $this->flush();
        $this->do_echo('</table>');
    }

    /**
     * Echo text if not empty and not cli script
     * @param string $text
     * @return void
     */
    public function do_echo($text)
    {
        // If text is empty or this is cli script just return.
        if (empty($text) || defined('CLI_SCRIPT')) {
            return;
        }
        echo $text;
    }
}
