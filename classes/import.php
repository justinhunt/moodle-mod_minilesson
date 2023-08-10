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
        $this->keycolumns =  [];
        $this->keycolumns['type']=['type'=>'string','optional'=>false,'default'=>'','dbname'=>'type'];
        $this->keycolumns['name']=['type'=>'string','optional'=>false,'default'=>'','dbname'=>'name'];
        $this->keycolumns['visible']=['type'=>'boolean','optional'=>true,'default'=>0,'dbname'=>'visible'];
        $this->keycolumns['instructions']=['type'=>'string','optional'=>true,'default'=>'', 'dbname'=>'iteminstructions'];
        $this->keycolumns['text']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtext'];
        $this->keycolumns['textformat']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemtextformat'];
        $this->keycolumns['tts']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtts'];
        $this->keycolumns['ttsvoice']=['type'=>'voice','optional'=>true,'default'=>'','dbname'=>'itemttsvoice'];
        $this->keycolumns['ttsoption']=['type'=>'voiceopts','optional'=>true,'default'=>0,'dbname'=>'itemttsoption'];
        $this->keycolumns['ttsautoplay']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemttsautoplay'];
        $this->keycolumns['textarea']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemtextarea'];
        $this->keycolumns['ytid']=['type'=>'string','optional'=>true,'default'=>'','dbname'=>'itemytid'];
        $this->keycolumns['ytstart']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemytstart'];
        $this->keycolumns['ytend']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'itemytend'];
        $this->keycolumns['audiofname']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemaudiofname'];
        $this->keycolumns['ttsdialog']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttsdialog'];
        $this->keycolumns['ttsdialogopts']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttsdialogopts'];
        $this->keycolumns['ttsdialogvoicea']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEA];
        $this->keycolumns['ttsdialogvoiceb']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEB];
        $this->keycolumns['ttsdialogvoicec']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVOICEC];
        $this->keycolumns['ttsdialogvisible']=['type'=>'boolean','optional'=>true,'default'=>null,'dbname'=>constants::TTSDIALOGVISIBLE];
        $this->keycolumns['ttspassage']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttspassage'];
        $this->keycolumns['ttspassageopts']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'itemttspassageopts'];
        $this->keycolumns['ttspassagevoice']=['type'=>'voice','optional'=>true,'default'=>null,'dbname'=>constants::TTSPASSAGEVOICE];
        $this->keycolumns['ttspassagespeed']=['type'=>'voiceopts','optional'=>true,'default'=>null,'dbname'=>constants::TTSPASSAGESPEED];
        $this->keycolumns['text1']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext1'];
        $this->keycolumns['text1format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext1format'];
        $this->keycolumns['text2']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext2'];
        $this->keycolumns['text2format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext2format'];
        $this->keycolumns['text3']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext3'];
        $this->keycolumns['text3format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext3format'];
        $this->keycolumns['text4']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext4'];
        $this->keycolumns['text4format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext4format'];
        $this->keycolumns['text5']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext5'];
        $this->keycolumns['text5format']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customtext5format'];
        $this->keycolumns['text6']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customtext6'];
        $this->keycolumns['data1']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata1'];
        $this->keycolumns['data2']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata2'];
        $this->keycolumns['data3']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata3'];
        $this->keycolumns['data4']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata4'];
        $this->keycolumns['data5']=['type'=>'string','optional'=>true,'default'=>null,'dbname'=>'customdata5'];
        $this->keycolumns['int1']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint1'];
        $this->keycolumns['int2']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint2'];
        $this->keycolumns['int3']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint3'];
        $this->keycolumns['int4']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint4'];
        $this->keycolumns['int5']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'customint5'];
        $this->keycolumns['timelimit']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'timelimit'];
        $this->keycolumns['layout']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'layout'];
        $this->keycolumns['correctanswer']=['type'=>'int','optional'=>true,'default'=>0,'dbname'=>'correctanswer'];

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
        if(count($this->allvoices) == 0){
            foreach (constants::ALL_VOICES as $lang => $langvoices) {
                foreach ($langvoices as $voicecode=>$voicename) {
                    $this->allvoices[strtolower($voicename)] = $voicecode;
                }
            }
        }
        
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

        //fetch item specific col definitions
        //eg multiaudio needs customtext5 to be voice and customint4 to be voice options
        $itemtype = $line[0]; //for now we force this to be at index 0
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemtype);
        $item_keycolumns = $itemtypeclass::get_import_keycolumns();
        $keycolumns = array_merge($keycolumns,$item_keycolumns);


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
