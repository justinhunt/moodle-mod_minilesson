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

namespace mod_minilesson\local\itemtype;

use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_passagereading extends item {

    // The item type.
    public const ITEMTYPE = constants::TYPE_PASSAGEREADING;

     /**
      * The class constructor.
      *
      */
    public function __construct($itemrecord, $moduleinstance=false, $context = false) {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }


    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {

        $testitem = parent::export_for_template($output);
        $testitem = $this->set_layout($testitem);
        $testitem->alternates = $this->itemrecord->{constants::ALTERNATES};
        $testitem->passagetext = $this->itemrecord->{constants::READINGPASSAGE};
        $testitem->passagehtml = \mod_minilesson\aitranscriptutils::render_passage($this->itemrecord->{constants::READINGPASSAGE});

        // Do we need a streaming token?
        $alternatestreaming = get_config(constants::M_COMPONENT, 'alternatestreaming');
        $isenglish = strpos($this->moduleinstance->ttslanguage, 'en') === 0;
        if ($isenglish || true) {
            $tokenobject = utils::fetch_streaming_token($this->moduleinstance->region);
                if ($tokenobject) {
                    $testitem->speechtoken = $tokenobject->token;
                    $testitem->speechtokenregion = $tokenobject->region;
                    $testitem->speechtokenvalidseconds = $tokenobject->validseconds;
                    $testitem->speechtokentype = $tokenobject->tokentype;
                } else {
                    $testitem->speechtoken = false;
                    $testitem->speechtokenregion = '';
                    $testitem->speechtokenvalidseconds = 0;
                    $testitem->speechtokentype = '';
                }
            if ($alternatestreaming) {
                $testitem->forcestreaming = true;
            }
        }

        //add a few things to enable the saving of uploaded audio (on S3)
        $testitem->savemedia = 1; // For now this is disabled
        $testitem->savemediaregion = $this->moduleinstance->region;
        $testitem->transcode = 1;
        $testitem->expiredays = 365;

        // Cloudpoodll.
        $maxtime = $this->itemrecord->timelimit;
        $testitem = $this->set_cloudpoodll_details($testitem, $maxtime);

        return $testitem;
    }

    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{constants::READINGPASSAGE} == '') {
            $error->col = constants::READINGPASSAGE;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns() {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => constants::TOTALMARKS];
        $keycols['text1'] = ['jsonname' => 'passage', 'type' => 'string', 'optional' => false, 'default' => '', 'dbname' => constants::READINGPASSAGE];
        $keycols['text2'] = ['jsonname' => 'alternates', 'type' => 'stringarray', 'optional' => true, 'default' => [], 'dbname' => constants::ALTERNATES];
        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires. 
    */
    public static function aigen_fetch_prompt ($itemtemplate, $generatemethod) {
        switch($generatemethod) {

            case 'extract':
                $prompt = "Create a {language} passage that is a 5 or 6 sentence summarisation of the following text: [{text}]. ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Generate a passage of text in {language} suitable for {level} level learners on the topic of: [{topic}] " . PHP_EOL;
                $prompt .= "The passage should be about 6 sentences long. ";
                break;
        }
        return $prompt;
    }

}
