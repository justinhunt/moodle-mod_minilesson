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

use context_system;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_minilesson\aigen;
use mod_minilesson\constants;
use mod_minilesson\utils;

/**
 * Class aigen_list_templates
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_list_templates extends external_api {
    /**
     * parameters for list templates
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * list all templates.
     * @return array
     */
    public static function execute() {

        $context = context_system::instance();
        self::validate_context($context);

        $templates = aigen::fetch_lesson_templates();
        $responsetemplates = [];

        foreach ($templates as $thetemplate) {
            $mappings = $thetemplate['config']->fieldmappings;
            $items = $thetemplate['config']->items;
            $templatedata = $thetemplate['template'];
            $templateitems = $thetemplate['template']->items;
            $inputs = [];
            $outputs = [];
            $requiredinputs = aigen::template_required_inputs($thetemplate['config']);
            foreach ($mappings as $fieldname => $fieldmapping) {
                if (!empty($fieldmapping->enabled)) {
                    $requirefields = [
                        'fieldname' => $fieldname,
                        'title' => $fieldmapping->title,
                        'type' => $fieldmapping->type,
                        'description' => $fieldmapping->description,
                        'required' => in_array($fieldname, $requiredinputs),
                    ];
                    $optionalfields = ($fieldmapping->type == 'dropdown') ? ['options' => $fieldmapping->options] : [];
                    $inputs[] = array_merge($requirefields, $optionalfields);
                }
            }
            $outputitems = [];
            $imagecount = 0;
            // The template's skills are auto derived from the skills of the item types it contains.
            $skills = [];
            foreach ($items as $item) {
                $totalfiles = 0;
                if (!empty($templateitems)) {
                    $type = $templateitems[$item->itemnumber]->type;
                    // Not every item type stores its descriptive text as "instructions" -
                    // some (page, multichoice, speechcards, dictation, ...) use "text" instead.
                    $description = $templateitems[$item->itemnumber]->instructions
                        ?? $templateitems[$item->itemnumber]->text
                        ?? '';
                    // Files are keyed by item number directly on $templatedata->files, not
                    // by a per-item "filesid" property (template items never had one).
                    $filesid = $item->itemnumber;
                    if (!empty($templatedata->files->$filesid)) {
                        foreach ($templatedata->files->$filesid as $fileobject) {
                            $totalfiles += count((array) $fileobject);
                        }
                    }
                    $imagecount += $totalfiles;
                    $outputitems[] = [
                        'type' => $type,
                        'description' => $description,
                        'control' => aigen::item_control_level($item),
                    ];

                    $itemtypeclass = utils::fetch_itemtype_classname($type);
                    if ($itemtypeclass && isset($itemtypeclass::$skills)) {
                        $skills = array_merge($skills, $itemtypeclass::$skills);
                    }
                }
            }
            $skills = array_values(array_unique($skills));
            // "content" is not a real skill, so only keep it when it is the template's sole skill
            // (a pure content template). Otherwise it just adds noise to multi-item templates.
            if (count($skills) > 1) {
                $skills = array_values(array_filter($skills, function($skill) {
                    return $skill !== constants::SKILL_CONTENT;
                }));
            }

            $outputs[] = [
                'itemcount' => count($items),
                'items' => $outputitems,
                'imagecount' => $imagecount,
            ];

            $responsetemplates[] = [
                'id' => $thetemplate['id'],
                'name' => $thetemplate['name'],
                'description' => $thetemplate['description'],
                'skills' => $skills,
                'agentonly' => !empty($thetemplate['agentonly']),
                'control' => aigen::template_control_level($thetemplate['config']),
                'inputs' => $inputs,
                'outputs' => $outputs,
                'variants' => [],
            ];
        }
        return self::add_variants($responsetemplates);
    }

    /**
     * Fill in each template's "variants": the other templates that produce the same item types,
     * strongest control first. Templates come in families (generate / upload / upload with markup)
     * and the family members are only discoverable by comparing every template's outputs, which is
     * work the caller should not have to redo to answer "is there a more specific template?".
     *
     * @param array $responsetemplates The templates built by execute().
     * @return array The same templates, with "variants" populated.
     */
    protected static function add_variants(array $responsetemplates) {
        // Group by the multiset of item types the template produces.
        $families = [];
        foreach ($responsetemplates as $index => $responsetemplate) {
            $types = [];
            foreach ($responsetemplate['outputs'] as $output) {
                foreach ($output['items'] as $outputitem) {
                    $types[] = $outputitem['type'];
                }
            }
            sort($types);
            $families[implode(',', $types)][] = $index;
        }

        foreach ($families as $memberindexes) {
            if (count($memberindexes) < 2) {
                continue;
            }
            foreach ($memberindexes as $index) {
                $variants = [];
                foreach ($memberindexes as $otherindex) {
                    if ($otherindex === $index) {
                        continue;
                    }
                    $variants[] = [
                        'id' => $responsetemplates[$otherindex]['id'],
                        'name' => $responsetemplates[$otherindex]['name'],
                        'control' => $responsetemplates[$otherindex]['control'],
                    ];
                }
                // Strongest control first, so the most specific alternative is the one read first.
                usort($variants, function ($a, $b) {
                    return aigen::control_rank($b['control']) <=> aigen::control_rank($a['control']);
                });
                $responsetemplates[$index]['variants'] = $variants;
            }
        }
        return $responsetemplates;
    }

    /**
     * return list of templates
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_RAW, 'Description'),
                'skills' => new external_multiple_structure(
                    new external_value(PARAM_ALPHA, 'A language skill (listening, speaking, reading, writing, '
                        . 'pronunciation, vocabulary, grammar) or "content" for display-only item types'),
                    'The language skills this template focuses on, auto derived from the item types it contains'
                ),
                'agentonly' => new external_value(PARAM_BOOL, 'Whether this template is hidden from the human '
                    . 'template picker. Agent-only templates are usually the high control member of their family: '
                    . 'they ask for content that is tedious to type by hand but easy for an agent to compose'),
                'control' => new external_value(PARAM_ALPHA, 'How much of the content you decide, rather than the AI '
                    . 'inventing it: "supplied" (your text is used verbatim), "derived" (the AI rewrites or marks up '
                    . 'text you supply) or "generated" (the AI invents the content from a topic, keywords or level). '
                    . 'This is the weakest of the template\'s items. If you have already decided something the '
                    . 'lesson must teach, check an input carries it, and otherwise use a stronger variant'),
                'inputs' => new external_multiple_structure(
                    new external_single_structure([
                        'fieldname' => new external_value(PARAM_TEXT, 'Field Name'),
                        'title' => new external_value(PARAM_TEXT, 'Field Title'),
                        'type' => new external_value(PARAM_TEXT, 'Field Type'),
                        'description' => new external_value(PARAM_RAW, 'Field Description', VALUE_DEFAULT, ''),
                        'required' => new external_value(PARAM_BOOL, 'Whether this input must be sent with a '
                            . 'non-empty value. A required input is consumed by a prompt, a reused field or an '
                            . 'image prompt, so sending it empty does not fall back to a default: it generates '
                            . 'content with a hole in it. Ask the user for the value rather than guessing or '
                            . 'omitting it. Creation is rejected if a required input is empty'),
                        'options' => new external_multiple_structure(
                            new external_value(PARAM_TEXT, 'Option value'),
                            'List of options',
                            VALUE_OPTIONAL
                        ),
                    ]),
                ),
                'outputs' => new external_multiple_structure(
                    new external_single_structure([
                        'itemcount' => new external_value(PARAM_INT, 'Count of items'),
                        'items' => new external_multiple_structure(
                            new external_single_structure([
                                'type' => new external_value(PARAM_TEXT, 'Item type'),
                                'description' => new external_value(PARAM_RAW, 'Item Description'),
                                'control' => new external_value(PARAM_ALPHA, 'This item\'s control level '
                                    . '("supplied", "derived" or "generated"), as described on the template'),
                            ])
                        ),
                        'imagecount' => new external_value(PARAM_INT, 'Count of image')
                    ]),
                ),
                'variants' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Template id of the variant'),
                        'name' => new external_value(PARAM_TEXT, 'Name of the variant'),
                        'control' => new external_value(PARAM_ALPHA, 'The variant\'s control level'),
                    ]),
                    'Other templates that produce the same item types, strongest control first. Read these before '
                    . 'settling on a template: if you already know what the lesson must teach (which words are '
                    . 'gapped or shuffled, which answer is correct, the translation language), prefer the variant '
                    . 'whose inputs carry that decision over one that leaves it to the AI'
                ),
            ])
        );
    }
}

