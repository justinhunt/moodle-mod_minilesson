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
class import
{

    private $itemsfromjson = false;
    private $cir;
    private $isjson = false;
    private $filesfromjson = false;
    private $moduleinstance;
    private $modulecontext;
    private $course;
    private $cm;
    private $errors;
    private $upt;
    private $currentheader;
    private $keycolumns;
    private $allvoices;


    /**
     * process constructor.
     *
     * @param \csv_import_reader $cir
     * @param string|null $progresstrackerclass
     * @throws \coding_exception
     */
    public function __construct($moduleinstance, $modulecontext, $course, $cm)
    {
        $this->moduleinstance = $moduleinstance;
        $this->modulecontext = $modulecontext;
        $this->course = $course;
        $this->allvoices = [];
        $this->cm = $cm;
        $this->errors = 0;
        $this->currentheader = [];
        $this->keycolumns = local\itemtype\item::get_keycolumns();

        // Keep timestamp consistent.
        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);

    }

    public function set_reader($reader, $isjson = false)
    {
        if ($isjson) {
            $this->isjson = true;
            $this->itemsfromjson = $reader->items;
            $this->filesfromjson = $reader->files;
        } else {
            $this->cir = $reader;
        }
    }

    public function import_process()
    {
        $this->errors = 0;
        $this->upt = new import_tracker($this->keycolumns);
        $this->upt->start(); // Start table.

        if ($this->isjson) {

            $linenum = 0;
            foreach ($this->itemsfromjson as $item) {
                $linenum++;
                $this->upt->flush();
                $this->upt->track('line', $linenum);
                $this->import_process_line($item);
            }
        } else {
            // Init csv import helper.
            $this->cir->init();

            // get the header line
            $this->currentheader = $this->cir->get_columns();

            $linenum = 1; // Column header is first line.
            while ($item = $this->cir->next()) {
                $linenum++;
                $this->upt->flush();
                $this->upt->track('line', $linenum);
                $this->import_process_line($item);
            }
            $this->cir->close();
            $this->cir->cleanup(true);
        }
        $this->upt->close(); // Close table.
    }

    public function map_json_to_csv($itemdata)
    {
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemdata->type);
        $keycolumns = $itemtypeclass::get_keycolumns();
        $line = [];
        foreach ($keycolumns as $colname => $coldef) {
            if (isset($itemdata->{$coldef['jsonname']})) {
                if ($coldef['type'] == 'stringarray') {
                    if (!is_array($itemdata->{$coldef['jsonname']})) {
                        // If generation failed for some reason, fall back (so we dont lose it all)
                        // Which fallback is better?
                        $line[] = $itemdata->{$coldef['jsonname']};
                        // $line[] = join(PHP_EOL, $coldef['default']);
                    } else {
                        $line[] = join(PHP_EOL, $itemdata->{$coldef['jsonname']});
                    }
                } else {
                    $line[] = $itemdata->{$coldef['jsonname']};
                }
            } else {
                if ($coldef['type'] == 'stringarray') {
                    $line[] = join(PHP_EOL, $coldef['default']);
                } else {
                    $line[] = $coldef['default'];
                }
            }
        }
        return $line;
    }

    /**
     * Process one line of CSV or JSON
     *
     * @param array $line
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function import_process_line($itemdata)
    {
        global $DB, $CFG, $SESSION;

        if ($this->isjson) {
            $itemtype = $itemdata->type;
            $line = $this->map_json_to_csv($itemdata);
        } else {
            $line = $itemdata;
            $itemtype = $line[0];
        }
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemtype);

        // if the item type is invalid, we can't continue, just exit
        if (!$itemtypeclass) {
            $this->upt->track('status', get_string('error:failed', constants::M_COMPONENT), 'error', true);
            $this->upt->track('type', get_string('error:invaliditemtype', constants::M_COMPONENT), 'error');
            return false;
        }

        // here we get the item specific keycolumns (it's the same columns, but with item specific col info for validation and data preprocessing)
        // eg multiaudio needs customtext5 to be voice and customint4 to be voice options
        $keycolumns = $itemtypeclass::get_keycolumns();

        // set up the voices array, it only needs to be done once, and probably should be done elsewhere
        // but here works too
        if (count($this->allvoices) == 0) {
            foreach (constants::ALL_VOICES as $lang => $langvoices) {
                foreach ($langvoices as $voicecode => $voicename) {
                    $this->allvoices[strtolower($voicename)] = $voicecode;
                }
            }
        }

        // Pre-Process Import Data, and turn into DB Ready data.
        $newrecord = $this->preprocess_import_data($line, $keycolumns);

        // set the defaults
        foreach ($keycolumns as $colname => $coldef) {
            if ($coldef['dbname'] && !isset($newrecord[$coldef['dbname']])) {
                $newrecord[$coldef['dbname']] = $coldef['default'];
            }
        }
        // turn array into object
        $newrecord = (object) $newrecord;

        // do files
        // if the item has a filesid attribute and that filesid holds data in filesfromjson array
        if (isset($itemdata->filesid) && isset($this->filesfromjson->{$itemdata->filesid})) {
            // files are stored as filename->base64data in the filesfromjson[filesid][filearea] array
            // each item has a filesid, so we can match them up with the file location in filesfromjson
            // json_encode can not be trusted to maintain arrays or objects, so force them to be arrays here
            $filesdata = $this->filesfromjson->{$itemdata->filesid};
            if (!is_array($filesdata)) {
                $filesdata = (array) $filesdata;
            }
            foreach ($filesdata as $filearea => $thefiles) {
                if (!is_array($thefiles)) {
                    $thefiles = (array) $thefiles;
                }
                $newrecord->{$filearea} = $thefiles;
            }
        }

        // fix up json fields which need to be packed into json
        // tts dialog opts
        if (!empty($newrecord->{constants::TTSDIALOG})) {
            $newrecord->{constants::TTSDIALOGOPTS} = utils::pack_ttsdialogopts($newrecord);
        }
        // tts passage opts
        if (!empty($newrecord->{constants::TTSPASSAGE})) {
            $newrecord->{constants::TTSPASSAGEOPTS} = utils::pack_ttspassageopts($newrecord);
        }

        // call the itemtype specific import validation function
        $error = $this->perform_import_validation($newrecord, $this->cm);
        if ($error) {
            $this->upt->track('status', get_string('error:failed', constants::M_COMPONENT), 'error', true);
            $this->upt->track($error->col, $error->message, 'error');
            return false;
        }

        // get itemorder
        $newrecord->itemorder = local\itemform\helper::get_new_itemorder($this->cm);

        // create a rsquestionkey
        $newrecord->rsquestionkey = local\itemtype\item::create_itemkey();
        $theitem = utils::fetch_item_from_itemrecord($newrecord, $this->moduleinstance);
        // remove bad accents and things that mess up transcription (kind of like clear but permanent)
        $theitem->deaccent();

        // xreate passage hash
        $olditem = false;
        $theitem->update_create_langmodel($olditem);

        // lets update the phonetics
        $theitem->update_create_phonetic($olditem);

        // finally do the update
        $result = $theitem->update_insert_item();
        if ($result) {
            $this->upt->track('status', 'Success', 'normal');
        } else {
            $this->upt->track('status', 'Failed', 'error');
        }

        return true;
        // Do what we have to do

    }

    public function perform_import_validation($newrecord, $cm)
    {
        global $DB;
        $itemtype = $newrecord->type;
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemtype);
        if ($itemtypeclass) {
            $error = $itemtypeclass::validate_import($newrecord, $cm);
            if ($error) {
                return $error;
            }
        }
        return false;
    }

    public function preprocess_import_data($line, $keycolumns)
    {

        // return value init
        $newrecord = [];

        foreach ($line as $keynum => $value) {

            // CSV files have the field name in the top line of the file = current header
            // but JSON files its in the json per item (which we stripped away to make CSV like data duh ..)
            // so we need to get the field name from the keycolumns array
            if (isset($this->currentheader[$keynum])) {
                // CSV data
                $colname = $this->currentheader[$keynum];
            } else {
                // JSON data
                $currentheader = array_keys($keycolumns);
                $colname = $currentheader[$keynum];
            }

            if (!isset($keycolumns[$colname])) {
                // This should not happen.

                $this->upt->track('status', 'unknown column ' . $colname, 'error');
                $this->errors++;
                continue;
            }
            $coldef = $keycolumns[$colname];

            switch ($coldef['type']) {
                case 'int':
                    $value = intval($value);
                    break;
                case 'string':
                case 'stringarray':
                    $value = strval($value);
                    break;

                case 'voice':
                    if (empty($value) || $value == 'auto') {
                        // this will return the name, not the key
                        $value = utils::fetch_auto_voice($this->moduleinstance->ttslanguage);
                        // here we go from name to key
                        $value = $this->allvoices[strtolower($value)];
                    } else {
                        if (array_key_exists(strtolower($value), $this->allvoices)) {
                            $value = $this->allvoices[strtolower($value)];
                        } else if (in_array(strtolower($value) . '_g', $this->allvoices)) {
                            $value = $this->allvoices[strtolower($value) . '_g'];
                        } else {
                            // not sure how to get this to user
                            $this->upt->track($colname, 'UNKNOWN VOICE' . $value, 'warning');
                            $value = utils::fetch_auto_voice($this->moduleinstance->ttslanguage);
                        }
                    }
                    break;

                case 'voiceopts':
                    switch ($value) {
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
                    switch ($value) {
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
                    switch (strtolower($value)) {
                        case 'true':
                        case 'yes':
                        case '1':
                            $value = 1;
                            break;
                        case 'false':
                        case 'no':
                        case '0':
                            $value = 0;
                            break;
                        default:
                            $value = 1;
                    }
                    break;
                case 'file':
                    // we don't do anything with this here, but we need to set it to something
                    $value = '';

                case 'anonymousfile':
                    // we don't do anything with this here, but we need to set it to something
                    $value = '';
            }

            // set default values
            if (in_array($colname, $this->upt->columns)) {
                // Default value in progress tracking table, can be changed later.
                if (is_array($value)) {
                    $this->upt->track($colname, join(PHP_EOL, $value), 'normal');
                } else {
                    $this->upt->track($colname, s($value), 'normal');
                }
            }
            $newrecord[$coldef['dbname']] = $value;
        }
        return $newrecord;
    }

    public function call_translate($itemsjson, $fromlang, $tolang){
        $aigen = new aigen($this->cm);
        $prompt = "Translate any instances of language: $fromlang , into language: $tolang in the following JSON string." . PHP_EOL;
        $prompt .= "Return results in the format: {translatedjson: thetranslation}" . PHP_EOL;
        $prompt .= $itemsjson;

        $ret = $aigen->generate_data($prompt);
        if ($ret->success) {
            // The JSON will have been decoded into items in the transferall process, no need to decode again.
            return $ret->payload->translatedjson;
        } else {
            return false;
        }
    }

    public function translate_items($fromlang, $tolang) {
        $jsonformat = false;
        $exportobj = $this->export_items($jsonformat);
        $itemsjson = json_encode($exportobj->items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $translateditems = $this->call_translate($itemsjson, $fromlang, $tolang);

        // AI sometimes returns from AI here as json and sometimes it arrives already decoded as array.
        // So we make sure its an array as best we can
        if ($translateditems && !is_array($translateditems) && utils::is_json($translateditems)) {
            $translateditems = json_decode($translateditems);
        }

        if ($translateditems && is_array($translateditems)) {
            $exportobj->items = $translateditems;
            return json_encode($exportobj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            return false;
        }
    }

    public function export_items($jsonformat = true)
    {
        global $DB;
        $allitems = $DB->get_records(constants::M_QTABLE, ['minilesson' => $this->moduleinstance->id], 'itemorder ASC');
        $exportobj = new \stdClass();
        $exportobj->items = [];
        $exportobj->files = [];
        if ($allitems && count($allitems) > 0) {
            $i = 0;
            foreach ($allitems as $theitem) {
                $i++;
                $itemobj = $this->export_item_as_jsonobj($theitem);
                if ($itemobj) {
                    // Do a files check .. if so move them to the final files obj at end of json file and set an id in the item.
                    if (count($itemobj->files) > 0) {
                        $itemobj->filesid = $i;
                        // add the files to the export obj
                        $exportobj->files[$i] = $itemobj->files;
                    }
                    unset($itemobj->files);
                    // Add the item to the items array.
                    $exportobj->items[] = $itemobj;
                }
            }
        }

        // Depending on export format return JSON or an object. (Translate prefers an object).
        if ($jsonformat) {
            return json_encode($exportobj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            return $exportobj;
        }
    }

    public function export_item_as_jsonobj($itemrecord)
    {
        // get item type
        $itemtypeclass = local\itemtype\item::get_itemtype_class($itemrecord->type);
        if (!$itemtypeclass) {
            return false;
        }

        // files info
        $thefiles = [];

        // get item column details
        $keycolumns = $itemtypeclass::get_keycolumns();

        // set up all voices if its not set up
        if (count($this->allvoices) == 0) {
            foreach (constants::ALL_VOICES as $lang => $langvoices) {
                foreach ($langvoices as $voicecode => $voicename) {
                    $this->allvoices[strtolower($voicename)] = $voicecode;
                }
            }
        }

        // Set fields where we pack data into json in DB
        // tts dialog opts
        if (!empty($itemrecord->{constants::TTSDIALOG})) {
            $itemrecord = utils::unpack_ttsdialogopts($itemrecord);
        }
        $itemrecord->{constants::TTSDIALOGOPTS} = null;

        // tts passage opts
        if (!empty($itemrecord->{constants::TTSPASSAGE})) {
            $itemrecord = utils::unpack_ttspassageopts($itemrecord);
        }
        $itemrecord->{constants::TTSPASSAGEOPTS} = null;

        // make an empty item object
        $itemobj = new \stdClass();

        // loop through columnns making a nice value for our json object
        foreach ($keycolumns as $keycolumn) {
            $fieldvalue = $itemrecord->{$keycolumn['dbname']};
            // skip any optional fields whose value is the default
            // anonymous files are not in the DB record, so we need to process them a little later, to see if they are present
            // for some reason integers and nulls are strings in $fieldvalue, so we  == though it should be ===
            if ($keycolumn['optional'] == true && $keycolumn['default'] == $fieldvalue && $keycolumn['type'] !== 'anonymousfile') {
                // skip
                continue;
            }

            // Turn db values into human values.
            switch ($keycolumn['type']) {
                case 'int':
                case 'string':
                    $jsonvalue = $fieldvalue;
                    break;

                case 'stringarray':
                    $lines = explode(PHP_EOL, $fieldvalue);
                    $jsonvalue = $lines;
                    break;

                case 'voice':

                    if (array_key_exists(strtolower($fieldvalue), $this->allvoices)) {
                        $jsonvalue = $this->allvoices[strtolower($fieldvalue)];
                    } else if (in_array(strtolower($fieldvalue) . '_g', $this->allvoices)) {
                        $jsonvalue = $this->allvoices[strtolower($fieldvalue) . '_g'];
                    } else {
                        $jsonvalue = 'auto';
                    }
                    break;

                case 'voiceopts':
                    switch ($fieldvalue) {
                        case constants::TTS_SLOW:
                            $jsonvalue = 'slow';
                            break;
                        case constants::TTS_VERYSLOW:
                            $jsonvalue = 'veryslow';
                            break;
                        case constants::TTS_SSML:
                            $jsonvalue = 'SSML';
                            break;
                        case constants::TTS_NORMAL:
                        default:
                            $jsonvalue = 'normal';
                            break;
                    }
                    break;

                case 'layout':
                    switch ($fieldvalue) {
                        case constants::LAYOUT_HORIZONTAL:
                            $jsonvalue = 'horizontal';
                            break;
                        case constants::LAYOUT_VERTICAL:
                            $jsonvalue = 'vertical';
                            break;
                        case constants::LAYOUT_MAGAZINE:
                            $jsonvalue = 'magazine';
                            break;
                        case constants::LAYOUT_AUTO:
                        default:
                            $jsonvalue = 'auto';
                            break;
                    }
                    break;

                case 'boolean':
                    switch ($fieldvalue) {
                        case true:
                            $jsonvalue = 'yes';
                            break;
                        default:
                            $jsonvalue = 'no';
                    }
                    break;

                case 'file':
                    $fs = get_file_storage();
                    $filearea = $keycolumn['type'];
                    $files = $fs->get_area_files(
                        $this->modulecontext->id,
                        constants::M_COMPONENT,
                        $filearea,
                        $itemrecord->id
                    );

                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        if ($filename == '.') {
                            continue;
                        }
                        if ($filename == $fieldvalue) {
                            if (!isset($thefiles[$filearea])) {
                                $thefiles[$filearea] = [];
                            }
                            $thefiles[$filearea][$filename] = base64_encode($file->get_content());
                            $jsonvalue = $filename;
                            break;
                        }
                    }
                    break;
                case 'anonymousfile':
                    $fs = get_file_storage();
                    $filearea = $keycolumn['jsonname'];
                    $files = $fs->get_area_files(
                        $this->modulecontext->id,
                        constants::M_COMPONENT,
                        $filearea,
                        $itemrecord->id
                    );

                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        if ($filename == '.') {
                            continue;
                        }
                        if (!isset($thefiles[$filearea])) {
                            $thefiles[$filearea] = [];
                        }
                        $thefiles[$filearea][$filename] = base64_encode($file->get_content());
                    }
            }//end of switch keycolumn type
            // if the column has a DB field (file cols may not) we update it
            if ($keycolumn['dbname']) {
                $itemobj->{$keycolumn['jsonname']} = $jsonvalue;
            }
        }//end of loop through key cols

        // Include the files
        $itemobj->files = $thefiles;

        return $itemobj;
    }//end of export item function

}
