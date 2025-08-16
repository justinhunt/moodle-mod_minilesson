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
 * This file keeps track of upgrades to the minilesson module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Execute minilesson upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_minilesson_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

    // Add question titles to minilesson table
    if ($oldversion < 2020090700) {
        $activitytable = new xmldb_table(constants::M_TABLE);

        // Define field showqtitles to be added to minilesson\
        $showqtitles = new xmldb_field('showqtitles', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');

        // add showqtitles field to minilesson table
        if (!$dbman->field_exists($activitytable, $showqtitles)) {
            $dbman->add_field($activitytable, $showqtitles);
        }
        upgrade_mod_savepoint(true, 2020090700, 'minilesson');
    }

    // Add passagehash to questions table
    if ($oldversion < 2020100200) {
        $qtable = new xmldb_table(constants::M_QTABLE);

        // Define field showqtitles to be added to minilesson\
        $field = new xmldb_field('passagehash', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED, XMLDB_NOTNULL, null);

        // add showqtitles field to minilesson table
        if (!$dbman->field_exists($qtable, $field)) {
            $dbman->add_field($qtable, $field);
        }
        upgrade_mod_savepoint(true, 2020100200, 'minilesson');
    }

    // Add rich text prompt flag to minilesson table
    if ($oldversion < 2020122300) {
        $activitytable = new xmldb_table(constants::M_TABLE);

        // Define field richtextprompt to be added to minilesson
        $richtextprompt = new xmldb_field('richtextprompt', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::M_PROMPT_RICHTEXT);

        // add richtextprompt field to minilesson table
        if (!$dbman->field_exists($activitytable, $richtextprompt)) {
            $dbman->add_field($activitytable, $richtextprompt);
        }
        upgrade_mod_savepoint(true, 2020122300, 'minilesson');
    }

    // Add TTS item  to minilesson table
    if ($oldversion < 2021021800) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define fields itemtts and itemtts voice to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('itemttsvoice', XMLDB_TYPE_CHAR, '255', XMLDB_UNSIGNED);
        $fields[] = new xmldb_field('itemtts', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2021021800, 'minilesson');
    }

    // Add TTS option to minilesson table
    if ($oldversion < 2021031500) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define field itemtts to be added to minilesson
        $itemttsoption = new xmldb_field('itemttsoption', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::TTS_NORMAL);

        // add richtextprompt field to minilesson table
        if (!$dbman->field_exists($table, $itemttsoption)) {
            $dbman->add_field($table, $itemttsoption);
        }
        upgrade_mod_savepoint(true, 2021031500, 'minilesson');
    }
    // Add Question TextArea item  to minilesson table
    if ($oldversion < 2021052200) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define fields itemtts and itemtts voice to be added to minilesson
        // Item text area was added in 2021041500, but it was missed from install.xml ... so we double check it in this version
        $fields = [];
        $fields[] = new xmldb_field('itemtextarea', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Some passage hashs seem to be empty. This script will search for empty (and wrong) ones and update them
        $instances = $DB->get_records(constants::M_TABLE);
        if($instances){
            foreach ($instances as $moduleinstance){
                \mod_minilesson\local\itemform\helper::update_all_langmodels($moduleinstance);
            }
        }
        upgrade_mod_savepoint(true, 2021052200, 'minilesson');
    }

    // Add foriframe option to minilesson table
    if ($oldversion < 2021053100) {
        $table = new xmldb_table(constants::M_TABLE);

        // Define field itemtts to be added to minilesson
        $field = new xmldb_field('foriframe', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // add richtextprompt field to minilesson table
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2021053100, 'minilesson');
    }

    // Add alternatives  to minilesson table
    if ($oldversion < 2021081801) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define alternatives field to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('alternatives', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('phonetic', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2021081801, 'minilesson');
    }

    // Update all the phonetic fields in minilesson
    if ($oldversion < 2021082701) {

        // this will add phonetic info for speechy items that have none currently
        $instances = $DB->get_records(constants::M_TABLE);
        if($instances){
            foreach ($instances as $moduleinstance){
                \mod_minilesson\local\itemform\helper::update_all_phonetic($moduleinstance);
            }
        }

        upgrade_mod_savepoint(true, 2021082701, 'minilesson');
    }

    // Add TTS autoplay to minilesson q table
    if ($oldversion < 2022012001) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define fields itemttsautoplay and layout to be added to minilesson q table
        $fields = [];
        $fields[] = new xmldb_field('itemttsautoplay', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('layout', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, constants::LAYOUT_AUTO);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022012001, 'minilesson');
    }

    // Add YT Video Clip to minilesson question table
    if ($oldversion < 2022020300) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define YT clip fields to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('itemytid', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('itemytstart', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('itemytend', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022020300, 'minilesson');
    }

    // Add open and close dates to the activity
    if ($oldversion < 2022020800) {
        $table = new xmldb_table(constants::M_TABLE);

        $fields = [];
        $fields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10, XMLDB_NOTNULL, null, 0);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022020800, 'minilesson');
    }
    // redo the prompt/response =>
    if ($oldversion < 2022021400) {
        $questions = $DB->get_records(constants::M_QTABLE);
        foreach($questions as $question){
            $sentences = explode(PHP_EOL, $question->customtext1);
            $updaterequired = false;
            $newsentences = [];
            foreach($sentences as $sentence){
                $sentencebits = explode('|', $sentence);
                if (count($sentencebits) > 1) {
                    $updaterequired = true;
                    $audioprompt = trim($sentencebits[1]);
                    $correctresponse = trim($sentencebits[0]);
                    $textprompt = $correctresponse;
                    $newsentences[] = $audioprompt . '|' . $correctresponse .'|' . $textprompt;
                }else{
                    $newsentences[] = $sentence;
                }//end of if count
            }//end of for sentences
            if($updaterequired){
                $updatetext = implode(PHP_EOL, $newsentences);
                $DB->update_record(constants::M_QTABLE, ['id' => $question->id, 'customtext1' => $updatetext]);
            }
        }

        upgrade_mod_savepoint(true, 2022021400, 'minilesson');
    }

    // Add TTS Dialog to minilesson question table
    if ($oldversion < 2022032900) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define field itemttsdialog
        $fields = [];
        $fields[] = new xmldb_field('itemttsdialog', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('itemttsdialogopts', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2022032900, 'minilesson');
    }

    // Add lessonkey  to minilesson table
    if ($oldversion < 2022041800) {
        $table = new xmldb_table(constants::M_TABLE);

        // Define fields ,lessonkey,to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('lessonkey', XMLDB_TYPE_CHAR, '255', null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2022041800, 'minilesson');
    }

    // Add containerwidth  to minilesson table
    if ($oldversion < 2022053102) {
        $table = new xmldb_table(constants::M_TABLE);

        // Define fields ,lessonkey,to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('containerwidth', XMLDB_TYPE_CHAR, '255', null, true, null, 'compact');
        $fields[] = new xmldb_field('lessonfont', XMLDB_TYPE_CHAR, '255', null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // add missign default if its missing
        $vfields = [];
        $vfields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, null, null, 0);
        $vfields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, null, null, 0);

        // Add fields
        foreach ($vfields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_default($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2022053102, 'minilesson');
    }

    // Add iteminstructions to minilesson table
    if ($oldversion < 2023011800) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define fields ,lessonkey,to be added to minilesson
        $fields = [];
        $fields[] = new xmldb_field('iteminstructions', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2023011800, 'minilesson');
    }

    // Add TTS Passage to minilesson question table
    if ($oldversion < 2023011801) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define field itemttspassage
        $fields = [];
        $fields[] = new xmldb_field('itemttspassage', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('itemttspassageopts', XMLDB_TYPE_TEXT, null, null, null, null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2023011801, 'minilesson');
    }

      // Added Gap fill questions with time limits
    if ($oldversion < 2023041200) {
        $table = new xmldb_table(constants::M_QTABLE);

        // Define field item timelimit
        $fields = [];
        $fields[] = new xmldb_field('timelimit', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, null, null, 0);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2023041200, 'minilesson');

    }

    if($oldversion < 2023051300){
        // fields to change the notnull definition for] viewstart and viewend
        $table = new xmldb_table(constants::M_TABLE);
        $fields = [];
        $fields[] = new xmldb_field('viewstart', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('viewend', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $DB->set_field(constants::M_TABLE, 'viewstart', 0, ['viewstart' => null]);
        $DB->set_field(constants::M_TABLE, 'viewend', 0, ['viewend' => null]);

        // Alter fields
        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }

        // fix up messed up timelimit, if it had wrong null value or was on activity table
        $table = new xmldb_table(constants::M_QTABLE);

        // Define field item timelimit
        $field = new xmldb_field('timelimit', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        // if its not there add it, if it is there, change the null decl
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }else{
            $DB->set_field(constants::M_QTABLE, 'timelimit', 0, ['timelimit' => null]);
            $dbman->change_field_notnull($table, $field);
        }

        // remove field from activity table if it was there (mistake)
        $table = new xmldb_table(constants::M_TABLE);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2023051300, 'minilesson');
    }

    if($oldversion < 2023092600){
        // The norwegian language-locale code nb-no is not supported by all STT engines in Poodll, and no-no is. So updating
        $DB->set_field(constants::M_TABLE, 'ttslanguage', constants::M_LANG_NONO, ['ttslanguage' => constants::M_LANG_NBNO]);
        upgrade_mod_savepoint(true, 2023092600, 'minilesson');
    }

    $newversion = 2023111400;
    if ($oldversion < $newversion) {
        // Add auth table.
        $table = new xmldb_table('minilesson_auth');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('secret', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);

        // Add keys and index.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('user_id', XMLDB_INDEX_UNIQUE, ['user_id']);

        // Create table if it does not exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, $newversion, 'minilesson');
    }

    $newversion = 2024062200;
    if ($oldversion < $newversion) {
        $table = new xmldb_table(constants::M_TABLE);
        // Add finish screen options.
        $fields = [];
        $fields[] = new xmldb_field('finishscreen', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0);
        $fields[] = new xmldb_field('finishscreencustom', XMLDB_TYPE_TEXT, null, null, null, null);

        // Alter fields.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, $newversion, 'minilesson');
    }

    // Add csskey to minilesson table
    if ($oldversion < 2024120700) {
        $table = new xmldb_table(constants::M_TABLE);

        // Define fields , csskey,to be added to minilesson.
        $fields = [];
        $fields[] = new xmldb_field('csskey', XMLDB_TYPE_CHAR, '255', null);

        // Add fields
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, 2024120700, 'minilesson');
    }

     // Add showitemreview field to minilesson table.
    if ($oldversion < 2025010702) {
        $activitytable = new xmldb_table(constants::M_TABLE);
        // Define field showitemreview to be added to minilesson.
        $showitemreview = new xmldb_field('showitemreview', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1');

        // add showitemreview field to minilesson table
        if (!$dbman->field_exists($activitytable, $showitemreview)) {
            $dbman->add_field($activitytable, $showitemreview);
        }
        upgrade_mod_savepoint(true, 2025010702, 'minilesson');
    }

    // We do not want to change behaviour for existing users, so we set the default to 1.
    if ($oldversion < 2025011701) {
        $DB->set_field(constants::M_TABLE, 'showitemreview', 1, ['showitemreview' => 0]);
        upgrade_mod_savepoint(true, 2025011701, 'minilesson');
    }

    if($oldversion < 2025020700){
        // Add itemttsvoice to minilesson question table
        $table = new xmldb_table(constants::M_QTABLE);

        // Define new custom fields
        $fields = [];
        $fields[] = new xmldb_field('customtext6', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('customtext6format', XMLDB_TYPE_INTEGER, '2', null, false, null);
        $fields[] = new xmldb_field('customtext7', XMLDB_TYPE_TEXT, null, null, null, null);
        $fields[] = new xmldb_field('customtext7format', XMLDB_TYPE_INTEGER, '2', null, false, null);

        // Add fields.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Update AI instructions field.
        if ($DB->record_exists(constants::M_QTABLE, ['type' => 'freespeaking']) ||
         $DB->record_exists(constants::M_QTABLE, ['type' => 'freewriting'])) {
            $sql = "UPDATE {". constants::M_QTABLE . "} SET customtext6 = customtext1, customtext1 = ''";
            $sql .= " WHERE type = 'freewriting' OR type = 'freespeaking'";
            $DB->execute($sql);
        }



        upgrade_mod_savepoint(true, 2025020700, 'minilesson');
    }

    if ($oldversion < 2025062902) {

        // Define table minilesson_templates to be created.
        $table = new xmldb_table('minilesson_templates');

        // Adding fields to table minilesson_templates.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('minilessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table minilesson_templates.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fkminilessonid', XMLDB_KEY_FOREIGN, ['minilessonid'], 'minilesson', ['id']);

        // Conditionally launch create table for minilesson_templates.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025062902, 'minilesson');
    }

    if ($oldversion < 2025062903) {

        // Define field description to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'name');

        // Conditionally launch add field description.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025062903, 'minilesson');
    }

    if ($oldversion < 2025062904) {

        // Define field config to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('config', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'description');

        // Conditionally launch add field config.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field template to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('template', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'config');

        // Conditionally launch add field template.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025062904, 'minilesson');
    }

    if ($oldversion < 2025062905) {

        // Define field timemodified to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'timecreated');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025062905, 'minilesson');
    }

    if ($oldversion < 2025062907) {

        // Define key fkminilessonid (foreign) to be dropped form minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $key = new xmldb_key('fkminilessonid', XMLDB_KEY_FOREIGN, ['minilessonid'], 'minilesson', ['id']);

        // Launch drop key fkminilessonid.
        $dbman->drop_key($table, $key);

        // Define field minilessonid to be dropped from minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('minilessonid');

        // Conditionally launch drop field minilessonid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025062907, 'minilesson');
    }

    if ($oldversion < 2025071300) {

        // Create default templates if they do not exist.
        $templates = \mod_minilesson\aigen::fetch_lesson_templates();
        if (!$templates || empty($templates)) {
            \mod_minilesson\aigen::create_default_templates();
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071300, 'minilesson');
    }

    if ($oldversion < 2025071301) {

        // Delete existing templates because we are going to change the structure.
        $DB->delete_records('minilesson_templates');

        // Define field uniqueid to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('uniqueid', XMLDB_TYPE_CHAR, '250', null, null, null, null, 'template');

        // Conditionally launch add field uniqueid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field version to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'uniqueid');

        // Conditionally launch add field version.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing nullability of field uniqueid on table minilesson_templates to not null.
        $table = new xmldb_table('minilesson_templates');
        $field = new xmldb_field('uniqueid', XMLDB_TYPE_CHAR, '250', null, XMLDB_NOTNULL, null, null, 'template');

        // Launch change of nullability for field uniqueid.
        $dbman->change_field_notnull($table, $field);

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071301, 'minilesson');
    }

    if ($oldversion < 2025071302) {

        // Define key uniquniqueid (unique) to be added to minilesson_templates.
        $table = new xmldb_table('minilesson_templates');
        $key = new xmldb_key('uniquniqueid', XMLDB_KEY_UNIQUE, ['uniqueid']);

        // Launch add key uniquniqueid.
        $dbman->add_key($table, $key);

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071302, 'minilesson');
    }

    if ($oldversion < 2025071303) {

        \mod_minilesson\aigen::create_default_templates();

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071303, 'minilesson');
    }

    if ($oldversion < 2025071303.01) {

        // Define table minilesson_template_usages to be created.
        $table = new xmldb_table('minilesson_template_usages');

        // Adding fields to table minilesson_template_usages.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('minilessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('templateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('contextdata', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('progress', XMLDB_TYPE_NUMBER, '3, 2', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table minilesson_template_usages.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fkminilessonid', XMLDB_KEY_FOREIGN, ['minilessonid'], 'minilesson', ['id']);
        $table->add_key('fktemplateid', XMLDB_KEY_FOREIGN, ['templateid'], 'minilesson_templates', ['id']);

        // Conditionally launch create table for minilesson_template_usages.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071303.01, 'minilesson');
    }

    if ($oldversion < 2025071305) {
        global $DB;

        //Fetch unique minilesson ids for all minilesson items that are of type multichoice or multiaudio
        $sql = "SELECT DISTINCT minilesson FROM {". constants::M_QTABLE ."} WHERE type IN (:type1, :type2)";
        $params = [
            'type1' => constants::TYPE_MULTICHOICE,
            'type2' => constants::TYPE_MULTIAUDIO
        ];
        $minilessonids = $DB->get_fieldset_sql($sql, $params);

        // Fetch all minilesson instances that we are interested in
        $minilessoninstances = $DB->get_records_list(constants::M_TABLE, 'id', $minilessonids);
        //$minilessoninstances = $DB->get_records(constants::M_TABLE);

        if ($minilessoninstances) {
            foreach ($minilessoninstances as $moduleinstance) {
                $upgradetypes = [ constants::TYPE_MULTICHOICE, constants::TYPE_MULTIAUDIO];
                foreach ($upgradetypes as $upgradetype) {

                    // Fetch all item records for the current minilesson instance.
                    $itemrecords = $DB->get_records(constants::M_QTABLE,
                    ['minilesson' => $moduleinstance->id, 'type' => $upgradetype]);
                    if (!$itemrecords) {
                        continue; // No items to upgrade for this minilesson instance, skip to the next one.
                    }
                    foreach ($itemrecords as $itemdata) {
                        $theitem = utils::fetch_item_from_itemrecord($itemdata, $moduleinstance);
                        if ($theitem) {
                            $theitem->upgrade_item($oldversion);
                        }
                    }
                }
            }
        }

        // Update default templates to the new format.
        \mod_minilesson\aigen::create_default_templates();

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071305, 'minilesson');
    }

    if ($oldversion < 2025071305.01) {

        // Define field error to be added to minilesson_template_usages.
        $table = new xmldb_table('minilesson_template_usages');
        $field = new xmldb_field('error', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timemodified');

        // Conditionally launch add field error.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025071305.01, 'minilesson');
    }

    if ($oldversion < 2025073000) {

        // Update default templates
        \mod_minilesson\aigen::create_default_templates();

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025073000, 'minilesson');
    }

    if ($oldversion < 2025080102) {

        // Add more customint fields to minilesson question table
        $table = new xmldb_table(constants::M_QTABLE);

        // Define new custom fields
        $fields = [];

        $fields[] = new xmldb_field('customint6', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $fields[] = new xmldb_field('customint7', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $fields[] = new xmldb_field('customint8', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $fields[] = new xmldb_field('customint9', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $fields[] = new xmldb_field('customint10', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $fields[] = new xmldb_field('itemaudiostoryzoom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');

        // Add fields.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025080102, 'minilesson');
    }

    if ($oldversion < 2025080103) {

        // Update default templates
        \mod_minilesson\aigen::create_default_templates();

        // Minilesson savepoint reached.
        upgrade_mod_savepoint(true, 2025080103, 'minilesson');
    }

    // Final return of upgrade result (true, all went good) to Moodle.
    return true;
}
