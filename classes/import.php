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


use gradereport_singleview\local\ui\empty_element;

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
        $this->allvoices=[];
        $this->cm = $cm;
        $this->errors = 0;
        $this->currentheader =  [];
        $this->keycolumns = local\itemtype\item::get_keycolumns();

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

        //set up the voices array, it only needs to be done once, and probably should be done elsewhere
        //but here works too
        if(count($this->allvoices) == 0){
            foreach (constants::ALL_VOICES as $lang => $langvoices) {
                foreach ($langvoices as $voicecode=>$voicename) {
                    $this->allvoices[strtolower($voicename)] = $voicecode;
                }
            }
        }

        //here we get the item specific keycolumns (its the same columns, but with item specific col info for validation and data preprocessing)
        //eg multiaudio needs customtext5 to be voice and customint4 to be voice options
        $itemtype = $line[0]; //for now we force this to be at index 0
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemtype);
        //if the item type is invalid, we can't continue, just exit
        if(!$itemtypeclass){
            $this->upt->track('status',get_string('error:failed',constants::M_COMPONENT), 'error',true);
            $this->upt->track('type',get_string('error:invaliditemtype',constants::M_COMPONENT), 'error');
            return false;
        }
        $keycolumns = $itemtypeclass::get_keycolumns();
        
        // Pre-Process Import Data, and turn into DB Ready data.
        $newrecord = $this->preprocess_import_data($line, $keycolumns);

        //set the defaults
        foreach($keycolumns as $colname=>$coldef){
            if(!isset($newrecord[$coldef['dbname']])){
                $newrecord[$coldef['dbname']]=$coldef['default'];
            }
        }
        //turn array into object
        $newrecord = (object)$newrecord;

        //fix up json fields which need to be packed into json
        //tts dialog opts
        if(!empty($newrecord->{constants::TTSDIALOG})){
           $newrecord->{constants::TTSDIALOGOPTS} = utils::pack_ttsdialogopts($newrecord);
        }
        //tts passage opts
        if(!empty($newrecord->{constants::TTSPASSAGE})){
            $newrecord->{constants::TTSPASSAGEOPTS} = utils::pack_ttspassageopts($newrecord);
        }

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

    public function preprocess_import_data($line, $keycolumns){

        //return value init
        $newrecord = [];

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
                continue;
            }
            $coldef = $keycolumns[$colname];

            switch($coldef['type']){
                case 'int':
                    $value = intval($value);
                    break;
                case 'string':
                    $value = strval($value);
                    break;
                case 'voice':
                    if(empty($value) || $value == 'auto'){
                        $value = utils::fetch_auto_voice($this->moduleinstance->ttslanguage);
                    }else{
                        if(array_key_exists(strtolower($value), $this->allvoices)){
                            $value = $this->allvoices[strtolower($value)];
                        }elseif(in_array(strtolower($value) . '_g', $this->allvoices)){
                            $value = $this->allvoices[strtolower($value) . '_g'];
                        }else{
                            //not sure how to get this to user
                            $this->upt->track($colname,'UNKNOWN VOICE' . $value, 'warning');
                            $value = utils::fetch_auto_voice($this->moduleinstance->ttslanguage);
                        }
                    }
                    break;

                case 'voiceopts':
                    switch($value){
                        case 'slow':
                            $value = constants::TTS_SLOW;
                            break;
                        case 'veryslow':
                            $value = constants::TTS_VERYSLOW;
                            break;
                        case 'SSML':
                            $value = constants::TTS_SSML;
                            break;
                        default:
                            $value = constants::TTS_NORMAL;
                            break;
                    }
                    break;

                case 'layout':
                    switch($value){
                        case 'horizontal':
                            $value = constants::LAYOUT_HORIZONTAL;
                            break;
                        case 'vertical':
                            $value = constants::LAYOUT_VERTICAL;
                            break;
                        case 'magazine':
                            $value = constants::LAYOUT_MAGAZINE;
                            break;
                        default:
                            $value = constants::LAYOUT_AUTO;
                            break;
                    }
                    break;

                case 'boolean':
                    switch(strtolower($value)){
                        case 'true':
                            $value = 1;
                            break;
                        case 'false':
                            $value = 0;
                            break;
                        default:
                            $value = 1;
                    }
                    break;
            }

            //set default values
            if (in_array($colname, $this->upt->columns)) {
                // Default value in progress tracking table, can be changed later.
                $this->upt->track($colname, s($value), 'normal');
            }
            $newrecord[$coldef['dbname']] = $value;
        }
        return $newrecord;
    }

}
