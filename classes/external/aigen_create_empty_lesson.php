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

namespace mod_minilesson\external;

use context_course;
use Exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\utils;
use stdClass;

/**
 * Class aigen_create_empty_lesson
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_create_empty_lesson extends external_api {

    public static function execute_parameters() {
        $config = get_config(constants::M_COMPONENT);
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'title' => new external_value(PARAM_TEXT, 'Module title'),
            'section' => new external_value(PARAM_INT, 'Course Section Number', VALUE_DEFAULT, 1),
            'pagelayout' => new external_value(PARAM_TEXT, 'Page layout', VALUE_DEFAULT, 'standard'),
            'showqtitles' => new external_value(PARAM_INT, 'Show item titles in lesson', VALUE_DEFAULT, 0),
            'maxattempts' => new external_value(PARAM_INT, 'Max Attempts', VALUE_DEFAULT, 0),
            'ttslanguage' => new external_value(PARAM_TEXT, 'Target/Voice Language', VALUE_DEFAULT, $config->ttslanguage),
            'region' => new external_value(PARAM_TEXT, 'AWS Region', VALUE_DEFAULT, $config->awsregion),
            'transcriber' => new external_value(PARAM_INT, 'Transcriber', VALUE_DEFAULT, $config->transcriber),
            'richtextprompt' => new external_value(PARAM_INT, 'Text and Media', VALUE_DEFAULT, $config->prompttype),
            'containerwidth' => new external_value(PARAM_TEXT, 'Container width', VALUE_DEFAULT, $config->containerwidth),
            'activitylink' => new external_value(PARAM_INT, 'Link to next activity', VALUE_DEFAULT, 0),
            'foriframe' => new external_value(PARAM_INT, 'for iframe', VALUE_DEFAULT, 0),
            'grade' => new external_value(PARAM_INT, 'Grade', VALUE_DEFAULT, 0),
            'visible' => new external_value(PARAM_INT, 'Visible', VALUE_DEFAULT, 0),
            'nativelang' => new external_value(PARAM_TEXT, 'Native Language', VALUE_DEFAULT, $config->nativelang),
        ]);
    }

    /**
     * Create Empty minilesson
     * @param int $courseid
     * @param string $title
     * @param int $section
     * @param string $pagelayout
     * @param int $showqtitles
     * @param int $maxattempts
     * @param string $ttslanguage
     * @param string $region
     * @param int $transcriber
     * @param int $richtextprompt
     * @param string $containerwidth
     * @param int $activitylink
     * @param int $foriframe
     * @param int $grade
     * @param int $visible
     * @return array
     */
    public static function execute($courseid, $title, $section, $pagelayout, $showqtitles,
            $maxattempts, $ttslanguage, $region, $transcriber, $richtextprompt, $containerwidth,
            $activitylink, $foriframe, $grade, $visible, $nativelang) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'title' => $title,
            'section' => $section,
            'pagelayout' => $pagelayout,
            'showqtitles' => $showqtitles,
            'maxattempts' => $maxattempts,
            'ttslanguage' => $ttslanguage,
            'region' => $region,
            'transcriber' => $transcriber,
            'richtextprompt' => $richtextprompt,
            'containerwidth' => $containerwidth,
            'activitylink' => $activitylink,
            'foriframe' => $foriframe,
            'grade' => $grade,
            'visible' => $visible,
            'nativelang' => $nativelang
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        require_capability('mod/minilesson:addinstance', $context);

        $course = get_course($params['courseid']);
        $moduledata = new stdClass();
        $moduledata->name = $params['title'];
        $moduledata->modulename = "minilesson";
        $moduledata->pagelayout = $params['pagelayout'];
        $moduledata->showqtitles = $params['showqtitles'];
        $moduledata->maxattempts = $params['maxattempts'];
        $moduledata->ttslanguage = $params['ttslanguage'];
        $moduledata->region = $params['region'];
        $moduledata->transcriber = $params['transcriber'];
        $moduledata->richtextprompt = $params['richtextprompt'];
        $moduledata->containerwidth = $params['containerwidth'];
        $moduledata->activitylink = $params['activitylink'];
        $moduledata->foriframe = $params['foriframe'];
        $moduledata->grade = $params['grade'];
        $moduledata->visible = $params['visible'];
        $moduledata->nativelang = $params['nativelang'];

        try {
            $cmid = utils::create_instance($moduledata, $course, $params['section']);
            return [
                'success' => true,
                'cmid' => $cmid,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'errormsg' => $e->getMessage(),
            ];
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True Or False'),
            'cmid' => new external_value(PARAM_INT, 'Course Module ID', VALUE_OPTIONAL),
            'errormsg' => new external_value(PARAM_RAW, 'Error message when lesson not create', VALUE_OPTIONAL),
        ]);
    }
}
