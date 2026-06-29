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

use core_customfield\{data_controller, field_controller};
use local_lessonbank\external\list_minilessons;
use local_modcustomfields\customfield\mod_handler;
use mod_minilesson\local\exception\textgenerationfailed;

/**
 * Class aigen
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen {

    /** @var array */
    public const DEFAULTTEMPLATES = [
        '6880824450555' => 'audiostory',
        '6874e6af39202' => 'passagereading',
        '6874e6ca1c71a' => 'wordpractice',
        '6874e6a65a0ff' => 'ayoutubelesson',
        '6874e6axxx999' => 'youtubefinalelesson',
        '688244029b789' => 'keywordstogapfillsfluency',
        '6899607f96fb7' => 'reading_aic_passagegen',
        '68996c17d07f5' => 'reading_aic_passageupload',
        '69076f35b4c3b' => 'set_of_slides',
        '69095a6426664' => 'set_of_slides_nopics',
        '68a036971fe4b' => 'keywords_to_ws_sc',
        '68a0422070f05' => 'keywords_to_ws_sc_sg',
        '690b33f83d02f' => 'dialog_multichoice',
        '690b5e695b235' => 'image_slides',
        '690eb6a91c972' => 'choose_best_reply',
        '691ad9a15f203' => 'wordpractice2',
        '6960baa71fa35' => 'fiction_withpics',
        '696105abf2b2a' => 'fiction_nopics',
        '6979e4ab51d53' => 'narrativefiction_withpics',
        '6a30d8d44b6fa' => 'vocabcards',
        '6a2504c6d2e93' => 'youtubefinale_freewrite',
        '6a2518a50b676' => 'youtubefinale_freespeak',
        '6a30f621d30b1' => 'passagegapfill_generate',
        '6a3545b0af700' => 'fluency_upload',
        '6a30fdf7bb4bb' => 'passagereading_generate',
        '6a30fef2641af' => 'freespeaking',
        '6a31023c2bd88' => 'wordshuffle_generate',
        '6a31062973acc' => 'scatter',
        '6a31071c21ad1' => 'spacegame',
        '6a313e18a1fc5' => 'multichoice_image',
        '6a3141279b845' => 'freewriting',
        '6a3142579495d' => 'listeninggapfill_generate',
        '6a315158ee84e' => 'speakinggapfill_generate',
        '6a3151f3681c2' => 'typinggapfill_generate',
        '6a31532a837be' => 'gapfill_set',
        '6a329e299a1f8' => 'audiostory_generate',
        '6a334d1c7f9c8' => 'shadow',
        '6a33790712563' => 'listenandspeak_generate',
        '6a337c067d7fd' => 'dictation_generate',
        '6a337d8515373' => 'dictationchat_generate',
        '6a337f919d299' => 'shortanswer',
        '6a3385bb328ce' => 'passagegapfill_upload',
        '6a338aa0467b5' => 'passagereading_upload',
        '6a338cfe5bca3' => 'wordshuffle_upload',
        '6a338f1f97bd4' => 'listenandspeak_upload',
        '6a3390f8c7e42' => 'dictation_upload',
        '6a33925f3c59f' => 'dictationchat_upload',
        '6a339418c0f3b' => 'audiostory_upload',
        '6a33957b46df0' => 'fluency_generate',
        '6a33984a3d23b' => 'typinggapfill_upload',
        '6a3398538779e' => 'speakinggapfill_upload',
        '6a3398588ba75' => 'listeninggapfill_upload',
        '6a339bc64597f' => 'reading_speaking_upload',
        '6a339bf11eff6' => 'listentothestory_upload',
        '6a33dfac8eb34' => 'passagegapfill_upload_keywords',
        '6a349d7edd597' => 'wordshuffle_keywords_generate',
        '6a34d7bfb6e6c' => 'wordshuffle_upload_markup',
        '6a354513e1b8e' => 'listeninggapfill_upload_markup',
        '6a35453bb06b9' => 'speakinggapfill_upload_markup',
        '6a35457039d98' => 'typinggapfill_upload_markup',
        '6a3545ea4c8c0' => 'listenandspeak_upload_markup',
        '6a35465198ab9' => 'spacegame_upload',
        '6a35469964a00' => 'scatter_upload',
        '6a3546ed4f010' => 'vocabcards_upload_markup',
        '6a35dd77d6c9b' => 'fluency_upload_markup',
        '6a35e28b48614' => 'freewriting_upload',
        '6a35e2a3a4cb6' => 'freespeaking_upload',
        '6a35e6ce88a31' => 'audiochat_generate',
        '6a35e70e9773b' => 'audiochat_upload',
        '6a362aeb558cb' => 'grammar_slides',
        '6a36351da64e3' => 'grammar_choosewords_v1',
        '6a363a03964a0' => 'grammar_shufflewords',
        '6a38ad44e9b60' => 'grammar_choosewords_v2',
    ];
    /** @var \stdClass|null */
    private $moduleinstance = null;
    /** @var \stdClass|null */
    private $course = null;
    /** @var \stdClass|null */
    private $cm = null;
    /** @var \stdClass|null */
    private $context = null;
    /** @var \stdClass|null */
    private $conf = null;

    /** @var \core\progress\db_updater */
    private $progressbar = null;

    /** @var aimanager */
    private aimanager $aimanager;

    /**
     * aigen constructor.
     *
     * @param \stdClass|null $moduleinstance The module instance object, if available.
     * @param \stdClass|null $course The course object, if available.
     * @param \stdClass|null $cm The course module object, if available.
     */
    public function __construct($cm, $progressbar = null) {
        global $PAGE, $OUTPUT;

        global $DB;
        $this->cm = $cm;
        $this->moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $this->conf = get_config(constants::M_COMPONENT);
        $this->progressbar = $progressbar;
        $this->aimanager = new aimanager(
            $this->context->id,
            $this->moduleinstance->region,
            $this->moduleinstance->ttslanguage
        );
    }

    /**
     * Makes import data for the minilesson based on the AI generation configuration and template.
     *
     * @param \stdClass $aigenconfig The AI generation configuration object.
     * @param \stdClass $aigentemplate The AI generation template object.
     * @param array $contextdata The context data for prompt generation.
     * @return \stdClass An object containing the generated import items and lesson files.
     * @throws textgenerationfailed If text generation fails for any item.
     */
    public function make_import_data($aigenconfig, $aigentemplate, $contextdata) {
        $contextfileareas = [];
        $importitems = [];
        $importlessonfiles = new \stdClass();
        $currentitemcount = 0;

        // Get all the voices and get just the nice ones (neural/whisper/azure).
        $langvoices = utils::get_tts_voices($this->moduleinstance->ttslanguage, false, $this->moduleinstance->region);
        $nicevoices = utils::get_nice_voices($this->moduleinstance->ttslanguage, $this->moduleinstance->region);

        foreach ($aigenconfig->items as $configitem) {
            $currentitemcount++;
            $importitem = $aigentemplate->items[$configitem->itemnumber];
            $importitemfileareas = (isset($importitem->filesid) && isset($aigentemplate->files->{$importitem->filesid})) ?
                $aigentemplate->files->{$importitem->filesid} :
                false;
            // This holds data not in the import item that we generate or use for generation.
            $dataitem = new \stdClass();

            // Wrap each item's work in its own indeterminate progress section.
            // An indeterminate parent is not auto-incremented by its children's end_progress() calls
            // (see core\progress\base) and enforces no max, so the variable number of nested text/image
            // sub-steps below are absorbed cleanly. Closing this wrapper advances the outer determinate
            // progress bar by exactly one per item, which avoids the "progress() value may not go backwards"
            // (and "parent progress would exceed max") coding exceptions.
            if ($this->progressbar) {
                $this->progressbar->start_progress(
                    get_string('generatingitemdata', constants::M_COMPONENT, $importitem->name)
                );
            }

            switch ($configitem->generatemethod) {
                case 'generate':
                case 'extract':
                    // Prepare the prompt with context data.
                    $useprompt = $configitem->prompt;
                    foreach ($configitem->promptfields as $promptfield) {
                        if (isset($contextdata[$promptfield->mapping])) {
                            $useprompt = str_replace(
                                '{' . $promptfield->name . '}',
                                $contextdata[$promptfield->mapping],
                                $useprompt
                            );
                        }
                    }

                    // Prepare the response format (JSON).
                    $generateformat = new \stdClass();
                    foreach ($configitem->generatefields as $generatefield) {
                        if (isset($generatefield->generate) && $generatefield->generate == 1) {
                            $generateformat->{$generatefield->name} = $generatefield->name . '_data';
                        }
                    }
                    $generateformatjson = json_encode($generateformat);

                    // Complete the prompt.
                    $useprompt = $useprompt . PHP_EOL . 'Generate the data in this JSON format: ' . $generateformatjson;

                    if ($this->progressbar) {
                        $this->progressbar->start_progress(
                            get_string('generatingtextdata', constants::M_COMPONENT, $importitem->name)
                        );
                    }

                    $genresult = $this->aimanager->generate_structured_content(
                        $useprompt,
                        true, // Enable caching.
                    );
                    // Add a breakpoint here to inspect the result of AI generation (text)
                    if ($genresult && $genresult->success) {
                        $genpayload = $genresult->payload;
                        // Now map the generated data to the importitem.
                        foreach ($configitem->generatefields as $generatefield) {
                            if (isset($genpayload->{$generatefield->name})) {
                                // Overwrite the field in the import template with the generated data (if it exists).
                                // It might not exist if its a data field we generated for use elsewhere in the process.
                                if (isset($importitem->{$generatefield->name})) {
                                    $importitem->{$generatefield->name} = $genpayload->{$generatefield->name};
                                } else {
                                    // If the field does not exist in the import item, we can add it to the dataitem.
                                    $dataitem->{$generatefield->name} = $genpayload->{$generatefield->name};
                                }
                            }
                        }
                    } else {
                        throw new textgenerationfailed(
                            $currentitemcount,
                            $importitem->type,
                            $useprompt . ' | Error: ' . $genresult->payload
                        );
                    }

                    if ($this->progressbar) {
                        $this->progressbar->end_progress();
                    }

                    break;

                case 'reuse':
                    foreach ($configitem->generatefields as $generatefield) {
                        if (
                            isset($importitem->{$generatefield->name}) &&
                            !empty($generatefield->mapping && isset($contextdata[$generatefield->mapping]))
                        ) {
                            $importitem->{$generatefield->name} = $contextdata[$generatefield->mapping];
                        }
                    }
                    break;
            }

            // Handle the image file mapping fieldset. It has its own generate method which may differ
            // from the item-level method. Default to the item-level method for back-compat with configs
            // saved before the per-fieldset method existed.
            $fileareasmethod = '';
            // DEVELOPER: Comment this line if testing and don't want to needlessly create images.
            $fileareasmethod = $configitem->generatefileareasmethod ?? $configitem->generatemethod;
            switch ($fileareasmethod) {
                case 'generate':
                case 'extract':
                    // First collect overall image context which is just used to encourage AI to make consistent images.
                    $overallimagecontext = false;
                    if (
                        isset($configitem->overallimagecontext) && $configitem->overallimagecontext !== "--"
                        && isset($contextdata[$configitem->overallimagecontext])
                        && !empty($contextdata[$configitem->overallimagecontext])
                    ) {
                        $overallimagecontext = $contextdata[$configitem->overallimagecontext];
                    }
                    // If the filearea is in the template, and the mapping data (topic/sentences etc) is set, generate images.
                    foreach ($configitem->generatefileareas as $generatefilearea) {
                        if (
                            $importitemfileareas &&
                            isset($importitemfileareas->{$generatefilearea->name}) &&
                            isset($generatefilearea->mapping) &&
                            (
                                isset($importitem->{$generatefilearea->mapping}) ||
                                isset($contextdata[$generatefilearea->mapping]) ||
                                isset($dataitem->{$generatefilearea->mapping})
                            )
                        ) {
                            // Update the user.
                            if ($this->progressbar) {
                                $this->progressbar->start_progress(
                                    get_string('generatingimagedata', constants::M_COMPONENT, $importitem->name)
                                );
                            }
                            // Image prompt data - usually mapped from other items (created)
                            // but possibly also from context data or dataitem.
                            $imagepromptdata = false;
                            if (isset($importitem->{$generatefilearea->mapping})) {
                                $imagepromptdata = $importitem->{$generatefilearea->mapping};
                            } else if (isset($dataitem->{$generatefilearea->mapping})) {
                                $imagepromptdata = $dataitem->{$generatefilearea->mapping};
                            } else if (
                                isset($contextdata[$generatefilearea->mapping]) &&
                                !empty($contextdata[$generatefilearea->mapping])
                            ) {
                                $imagepromptdata = $contextdata[$generatefilearea->mapping];
                            }

                            // If the resolved mapping is a JSON-encoded array (e.g. supplied via a
                            // contextdata field as a string), decode it so generate_images() iterates
                            // per element instead of treating the whole blob as a single prompt.
                            if (is_string($imagepromptdata)) {
                                $trimmed = ltrim($imagepromptdata);
                                if ($trimmed !== '' && $trimmed[0] === '[') {
                                    $decoded = json_decode($imagepromptdata, true);
                                    if (is_array($decoded)) {
                                        $imagepromptdata = $decoded;
                                    }
                                }
                            }

                            $importitemfileareas->{$generatefilearea->name} =
                                $this->aimanager->generate_images(
                                    $importitemfileareas->{$generatefilearea->name},
                                    $imagepromptdata,
                                    $overallimagecontext,
                                    false
                                );
                            if ($this->progressbar) {
                                $this->progressbar->end_progress();
                            }
                        }
                    }
                    break;

                case 'reuse':
                    foreach ($configitem->generatefileareas as $generatefilearea) {
                        if (
                            $importitemfileareas &&
                            isset($importitemfileareas->{$generatefilearea->name}) &&
                            !empty($generatefilearea->mapping && isset($contextfileareas[$generatefilearea->mapping]))
                        ) {
                            $importitemfileareas->{$generatefilearea->name} = $contextfileareas[$generatefilearea->mapping];
                        }
                    }
                    break;
            }

            // Update the context data with import item data or dataitem data.
            foreach ($configitem->generatefields as $generatefield) {
                if ($generatefield->generate == 1) {
                    if (isset($importitem->{$generatefield->name})) {
                        $contextdata["item" . $configitem->itemnumber . "_" . $generatefield->name]
                            = $importitem->{$generatefield->name};
                    } else if (isset($dataitem->{$generatefield->name})) {
                        // If the field does not exist in the import item, we can add it to the dataitem.
                        $contextdata["item" . $configitem->itemnumber . "_" . $generatefield->name]
                            = $dataitem->{$generatefield->name};
                    }
                }
            }

            // Update the filearea data.
            foreach ($configitem->generatefileareas as $generatefilearea) {
                if ($importitemfileareas && $generatefilearea->generate == 1) {
                    $contextfileareas["item" . $configitem->itemnumber . "_" . $generatefilearea->name]
                        = $importitemfileareas->{$generatefilearea->name};
                }
            }

            // Voices - these are a special case and we may ultimately do this in a different way.
            // If the itemtemplate has set a voice field, and the template language is different from the module language
            // We need a voice in the module language.
            $itemtype = $importitem->type;
            $itemclass = utils::fetch_itemtype_classname($itemtype);

            // Now check the item for voice fields and update them if necessary.
            if (class_exists($itemclass)) {
                $allfields = $itemclass::get_keycolumns();
                foreach ($allfields as $key => $field) {
                    if (
                        isset($importitem->{$field['jsonname']}) &&
                        $field['type'] == 'voice' &&
                        !in_array($importitem->{$field['jsonname']}, $langvoices)
                    ) {
                        // If the voice is not in the module language we randomly select a voice in the right language.
                        $importitem->{$field['jsonname']} = array_rand(array_flip($nicevoices));
                    }
                }
            }

            // Update the import items.
            $importitems[] = $importitem;
            if (isset($importitem->filesid)) {
                $importlessonfiles->{$importitem->filesid} = $importitemfileareas;
            }

            // Close the per-item wrapper section. This advances the outer determinate progress bar by one.
            if ($this->progressbar) {
                $this->progressbar->end_progress();
            }
        }

        $thereturn = new \stdClass();
        $thereturn->items = $importitems;
        $thereturn->files = $importlessonfiles;
        return $thereturn;
    }

    /**
     * Generate and store the lessonbank custom field data for the lesson.
     *
     * The values are AI-generated from the lesson content, then post-processed with
     * authoritative sources already known at generation time: the generation context
     * ($contextdata) and the aigen template's predefined tags.
     *
     * @param \stdClass $importdata The import data (items/files) for the generated lesson.
     * @param array $contextdata The lesson generation context data.
     * @param int|null $templateid The aigen template id, used to read its predefined tags.
     * @return \stdClass The import data.
     */
    public function add_custom_field_data(\stdClass $importdata, array $contextdata = [], $templateid = null) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $pluginman = \core_plugin_manager::instance();
        if (
            $pluginman->get_plugin_info('local_lessonbank') &&
            $pluginman->get_plugin_info('local_modcustomfields')
        ) {
            $modcustomfieldhandler = mod_handler::create();
            $modcustomfieldhandler->set_parent_context($this->context->get_course_context());
            $categories = $modcustomfieldhandler->get_categories_with_fields();
            $importdata->customfields = [
                'version' => '1.0.0',
            ];
            $fields = [];

            foreach ($categories as $categorycontroller) {
                if ($categorycontroller->get('name') === get_string('lessonbankcatname', 'local_lessonbank')) {
                    foreach ($categorycontroller->get_fields() as $field) {
                        $fieldshortname = $field->get('shortname');
                        if (in_array($fieldshortname, list_minilessons::CUSTOMFIELDS)) {
                            if (in_array($field->get('type'), ['text', 'select', 'multiselect'])) {
                                if ($fieldshortname !== 'version') {
                                    $fields[$fieldshortname] = $field;
                                }
                            } else if ($field->get('type') === 'picture') {
                                if (!empty($importdata->files)) {
                                    $singlestack = [];
                                    $filesarray = json_decode(json_encode($importdata->files), true);
                                    array_walk_recursive(
                                        $filesarray,
                                        function ($value, $key) use (&$singlestack) {
                                            if (pathinfo($key, PATHINFO_EXTENSION) && file_extension_in_typegroup($key, 'image')) {
                                                if (empty($singlestack[$key])) {
                                                    $singlestack[$key] = $value;
                                                }
                                            }
                                        }
                                    );
                                    if (!empty($singlestack)) {
                                        $filename = key($singlestack);
                                        $filecontent = $singlestack[$filename];
                                        $storedfile = self::create_draft_file([
                                            'filename' => $filename,
                                            'content' => base64_decode($filecontent),
                                        ]);
                                        $importdata->customfields[$fieldshortname] = (int) $storedfile->get_itemid();
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $payloadobject = $this->aimanager->generate_customfield(
                $fields,
                $importdata->items,
            );
            if (!is_object($payloadobject)) {
                return $importdata;
            }

            // returnCode > 0  indicates an error
            if (!isset($payloadobject->returnCode) || $payloadobject->returnCode > 0) {
                return $importdata;
                // if all good, then lets do the embed
            } else if ($payloadobject->returnCode === 0) {
                $fielddatas = json_decode($payloadobject->returnMessage, true);

                // Post-process the AI-generated field data with authoritative sources that are
                // already known: the generation context (user_level / user_keywords / user_topic)
                // and the aigen template's predefined tags. Each merge/override is validated
                // against the field's *current* allowed options (the lessonbank allowed lists are
                // configurable and are not guaranteed to match the template tags / context values),
                // so anything that no longer maps to a valid option simply falls back to the AI
                // value rather than being blanked.
                $fielddatas = $this->apply_authoritative_customfields($fielddatas, $fields, $contextdata, $templateid);

                $errors = [];
                foreach ($fielddatas as $fieldshortname => $fielddata) {
                    if (!empty($fields[$fieldshortname])) {
                        $field = $fields[$fieldshortname];
                        $fieldtype = $field->get('type');
                        $fieldoptions = array_flip(self::get_customfield_options($field));
                        if (!empty($fieldoptions)) {
                            $fielddata = array_intersect_key(
                                $fieldoptions,
                                array_fill_keys((array) $fielddata, 1)
                            );
                            if ($fieldtype === 'select') {
                                $fielddata = reset($fielddata);
                            }
                        }
                        if ($fieldtype === 'text') {
                            $fielddata = join(' ', (array) $fielddata);
                        }
                        $datacontroller = data_controller::create(0, null, $field);
                        $importdata->customfields[$fieldshortname] = $fielddata;
                        $errors += $datacontroller->instance_form_validation([
                            $datacontroller->get_form_element_name() => $fielddata,
                        ], []);
                    }
                }
                if (!empty($errors)) {
                    mtrace('Custom field generation errors -> ' . var_export($errors, true));
                } else if (!empty($importdata->customfields)) {
                    // Existing values (keyed by shortname) so we never overwrite data already on the
                    // lesson. export_value() returns null when a field has no stored value.
                    $existingvalues = [];
                    foreach ($modcustomfieldhandler->get_instance_data($this->cm->id, true) as $datacontroller) {
                        $existingvalues[$datacontroller->get_field()->get('shortname')] = $datacontroller->export_value();
                    }
                    foreach ($importdata->customfields as $fieldshortname => $fielddata) {
                        // Skip fields that already have a value. instance_form_save() leaves any
                        // customfield_* property we do not set on the instance untouched.
                        $existing = $existingvalues[$fieldshortname] ?? null;
                        if ($existing !== null && $existing !== '') {
                            continue;
                        }
                        $this->cm->{'customfield_' . $fieldshortname} = $fielddata;
                    }
                    $modcustomfieldhandler->instance_form_save($this->cm, true);
                }
            } else {
                return $importdata;
            }
        }
        return $importdata;
    }

    /**
     * Post-process AI-generated custom field data with authoritative sources (generation
     * context + aigen template tags). Values are injected into $fielddatas in the same shape
     * the AI returns them (option display text for option fields), so the existing
     * option-intersection and validation in add_custom_field_data() applies uniformly and any
     * value that does not map to a current option falls back to the AI value.
     *
     * @param array|null $fielddatas The AI-decoded field data (fieldshortname => value).
     * @param field_controller[] $fields The custom fields keyed by shortname.
     * @param array $contextdata The lesson generation context data.
     * @param int|null $templateid The aigen template id (for predefined tags).
     * @return array The adjusted field data.
     */
    protected function apply_authoritative_customfields($fielddatas, array $fields, array $contextdata, $templateid) {
        $fielddatas = (array) $fielddatas;

        // Override languagelevel from context user_level (only when it maps to a current option).
        $this->set_customfield_source($fielddatas, $fields, 'languagelevel', $contextdata['user_level'] ?? null, false);

        // Override topic from context user_topic (when present).
        $this->set_customfield_source($fielddatas, $fields, 'topic', $contextdata['user_topic'] ?? null, false);

        // Merge keyvocabulary from context user_keywords with AI (key vocabulary, NOT search keywords).
        $this->set_customfield_source($fielddatas, $fields, 'keyvocabulary', $contextdata['user_keywords'] ?? null, true);

        // Merge skills from the aigen template predefined tags with AI.
        if (!empty($templateid)) {
            $tags = array_column(template_tag_manager::get_current_tags($templateid), 'tagname');
            $this->set_customfield_source($fielddatas, $fields, 'skills', $tags, true);
        }

        return $fielddatas;
    }

    /**
     * Inject an authoritative value into the AI field data for a single custom field.
     *
     * For option fields (select/multiselect) the candidates and AI values are option display
     * text, matched case-insensitively against the current options and stored as the option's
     * canonical text; candidates that do not match a current option are discarded, and if none
     * match the AI value is left untouched (fallback). For free-text fields the value is overridden or
     * (when merging) term-merged and de-duplicated with the AI value.
     *
     * @param array $fielddatas The AI field data, modified in place.
     * @param field_controller[] $fields The custom fields keyed by shortname.
     * @param string $shortname The target field shortname.
     * @param mixed $candidates The authoritative value(s) from context/tags.
     * @param bool $merge True to merge with the AI value, false to override it.
     */
    protected function set_customfield_source(array &$fielddatas, array $fields, $shortname, $candidates, $merge) {
        if (empty($fields[$shortname]) || $candidates === null || $candidates === '' || $candidates === []) {
            return;
        }
        $field = $fields[$shortname];
        $fieldtype = $field->get('type');

        if ($fieldtype === 'select' || $fieldtype === 'multiselect') {
            // Build a lowercased-text -> canonical-text map of the field's *current* allowed
            // options, so matching is case-insensitive but we always store the option's exact text.
            $optionmap = [];
            foreach (self::get_customfield_options($field) as $optiontext) {
                $optionmap[\core_text::strtolower($optiontext)] = $optiontext;
            }
            $resolve = function ($values) use ($optionmap) {
                $resolved = [];
                foreach ((array) $values as $value) {
                    $key = \core_text::strtolower(trim((string) $value));
                    if ($key !== '' && isset($optionmap[$key])) {
                        $resolved[] = $optionmap[$key];
                    }
                }
                return $resolved;
            };

            $valid = $resolve($candidates);
            if (empty($valid)) {
                // Nothing maps to a current option -> keep the AI value.
                return;
            }
            if ($merge) {
                // Canonicalise the AI values too so casing drift does not drop them downstream.
                $valid = array_merge($resolve($fielddatas[$shortname] ?? []), $valid);
            }
            $valid = array_values(array_unique($valid));
            $fielddatas[$shortname] = $fieldtype === 'select' ? reset($valid) : $valid;
        } else {
            // Free-text field.
            if ($merge) {
                $fielddatas[$shortname] = self::merge_terms($fielddatas[$shortname] ?? '', $candidates);
            } else {
                $fielddatas[$shortname] = is_array($candidates) ? implode(', ', $candidates) : (string) $candidates;
            }
        }
    }

    /**
     * Merge two comma/newline-separated term lists, trimming and de-duplicating case-insensitively.
     *
     * @param mixed $aivalue The AI-generated value (string or array).
     * @param mixed $uservalue The user/context value (string or array).
     * @return string The merged, comma-separated term list.
     */
    protected static function merge_terms($aivalue, $uservalue): string {
        $split = function ($value) {
            $parts = [];
            foreach ((array) $value as $item) {
                $parts = array_merge($parts, preg_split('/[,;\n]+/', (string) $item));
            }
            return $parts;
        };
        $result = [];
        $seen = [];
        foreach (array_merge($split($aivalue), $split($uservalue)) as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }
            $key = \core_text::strtolower($term);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $term;
        }
        return implode(', ', $result);
    }

    public static function create_draft_file($filedata = []): \stored_file {
        global $USER;

        $fs = get_file_storage();

        $filerecord = [
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => isset($filedata['itemid']) ? $filedata['itemid'] : file_get_unused_draft_itemid(),
            'author'    => isset($filedata['author']) ? $filedata['author'] : fullname($USER),
            'filepath'  => isset($filedata['filepath']) ? $filedata['filepath'] : '/',
            'filename'  => isset($filedata['filename']) ? $filedata['filename'] : 'file.txt',
        ];

        if (isset($filedata['contextid'])) {
            $filerecord['contextid'] = $filedata['contextid'];
        } else {
            $usercontext = \context_user::instance($USER->id);
            $filerecord['contextid'] = $usercontext->id;
        }
        $source = isset($filedata['source']) ? $filedata['source'] : serialize((object)['source' => 'From string']);
        $content = isset($filedata['content']) ? $filedata['content'] : 'some content here';

        $file = $fs->create_file_from_string($filerecord, $content);
        $file->set_source($source);

        return $file;
    }

    public static function get_customfield_options(field_controller $field): array {
        $fieldtype = $field->get('type');
        if ($fieldtype === 'select') {
            return array_filter($field->get_options());
        } else if ($fieldtype === 'multiselect') {
            return $field::get_options_array($field);
        }
        return [];
    }

    /**
     * Resizes image data to smaller dimensions.
     *
     * @param string $imagedata The raw image data.
     * @return string The resized image data.
     */
    public function make_image_smaller($imagedata) {
        return aimanager::make_image_smaller($imagedata);
    }

    public function generate_image($prompt) {
        return $this->aimanager->generate_image(
            $prompt,
            false
        );
    }

    public function generate_data($prompt) {
        return $this->aimanager->generate_structured_content(
            $prompt,
            true, // Enable cache for structured content.
        );
    }

    /**
     * Fetches the lesson templates from the lesson templates directory.
     *
     * @param array $filtertags Optional list of tags to filter the templates by.
     * @param bool $includeagentonly Whether to include agent-only templates (hidden from the human picker). Default true.
     * @return array An associative array of lesson templates,
     *  where the key is the template name and the value is an array containing 'config' and 'template' objects.
     */
    public static function fetch_lesson_templates($filtertags = [], $includeagentonly = true) {
        global $DB;

        $fields = 't.*';
        $from = '{minilesson_templates} t';
        $where = '1 = 1';
        $groupby = 't.id';
        $orderby = 't.id';
        $params = [];

        // Agent-only templates are hidden from the human AI generation picker.
        if (!$includeagentonly) {
            $where .= ' AND t.agentonly = 0';
        }

        $predefinedtags = template_tag_manager::get_predefined_tags();
        $singleormultitags = template_tag_manager::get_singleormulti_tags();
        $itemtypetags = template_tag_manager::get_itemtype_tags();
        $tags = array_merge($predefinedtags, $singleormultitags, $itemtypetags);
        $filtertags = array_intersect($filtertags, $tags);
        if ($filtertags) {
            [$in, $inparams] = $DB->get_in_or_equal($filtertags, SQL_PARAMS_NAMED);
            $from .= ' JOIN {' . template_tag_manager::DBTABLE . '} tt ON tt.templateid = t.id ';
            $where .= " AND tt.tagname {$in}";
            $params += $inparams;
        }

        $sql = "SELECT {$fields} FROM {$from} WHERE {$where} GROUP BY {$groupby} ORDER BY {$orderby}";
        $templates = $DB->get_records_sql($sql, $params);
        foreach ($templates as $i => $template) {
            $template->config = json_decode($template->config);
            $template->template = json_decode($template->template);
            $templates[$i] = (array) $template;
        }
        return $templates;
    }

    /**
     * Creates default templates for the AI generation.
     *
     * This function reads predefined template files from the specified directory,
     * creates a new template object, and uploads it using the aigen_uploadform class.
     * It handles exceptions if the files cannot be read.
     */
    public static function create_default_templates() {
        global $CFG, $DB;

        foreach (self::DEFAULTTEMPLATES as $uniqueid => $templateshortname) {
            $t = new \stdClass();
            $t->name = get_string("aigentemplatename:" . $templateshortname, constants::M_COMPONENT);
            $t->description = get_string("aigentemplatedescription:" . $templateshortname, constants::M_COMPONENT);
            // Load the configuration and template files for the aigen template.
            // These files should be located in the specified directory.
            // Ensure that the paths are correct and the files exist.
            // The configuration file should contain the lesson configuration in JSON format.
            // The template file should contain the lesson template in MiniLesson export/import JSON format.
            try {
                $t->config = file_get_contents(
                    $CFG->dirroot . "/mod/minilesson/lessontemplates/" . $templateshortname . "_config.json"
                );
                $t->template = file_get_contents(
                    $CFG->dirroot . "/mod/minilesson/lessontemplates/" . $templateshortname . "_template.json"
                );
                aigen_uploadform::upsert_template($t);
            } catch (\Exception $e) {
                // Handle the exception if the file cannot be read.
                debugging('Error reading $template config file: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
