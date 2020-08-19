<?php

namespace mod_poodlltime\rsquestion;

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
 * Internal library of functions for module poodlltime
 *
 * All the poodlltime specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_poodlltime
 * @copyright  COPYRIGHTNOTICE
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \mod_poodlltime\constants;

class helper
{

//creates a "unique" slide pair key so that backups and restores won't stuff things
  public static function create_rsquestionkey()
    {
        global $CFG;
        $prefix = $CFG->wwwroot . '@';
        return uniqid($prefix, true);
    }


    public static function move_item($poodlltime, $moveitemid, $direction)
    {
        global $DB;

        switch ($direction) {
            case 'up':
                $sort = 'itemorder ASC';
                break;
            case 'down':
                $sort = 'itemorder DESC';
                break;
            default:
                //inconceivable that we should ever arrive here.
                return;
        }

        if (!$items = $DB->get_records(constants::M_QTABLE, array('poodlltime' => $poodlltime->id), $sort)) {
            print_error("Could not fetch items for ordering");
            return;
        }

        $prioritem = null;
        foreach ($items as $item) {
            if ($item->id == $moveitemid && $prioritem != null) {
                $currentitemorder = $item->itemorder;
                $item->itemorder = $prioritem->itemorder;
                $prioritem->itemorder = $currentitemorder;

                //Set the new sort order
                $DB->set_field(constants::M_QTABLE, 'itemorder', $item->itemorder, array('id' => $item->id));
                $DB->set_field(constants::M_QTABLE, 'itemorder', $prioritem->itemorder, array('id' => $prioritem->id));
                break;
            }//end of if
            $prioritem = $item;
        }//end of for each
    }//end of move item function


    public static function delete_item($poodlltime, $itemid, $context)
    {
        global $DB;
        $ret = false;

        if (!$DB->delete_records(constants::M_QTABLE, array('id' => $itemid))) {
            print_error("Could not delete item");
            return $ret;
        }
        //remove files
        $fs = get_file_storage();

        $fileareas = array(constants::TEXTPROMPT_FILEAREA,
            constants::TEXTPROMPT_FILEAREA . '1',
            constants::TEXTPROMPT_FILEAREA . '2',
            constants::TEXTPROMPT_FILEAREA . '3',
            constants::TEXTPROMPT_FILEAREA . '4');

        foreach ($fileareas as $filearea) {
            $fs->delete_area_files($context->id, 'mod_poodlltime', $filearea, $itemid);
        }
        $ret = true;
        return $ret;
    }


    public static function fetch_editor_options($course, $modulecontext)
    {
        $maxfiles = 99;
        $maxbytes = $course->maxbytes;
        return array('trusttext' => 0,'noclean'=>1, 'subdirs' => true, 'maxfiles' => $maxfiles,
            'maxbytes' => $maxbytes, 'context' => $modulecontext);
    }

    public static function fetch_filemanager_options($course, $maxfiles = 1)
    {
        $maxbytes = $course->maxbytes;
        return array('subdirs' => true, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes, 'accepted_types' => array('audio', 'image'));
    }

    public static function update_insert_question($poodlltime, $data, $edit, $context, $cm ,$editoroptions, $filemanageroptions) {
        global $DB, $USER;

        $ret = new \stdClass;
        $ret->error = false;
        $ret->message = '';
        $ret->payload = null;

        $theitem = new \stdClass;
        $theitem->poodlltime = $poodlltime->id;
        $theitem->id = $data->itemid;
        $theitem->visible = $data->visible;
        $theitem->itemorder = $data->itemorder;
        $theitem->type = $data->type;
        $theitem->name = $data->name;
        $theitem->modifiedby = $USER->id;
        $theitem->timemodified = time();

        //first insert a new item if we need to
        //that will give us a itemid, we need that for saving files
        if (!$edit) {

            $theitem->{constants::TEXTQUESTION} = '';
            $theitem->timecreated = time();
            $theitem->createdby = $USER->id;

            //get itemorder
            $comprehensiontest = new \mod_poodlltime\comprehensiontest($cm);
            $currentitems = $comprehensiontest->fetch_items();
            if (count($currentitems) > 0) {
                $lastitem = array_pop($currentitems);
                $itemorder = $lastitem->itemorder + 1;
            } else {
                $itemorder = 1;
            }
            $theitem->itemorder = $itemorder;

            //create a rsquestionkey
            $theitem->rsquestionkey = \mod_poodlltime\rsquestion\helper::create_rsquestionkey();

            //try to insert it
            if (!$theitem->id = $DB->insert_record(constants::M_QTABLE, $theitem)) {
                $ret->error = true;
                $ret->message = "Could not insert poodlltime item!";
                return $ret;
            }
        }//enf of of !edit

        //handle all the text questions
        //if its an editor field, do this
        if (property_exists($data, constants::TEXTQUESTION . '_editor')) {
            $data = file_postupdate_standard_editor($data, constants::TEXTQUESTION, $editoroptions, $context,
                    constants::M_COMPONENT, constants::TEXTQUESTION_FILEAREA, $theitem->id);
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
            $theitem->{constants::TEXTQUESTION_FORMAT} = $data->{constants::TEXTQUESTION_FORMAT};
            //if its a text field, do this
        } else if (property_exists($data, constants::TEXTQUESTION)) {
            $theitem->{constants::TEXTQUESTION} = $data->{constants::TEXTQUESTION};
        }

        //save correct answer if we have one
        if (property_exists($data, constants::CORRECTANSWER)) {
            $theitem->{constants::CORRECTANSWER} = $data->{constants::CORRECTANSWER};
        }

        //save correct answer if we have one
        if (property_exists($data, constants::CORRECTANSWER)) {
            $theitem->{constants::CORRECTANSWER} = $data->{constants::CORRECTANSWER};
        }

        //save text answers and other data in custom text
        //could be editor areas
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMTEXT; $anumber++) {
            //if its an editor field, do this
            if (property_exists($data, constants::TEXTANSWER . $anumber . '_editor')) {
                $data = file_postupdate_standard_editor($data, constants::TEXTANSWER . $anumber, $editoroptions, $context,
                        constants::M_COMPONENT, constants::TEXTANSWER_FILEAREA . $anumber, $theitem->id);
                $theitem->{constants::TEXTANSWER . $anumber} = $data->{'customtext' . $anumber};
                $theitem->{constants::TEXTANSWER . $anumber . 'format'} = $data->{constants::TEXTANSWER . $anumber . 'format'};
                //if its a text field, do this
            } else if (property_exists($data, constants::TEXTANSWER . $anumber)) {
                $theitem->{constants::TEXTANSWER . $anumber} = $data->{constants::TEXTANSWER . $anumber};
            }
        }

        //we might have other customdata
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMDATA; $anumber++) {
            if (property_exists($data, constants::CUSTOMDATA . $anumber)) {
                $theitem->{constants::CUSTOMDATA . $anumber} = $data->{constants::CUSTOMDATA . $anumber};
            }
        }

        //we might have custom int
        for ($anumber = 1; $anumber <= constants::MAXCUSTOMINT; $anumber++) {
            if (property_exists($data, constants::CUSTOMINT . $anumber)) {
                $theitem->{constants::CUSTOMINT . $anumber} = $data->{constants::CUSTOMINT . $anumber};
            }
        }

        //now update the db once we have saved files and stuff
        if (!$DB->update_record(constants::M_QTABLE, $theitem)) {
            $ret->error = true;
            $ret->message = "Could not update poodlltime item!";
            return $ret;
        }else{
            $ret->item = $theitem;
            return $ret;
        }
    }//end of edit_insert_question

}
