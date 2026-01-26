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
 * Renderable class for a fiction item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_fiction extends item
{
    /** @var string */
    public const ITEMTYPE = constants::TYPE_FICTION;

    /**
     * Load data from record.
     *
     * @param object $itemrecord The item record from the database.
     * @param object|false $moduleinstance The module instance record.
     * @param object|false $context The context object.
     * @return void
     */
    public function from_record($itemrecord, $moduleinstance = false, $context = false)
    {
        parent::from_record($itemrecord, $moduleinstance, $context);
        $this->filemanageroptions['maxfiles'] = -1;
    }


    /**
     * The class constructor.
     * @param object $itemrecord The item record from the database.
     * @param object|false $moduleinstance The module instance record.
     * @param object|false $context The context object.
     * @return void
     */
    public function __construct($itemrecord, $moduleinstance = false, $context = false)
    {
        parent::__construct($itemrecord, $moduleinstance, $context);
        $this->needs_speechrec = true;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {
        global $USER;

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->region = $this->region;

        $imageserveurl = urldecode(\moodle_url::make_pluginfile_url(
            $this->context->id,
            constants::M_COMPONENT,
            constants::FICTIONFILES,
            $this->itemrecord->id,
            '/',
            '{filename}'
        ));

        // Fetch all filenames in file area.
        $fs = get_file_storage();

        // Get all files in that file area.
        $files = $fs->get_area_files(
            $this->context->id,
            constants::M_COMPONENT,
            constants::FICTIONFILES,
            $this->itemrecord->id,
            'filepath, filename',
            false
        );

        // Extract the filenames into an array.
        $filenames = [];
        foreach ($files as $file) {
            $filenames[] = $file->get_filename();
        }
        $testitem->hasmedia = !empty($filenames);
        $testitem->filenamesmap = [];
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $testitem->filenamesmap[] = [
                'filekey' => strtolower(str_replace(' ', '_', pathinfo($filename, PATHINFO_FILENAME))),
                'fileurl' => str_replace('{filename}', rawurlencode($filename), $imageserveurl),
            ]; 
        }

        // Process yarn text for files in files area.
        $fictionyarn = preg_replace_callback(
            '/<<(?:picture|audio|video)\s+(?<filename>[^>]+)>>/',
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

                // Add base path (and escape spaces if needed).
                $newsrc = str_replace('{filename}', rawurlencode($filename), $imageserveurl);

                // Replace only the filename part.
                return str_replace($filename, $newsrc, $matches[0]);
            },
            $this->itemrecord->{constants::FICTION_YARN}
        );

        // Weird characters can break things like tables, so clean it a bit.
        $fictionyarn = $this->sanitize_yarn($fictionyarn);

        // Set it to output.
        $testitem->fictionyarn = $fictionyarn;

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

        // Presentation Mode - plain or mobilechat.
        $testitem->presention_plain = empty($this->itemrecord->{constants::FICTION_PRESENTATION_MODE});
        $testitem->presention_mobilechat = !empty($this->itemrecord->{constants::FICTION_PRESENTATION_MODE});

        // Flowthrough mode.
        $testitem->flowthroughmode = $this->itemrecord->{constants::FICTION_FLOWTHROUGH_MESSAGES} ? true : false;

        // Show non-options.
        $testitem->shownonoptions = $this->itemrecord->{constants::FICTION_SHOW_NONOPTIONS} ? true : false;

        // Pass in user data for display in the story
        $testitem->userfirstname = $USER->firstname;
        $testitem->userlastname = $USER->lastname;
        $testitem->userfullname = fullname($USER);
 
        // Cloudpoodll.
        $testitem = $this->set_cloudpoodll_details($testitem);
                return $testitem;
    }

    /**
     * Sanitize yarn markdown to remove problematic characters.
     *
     * @param string $yarn The yarn to sanitize.
     * @return string The sanitized yarn.
     */
    public function sanitize_yarn($yarn) {
        // 1. Remove zero-width chars (Space-efficient way to include the BOM)
        $yarn = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $yarn);

        // 2. Replace ALL variations of non-breaking spaces
        // This targets U+00A0 (NBSP) and U+202F (Narrow NBSP) using Unicode hex
        $yarn = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $yarn);

        // 3 Normalize all horizontal whitespace to regular spaces
        $yarn = preg_replace('/\h/u', ' ', $yarn);

        // 4. Normalize Line Endings
        // Converts Windows \r\n to Linux \n so your regex logic is consistent
        $yarn = str_replace("\r\n", "\n", $yarn);

        // 5. Trim trailing whitespace from each line
        $yarn = preg_replace('/\h+$/m', '', $yarn);

        return $yarn;
    }

    /**
     * Validate import data for this item type.
     *
     * @param object $newrecord The new record being imported.
     * @param object $cm The course module object.
     * @return \stdClass|false An error object if validation fails, or false if no error.
     */
    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if ($newrecord->{constants::FICTION_YARN} == '') {
            $error->col = constants::FICTION_YARN;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }
    /**
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type.
     * @return array
     */
    public static function get_keycolumns()
    {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = [
            'jsonname' => 'fictionyarn',
            'type' => 'string',
            'optional' => false,
            'default' => [],
            'dbname' => constants::FICTION_YARN,
        ];

        $keycols['int1'] = [
            'jsonname' => 'presentationmode',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::FICTION_PRESENTATION_MODE,
        ];

        $keycols['int2'] = [
            'jsonname' => 'flowthroughmode',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::FICTION_FLOWTHROUGH_MESSAGES,
        ];

        $keycols['int3'] = [
            'jsonname' => 'shownonoptions',
            'type' => 'int',
            'optional' => true,
            'default' => 0,
            'dbname' => constants::FICTION_SHOW_NONOPTIONS,
        ];

        $keycols[constants::FICTIONFILES] = [
            'jsonname' => constants::FICTIONFILES,
            'type' => 'anonymousfile',
            'optional' => true,
            'default' => null,
            'dbname' => false,
        ];

        return $keycols;
    }

    /**
     * This function return the prompt that the generate method requires.
     * @param string $itemtemplate The item template being used.
     * @param string $generatemethod The method of generation.
     * @return string The prompt to be used.
     */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create an adventure fiction story in yarn format on the topic of: [{topic}] ";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create an adventure fiction story in yarn format on the topic of: [{topic}] ";
                break;
        }
        return $prompt;
    }
}
