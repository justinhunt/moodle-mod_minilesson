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
 * Handles the import of items from CSV.
 *
 * @package   mod_minilesson
 * @copyright 2023 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_minilesson;


/**
 * Class for importing items into a minilesson
 *
 * @copyright 2023 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import  {

    /**
     * process constructor.
     *
     * @param \csv_import_reader $cir
     * @param string|null $progresstrackerclass
     * @throws \coding_exception
     */
    public function __construct(\csv_import_reader $cir,$moduleinstance, $modulecontext, $course,$cm) {
        $this->cir = $cir;
        $this->moduleinstance = $moduleinstance;
        $this->modulecontext = $modulecontext;
        $this->course = $course;
        $this->cm = $cm;

        $this->keycolumns =  [];
        $this->keycolumns['type']=['type'=>'string','optional'=>false,'default'=>''];
        $this->keycolumns['name']=['type'=>'string','optional'=>false,'default'=>''];
        $this->keycolumns['visible']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['iteminstructions']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemtext']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemtextformat']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['itemtts']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemttsvoice']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemttsoption']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['itemtextarea']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemttsautoplay']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['itemytid']=['type'=>'string','optional'=>true,'default'=>''];
        $this->keycolumns['itemytstart']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['itemytend']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['itemyaudiofname']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['itemttsdialog']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['itemttsdialogopts']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['itemttspassage']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['itemttspassageopts']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext1']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext1format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext2']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext2format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext3']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext3format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext4']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext4format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext5']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customtext5format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext6']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customdata1']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customdata2']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customdata3']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customdata4']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customdata5']=['type'=>'text','optional'=>true,'default'=>null];
        $this->keycolumns['customint1']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint2']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint3']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint4']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint5']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['layout']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['correctanswer']=['type'=>'text','optional'=>true,'default'=>0];

        // Keep timestamp consistent.
        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);

    }

    public function import_process() {

        $this->upt = new import_tracker();
        $this->upt->start(); // Start table.

        // Init csv import helper.
        $this->cir->init();
        $linenum = 1; // Column header is first line.
        while ($line = $this->cir->next()) {
            $linenum++;
            $this->upt->flush();
            $this->upt->track('line', $linenum);
            $this->import_process_line($line);
        }

        $this->upt->close(); // Close table.
        $this->cir->close();
        $this->cir->cleanup(true);
    }


    /**
     * Process one line from CSV file
     *
     * @param array $line
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function import_process_line(array $line)
    {
        global $DB, $CFG, $SESSION;

        // Add fields to user object.
        foreach ($line as $keynum => $value) {
            if (!isset($this->get_file_columns()[$keynum])) {
                // This should not happen.
                continue;
            }
            $key = $this->get_file_columns()[$keynum];

            //do any validations on key type here

            if (in_array($key, $this->upt->columns)) {
                // Default value in progress tracking table, can be changed later.
                $this->upt->track($key, s($value), 'normal');
            }
        }

        return true;
        //Do what we have to do

    }

}
