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
        $this->errors = 0;
        $this->currentheader =  [];
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
        $this->keycolumns['itemaudiofname']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['itemttsdialog']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['itemttsdialogopts']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['itemttspassage']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['itemttspassageopts']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext1']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext1format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext2']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext2format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext3']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext3format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext4']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext4format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext5']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customtext5format']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customtext6']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customdata1']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customdata2']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customdata3']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customdata4']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customdata5']=['type'=>'string','optional'=>true,'default'=>null];
        $this->keycolumns['customint1']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint2']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint3']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint4']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['customint5']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['timelimit']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['layout']=['type'=>'int','optional'=>true,'default'=>0];
        $this->keycolumns['correctanswer']=['type'=>'int','optional'=>true,'default'=>0];

        // Keep timestamp consistent.
        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);

    }

    public function import_process() {
        $this->errors = 0;
        $this->upt = new import_tracker($this->keycolumns);
        $this->upt->start(); // Start table.

        // Init csv import helper.
        $this->cir->init();
        
        //get the header line
        $this->currentheader = $this->cir->get_columns();
        
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

        $keycolumns = $this->keycolumns;
        $newrecord = [];
        
        // Add fields to user object.
        foreach ($line as $keynum => $value) {
            
            
            if (!isset($this->currentheader[$keynum])) {
                // This should not happen.
                continue;
            }
            $colname = $this->currentheader[$keynum];
            if (!isset($keycolumns[$colname])) {
                // This should not happen.

                $this->upt->track('status','unknown column ' . $colname, 'error');
                $this->errors++;
                return false;
            }
            $coldef = $keycolumns[$colname];
            
            switch($coldef['type']){
                case 'int':
                    $value = intval($value);
                    break;
                case 'string':
                    $value = strval($value);
                    break;
            }

            //set default values
            if (in_array($colname, $this->upt->columns)) {
                // Default value in progress tracking table, can be changed later.
                $this->upt->track($colname, s($value), 'normal');
            }
            $newrecord[$colname] = $value;
        }

        //set the defaults
        foreach($keycolumns as $colname=>$coldef){
            if(!isset($newrecord[$colname])){
                $newrecord[$colname]=$coldef['default'];
            }
        }
        //turn array into object
        $newrecord = (object)$newrecord;

        // call the itemtype specific import validation function
        $error = $this->perform_import_validation($newrecord,$this->cm);
        if($error) {
            $this->upt->track('status',get_string('error:failed',constants::M_COMPONENT), 'error',true);
            $this->upt->track($error->col, $error->message, 'error');
            return false;
        }

        //get itemorder
        $newrecord->itemorder = local\itemform\helper::get_new_itemorder($this->cm);

        //create a rsquestionkey
        $newrecord->rsquestionkey = local\itemtype\item::create_itemkey();
        $theitem= utils::fetch_item_from_itemrecord($newrecord,$this->moduleinstance);
        //remove bad accents and things that mess up transcription (kind of like clear but permanent)
        $theitem->deaccent();

        //xreate passage hash
        $olditem=false;
        $theitem->update_create_langmodel($olditem);

        //lets update the phonetics
        $theitem->update_create_phonetic($olditem);

        $result = $theitem->update_insert_item();
        if($result){
            $this->upt->track('status','Success', 'normal');
        }else{
            $this->upt->track('status','Failed', 'error');
        }

        return true;
        //Do what we have to do

    }

    public function perform_import_validation($newrecord,$cm){
        global $DB;
        $itemtype = $newrecord->type;
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemtype);
        if($itemtypeclass) {
            $error = $itemtypeclass::validate_import($newrecord, $cm);
            if ($error) {
                return $error;
            }
        }
        return false;
    }

}
