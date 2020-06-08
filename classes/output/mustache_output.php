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

/**
 * Mod Poodll Time class to prepare data for printable report mustache.
 *
 * @package mod_poodlltime
 * @copyright 2019 David Watson {@link http://evolutioncode.uk}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_poodlltime\output;

use mod_poodlltime\constants;
use mod_poodlltime\utils;

defined('MOODLE_INTERNAL') || die();
global $CFG;

/**
 * Prepares data for echoing printable report
 *
 * @package mod_poodlltime
 * @copyright 2019 David Watson {@link http://evolutioncode.uk}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mustache_output implements \renderable, \templatable {

    private $moduleinstance;
    private $gradenow;
    private $markeduppassage;
    private $mode;

    public function __construct($moduleinstance, $gradenow, $markeduppassage, $mode) {
        $this->moduleinstance = $moduleinstance;
        $this->gradenow = $gradenow;
        $this->markeduppassage = $markeduppassage;
        $this->mode = $mode;
    }

    /**
     * Export the data for the mustache template.
     * @param \renderer_base $output
     * @return array|\stdClass
     * @throws \moodle_exception
     */
    public function export_for_template(\renderer_base $output) {
        global $USER;
        if(!$this->markeduppassage){
            $this->markeduppassage =  utils::lines_to_brs($this->moduleinstance->passage);
        }

        $errorcount =  $this->gradenow->formdetails('errorcount',false);
        $flower = \mod_poodlltime\flower::get_flower($this->gradenow->attemptdetails('flowerid'));
        $passageclasses = constants::M_PASSAGE_CONTAINER . ' '  . constants::M_POSTATTEMPT;
        switch($this->mode) {
            case 'manual':
                $passageclasses .= ' ' . constants::M_GRADING_MODE;
                break;
            case 'quick':
                $passageclasses .= ' ' . constants::M_QUICK_MODE;
                break;
            case 'power':
                $passageclasses .= ' ' . constants::M_MSV_MODE;
                break;
        }
        return array(
            'title' => $this->moduleinstance->name,
            'student' => $this->gradenow->attemptdetails('userfullname'),
            'teacher' => fullname($USER), // TODO is this correct?
            'date' => date("Y-m-d H:i:s", $this->gradenow->attemptdetails('timecreated')),
            'quiz' => $this->gradenow->attemptdetails('qscore'),
            'level' => $this->moduleinstance->level,
            'audiourl' => $this->gradenow->attemptdetails('audiourl'),
            'flowerid' => $flower['id'],
            'flowername' => $flower['name'],
            'flowerdisplayname' => $flower['displayname'],
            'results' => array(
                'errorcount' => $errorcount,
                'errorrate' => utils::calc_error_rate($errorcount, $this->gradenow->formdetails('sessionendword',false)),
                'sc_rate' => utils::calc_sc_rate($errorcount, $this->gradenow->formdetails('sccount',false)),
                'wpm' => $this->gradenow->formdetails('wpm',false),
                'acc' => $this->gradenow->formdetails('accuracy',false),
                'notes' => $this->gradenow->attemptdetails('notes')
            ),
            'passage' => $this->markeduppassage,
            'passageclasses' => $passageclasses,
            'passageid' => constants::M_PASSAGE_CONTAINER,
        );
    }
}