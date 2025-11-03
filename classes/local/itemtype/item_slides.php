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
use moodle_url;
use templatable;
use renderable;

/**
 * Renderable class for a slides item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_slides extends item {

    //the item type
    public const ITEMTYPE = constants::TYPE_SLIDES;

    public function from_record($itemrecord, $moduleinstance = false, $context = false) {
        parent::from_record($itemrecord, $moduleinstance, $context);
        $this->filemanageroptions['maxfiles'] = -1;
    }

     /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {

        $testitem = new \stdClass();
        $testitem = $this->get_common_elements($testitem);
        $testitem = $this->get_text_answer_elements($testitem);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        $imageserveurl = moodle_url::make_pluginfile_url(
            $this->context->id,
            constants::M_COMPONENT,
            constants::SLIDESFILES,
            $this->itemrecord->id,
            '/',
            '{filename}'
        );

        // Fetch all filenames in file area.
        $fs = get_file_storage();

        // Get all files in that file area.
        $files = $fs->get_area_files($this->context->id, 
        constants::M_COMPONENT,
        constants::SLIDESFILES,
        $this->itemrecord->id,
        'filepath, filename',
        false);

        // Extract the filenames into an array.
        $filenames = [];
        foreach ($files as $file) {
            $filenames[] = $file->get_filename();
        }

        $testitem->slidesmarkdown = preg_replace_callback(
            '/!\[[^\]]*\]\((?<filename>.*?)(?=\"|\))(?<optionalpart>\".*\")?\)/',
            function ($matches) use ($imageserveurl, $filenames) {
                $filename = trim($matches['filename']);

                // Skip if it's already a full URL (http/https).
                if (preg_match('/^https?:\/\//', $filename)) {
                    return $matches[0];
                }

                // Skip if the file does not exist in the file area.
                if (!in_array($filename, $filenames)) {
                    return $matches[0];
                }

                // Add base path (and escape spaces if needed)
                $newsrc = str_replace('{filename}', rawurlencode($filename), urldecode($imageserveurl));

                // Replace only the filename part
                return str_replace($filename, $newsrc, $matches[0]);
            },
            $this->itemrecord->{constants::SLIDES_MARKDOWN}
        );

        $testitem->selectedtheme = $this->itemrecord->{constants::SLIDETHEME};
        $testitem->selectedfontsize = $this->itemrecord->{constants::SLIDEFONTSIZE};

        return $testitem;
    }

    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{constants::SLIDES_MARKDOWN} == '') {
            $error->col = constants::SLIDES_MARKDOWN;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        //return false to indicate no error
        return false;
    }
    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns()
    {
        //get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'slidesmarkdown', 'type' => 'string', 'optional' => false, 'default' => [], 'dbname' => constants::SLIDES_MARKDOWN];
        $keycols['text2'] = ['jsonname' => 'slidestheme', 'type' => 'string', 'optional' => false, 'default' => 'black', 'dbname' => constants::SLIDETHEME];
        $keycols['text3'] = ['jsonname' => 'slidesfontsize', 'type' => 'string', 'optional' => false, 'default' => '32', 'dbname' => constants::SLIDEFONTSIZE];
        $keycols[constants::SLIDESFILES] = ['jsonname' => constants::SLIDESFILES, 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];

        return $keycols;
    }

    /*
  This function return the prompt that the generate method requires. 
  */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {

            case 'extract':
                $prompt = "Create a reveal.js presentation in markdown format to summarize and explain the following topic: [{text}]";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a reveal.js presentation in markdown format to summarize and explain the following topic: [{text}]";
                break;
        }
        return $prompt;
    }


}
