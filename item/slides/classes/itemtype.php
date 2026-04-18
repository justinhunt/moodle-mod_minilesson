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

namespace minilessonitem_slides;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use moodle_url;

/**
 * Renderable class for a slides item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    public const MARKDOWN = 'customtext1';
    public const FULLSCREEN = 'customint1';
    public const MARKDOWN_DEFAULT = "# Slide 1 Title\n\nYour content here. Use markdown syntax to format text and add images.\n\n---\n\n# Slide 2 Title\n\nMore content here. You can add as many slides as you need.\n";
    public const SLIDETHEME = 'customtext2';
    public const SLIDEFONTSIZE = 'customtext3';
    public const FILES = 'customfile1';

    //the item type
    public function from_record($itemrecord, $moduleinstance = false, $context = false)
    {
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

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->region = $this->region;

        $imageserveurl = moodle_url::make_pluginfile_url(
            $this->context->id,
            constants::M_COMPONENT,
            self::FILES,
            $this->itemrecord->id,
            '/',
            '{filename}'
        );

        // Fetch all filenames in file area.
        $fs = get_file_storage();

        // Get all files in that file area.
        $files = $fs->get_area_files(
            $this->context->id,
            constants::M_COMPONENT,
            self::FILES,
            $this->itemrecord->id,
            'filepath, filename',
            false
        );

        // Extract the filenames into an array.
        $filenames = [];
        foreach ($files as $file) {
            $filenames[] = $file->get_filename();
        }

        // Process markdown for files in files area.
        $slidesmarkdown = preg_replace_callback(
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
            $this->itemrecord->{self::MARKDOWN}
        );

        // Weird characters can break things like tables, so clean it a bit.
        $slidesmarkdown = self::sanitize_markdown($slidesmarkdown);

        // Process markdown layouts (e.g. ::: 2cols -> <div class="ml_slides_2cols">)
        $slidesmarkdown = self::process_layout_markdown($slidesmarkdown);

        // Set it to output.
        $testitem->slidesmarkdown = $slidesmarkdown;

        $testitem->selectedtheme = $this->itemrecord->{self::SLIDETHEME};
        $testitem->selectedfontsize = $this->itemrecord->{self::SLIDEFONTSIZE};
        $testitem->fullscreen = $this->itemrecord->{self::FULLSCREEN};


        return $testitem;
    }

    public static function sanitize_markdown($md)
    {

        // Remove zero-width chars.
        $md = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $md);

        // Replace NBSP with normal space.
        $md = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], " ", $md);

        // Trim trailing spaces and tabs but preserve newlines.
        $md = preg_replace('/[ \t]+$/m', '', $md);

        return $md;
    }

    /**
     * Replaces ::: class syntax with divs to create grid layouts
     */
    public static function process_layout_markdown($md)
    {
        // Replace opening tags. Use [ \t]* so we don't accidentally consume newlines and merge previous slides together!
        // Tolerate \r before end-of-line in case of Windows CRLF line endings.
        // Inject \n\n around the block so that Marked.js isolates the HTML elements and resumes standard markdown-parsing inside them.
        $md = preg_replace('/^:::[ \t]*([a-zA-Z0-9_\-]+)[ \t]*\r?$/m', "\n\n<div class=\"ml_slides_$1\">\n\n", $md);
        
        // Replace closing tags
        $md = preg_replace('/^:::[ \t]*\r?$/m', "\n\n</div>\n\n", $md);

        return $md;
    }

    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{self::MARKDOWN} == '') {
            $error->col = self::MARKDOWN;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }
    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns()
    {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'slidesmarkdown', 'type' => 'string', 'optional' => false, 'default' => [], 'dbname' => self::MARKDOWN];
        $keycols['text2'] = ['jsonname' => 'slidestheme', 'type' => 'string', 'optional' => false, 'default' => 'black', 'dbname' => self::SLIDETHEME];
        $keycols['text3'] = ['jsonname' => 'slidesfontsize', 'type' => 'string', 'optional' => false, 'default' => '32', 'dbname' => self::SLIDEFONTSIZE];
        $keycols['int1'] = ['jsonname' => 'slidesfullscreen', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::FULLSCREEN];
        $keycols[self::FILES] = ['jsonname' => self::FILES, 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];


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
