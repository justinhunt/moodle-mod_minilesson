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

namespace minilessonitem_h5p;

use mod_minilesson\local\itemtype\item;

use core_h5p\player;
use mod_minilesson\constants;
use stdClass;

/**
 * Renderable class for a h5p item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_CONTENT];

    public const FILE = 'customfile1';

    // the item type
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {
        $itemrecord = $this->itemrecord;
        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        // Get the H5P File
         $mediaurls = $this->fetch_media_urls(self::FILE, $itemrecord);
        if ($mediaurls && count($mediaurls) > 0) {
            $config = (object) array_fill_keys(['frame', 'export', 'embed', 'copyright'], 0);
            $h5purl = $mediaurls[0];
            $testitem->h5purl = $h5purl;
            $testitem->h5pembedcode = player::display($h5purl, $config, true, 'mod_minilesson');
        } else {
            $testitem->h5purl = false;
        }

        // Max Score
        $testitem->totalmarks = $itemrecord->{constants::TOTALMARKS};

        return $testitem;
    }

    /**
     * Validates an import record for this item type. Runs after preprocessing, so any payload
     * files are already attached to the record as filearea arrays.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm)
    {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        // The item is useless without an .h5p package in the files payload.
        $haspackage = false;
        if (!empty($newrecord->{self::FILE}) && is_array((array) $newrecord->{self::FILE})) {
            foreach (array_keys((array) $newrecord->{self::FILE}) as $filename) {
                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'h5p') {
                    $haspackage = true;
                    break;
                }
            }
        }
        if (!$haspackage) {
            $error->col = self::FILE;
            $error->message = get_string('error:noh5ppackage', constants::M_COMPONENT);
            return $error;
        }

        // return false to indicate no error
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'Embeds an interactive H5P activity (e.g. a crossword, interactive video or quiz) inside the '
            . 'lesson; the H5P activity\'s own score contributes to the lesson grade, scaled by totalmarks. '
            . 'Use it only when a ready-made .h5p package file is available to upload - composing H5P content '
            . 'is outside the import API, so prefer the native item types unless the user has supplied a package.';
    }

    /**
     * The agent-facing import field spec for h5p.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'layout']);
        $fields['type']['example'] = 'h5p';

        $ownfields = [
            'totalmarks' => [
                'description' => 'The number of points the H5P activity\'s score contributes to the lesson: '
                    . 'the item grade is the learner\'s H5P score as a ratio of its maximum, scaled to this value. '
                    . 'Set this explicitly (e.g. 5 or 10): the authoring form default is 5, but the import '
                    . 'default is 0.',
                'example' => '10',
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => true,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload, '
                . 'which must contain the .h5p package. Required for this item type.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per H5P activity. The .h5p package file must be supplied '
                . '(base64) in the ' . self::FILE . ' file area - the import cannot create H5P content or pull '
                . 'it from the content bank, so only use this type when the user has provided a package. '
                . 'Set totalmarks to the points the activity should contribute to the lesson.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => self::FILE,
                    'description' => 'The H5P package to embed. Required.',
                    'filenames' => 'A single file with the .h5p extension, e.g. "activity.h5p".',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'h5p',
                        'name' => 'H5P activity',
                        'instructions' => 'Complete the task below.',
                        'totalmarks' => 10,
                        'filesid' => 1,
                    ],
                ],
                'files' => [
                    '1' => [
                        self::FILE => [
                            'activity.h5p' => '<base64 of the .h5p package supplied by the user>',
                        ],
                    ],
                ],
            ],
        ];
    }

    /*
    * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
    */
    public static function get_keycolumns()
    {
        // get the basic key columns and customize a little for instances of this item type
        $keycols = parent::get_keycolumns();
        // The jsonalias accepts data exported before the totalmarks jsonname was introduced.
        $keycols['int1'] = ['jsonname' => 'totalmarks', 'jsonalias' => 'int1', 'type' => 'int',
            'optional' => true, 'default' => 0, 'dbname' => constants::TOTALMARKS];
        $keycols[self::FILE] = ['jsonname' => self::FILE, 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];

        return $keycols;
    }

    /*
   This function returns the prompt that the generate method requires.
   */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod)
    {
        switch ($generatemethod) {
            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            case 'extract':
            default:
                $prompt = "H5P activities can not be created by AI. You should remove H5P from the item template";
                break;
        }
        return $prompt;
    }

    public function prepare_result(stdClass $result, stdClass $itemquizdata) {
        $result->hascorrectanswer = false;
        $result->hasincorrectanswer = false;
        $result->hasanswerdetails = false;
    }

}
