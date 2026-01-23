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

namespace mod_minilesson;

use stdClass;

/**
 * Class template_tag_manager
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_tag_manager
{
    /**
     * @var string Template tag table name
     */
    const DBTABLE = 'minilesson_template_tags';

    /**
     * @var int Tag indicated template containts single or multi item
     */
    const TYPE_SINGLEORMULTI = 1;

    /**
     * @var int Template item type tag
     */
    const TYPE_ITEMTYPE = 2;

    /**
     * @var int Predefined tag
     */
    const TYPE_PREDEFINED = 3;

    /**
     * get predefined tags
     * @return array
     */
    public static function get_predefined_tags()
    {
        return [
            'Speaking',
            'Listening',
            'Vocabulary Practice',
            'Video',
            'Grammar Instruction',
        ];
    }

    /**
     * get single or multiple tags
     * @return array
     */
    public static function get_singleormulti_tags()
    {
        return [
            'Single-Item',
            'Multi-Item',
        ];
    }

    /**
     * get itrmtype tags
     * @return array
     */
    public static function get_itemtype_tags()
    {
        return [
            constants::TYPE_MULTICHOICE,
            constants::TYPE_MULTIAUDIO,
            constants::TYPE_DICTATIONCHAT,
            constants::TYPE_DICTATION,
            constants::TYPE_SPEECHCARDS,
            constants::TYPE_LISTENREPEAT,
            constants::TYPE_PAGE,
            constants::TYPE_SHORTANSWER,
            constants::TYPE_SGAPFILL,
            constants::TYPE_LGAPFILL,
            constants::TYPE_TGAPFILL,
            constants::TYPE_PGAPFILL,
            constants::TYPE_SPACEGAME,
            constants::TYPE_FREEWRITING,
            constants::TYPE_FREESPEAKING,
            constants::TYPE_FLUENCY,
            constants::TYPE_PASSAGEREADING,
            constants::TYPE_AUDIOCHAT,
            constants::TYPE_WORDSHUFFLE,
            constants::TYPE_SCATTER,
            constants::TYPE_SLIDES,
            constants::TYPE_FICTION,
        ];
    }

    /**
     * store template tags
     * @param object $template The record of template
     * @param array $predefinedtags The list of tags
     *  selected from predefined tags {@see template_tag_manager::get_predefined_tags()}
     * @return void
     */
    public static function store_template_tags(stdClass $template, array $predefinedtags = [])
    {
        global $DB;

        if (!empty($template->id)) {
            $templateobject = json_decode($template->template);
            $tags = self::get_singleormulti_tags();
            if (!json_last_error()) {
                $deleterecords = [];

                // Process single/multi item tag.
                $tagtype = count($templateobject->items) > 1 ? $tags[1] : $tags[0];
                $tagrecordparams = ['templateid' => $template->id, 'type' => self::TYPE_SINGLEORMULTI];
                $tagrecord = $DB->get_record(self::DBTABLE, $tagrecordparams);
                if (!$tagrecord) {
                    $tagrecord = (object) $tagrecordparams;
                    $tagrecord->timecreated = time();
                    $tagrecord->tagname = $tagtype;
                    $tagrecord->id = $DB->insert_record(self::DBTABLE, $tagrecord);
                } elseif ($tagrecord->tagname != $tagtype) {
                    $tagrecord->tagname = $tagtype;
                    $tagrecord->timemodified = time();
                    $DB->update_record(self::DBTABLE, $tagrecord);
                }

                // Process item type tag.
                $templateitemtypes = array_column($templateobject->items, 'type', 'type');
                $templateitemtypes = array_intersect($templateitemtypes, self::get_itemtype_tags());

                $tagrecordparams = ['templateid' => $template->id, 'type' => self::TYPE_ITEMTYPE];
                $records = $DB->get_records(self::DBTABLE, $tagrecordparams);
                foreach ($records as $record) {
                    if (in_array($record->tagname, $templateitemtypes)) {
                        unset($templateitemtypes[$record->tagname]);
                    } else {
                        $deleterecords[] = $record;
                    }
                }

                foreach ($templateitemtypes as $itemtype) {
                    $tagrecord = (object) $tagrecordparams;
                    $tagrecord->timecreated = time();
                    $tagrecord->tagname = $itemtype;
                    $tagrecord->id = $DB->insert_record(self::DBTABLE, $tagrecord);
                }

                // Process predefined tag.
                $predefinedtags = array_combine($predefinedtags, $predefinedtags);
                $predefinedtags = array_intersect($predefinedtags, self::get_predefined_tags());
                $tagrecordparams = ['templateid' => $template->id, 'type' => self::TYPE_PREDEFINED];
                $records = $DB->get_records(self::DBTABLE, $tagrecordparams);
                foreach ($records as $record) {
                    if (in_array($record->tagname, $predefinedtags)) {
                        unset($predefinedtags[$record->tagname]);
                    } else {
                        $deleterecords[] = $record;
                    }
                }

                foreach ($predefinedtags as $itemtype) {
                    $tagrecord = (object) $tagrecordparams;
                    $tagrecord->timecreated = time();
                    $tagrecord->tagname = $itemtype;
                    $tagrecord->id = $DB->insert_record(self::DBTABLE, $tagrecord);
                }

                foreach ($deleterecords as $deleterecord) {
                    $DB->delete_records(self::DBTABLE, ['id' => $deleterecord->id]);
                }
            }
            $configobject = json_decode($template->config);
            if (!json_last_error()) {
                $tabobjects = self::get_current_tags($template->id);
                if (!empty($tabobjects)) {
                    $configobject->tags = array_column($tabobjects, 'tagname');
                } else {
                    $configobject->tags = [];
                }
                $template->config = json_encode($configobject, JSON_PRETTY_PRINT);
                $DB->update_record('minilesson_templates', $template);
            }
        }
    }

    /**
     * get current tags
     * @param int $templateid The template id
     * @param int $type The tag type
     * @return string[] Array of tag objects
     */
    public static function get_current_tags($templateid, $type = self::TYPE_PREDEFINED)
    {
        global $DB;
        return $DB->get_records_select(
            self::DBTABLE,
            'templateid = :templateid AND type = :type',
            ['templateid' => $templateid, 'type' => $type]
        );
    }
}
