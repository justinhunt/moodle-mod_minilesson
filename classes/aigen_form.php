<?php
/**
 * Helper.
 *
 * @package mod_minilesson
 * @author  Justin Hunt
 */
namespace mod_minilesson;

use context_user;
use core\output\mustache_uniqid_helper;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use mod_minilesson\constants;
use mod_minilesson\utils;
use stored_file;


/**
 * AIGEN form
 *
 * @package mod_minilesson
 * @author  Justin Hunt
 */
class aigen_form extends \moodleform
{

    public function definition()
    {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'templateid');
        $mform->setType('templateid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('hidden', 'step1done');
        $mform->setType('step1done', PARAM_INT);

        $mform->addElement('hidden', 'step2done');
        $mform->setType('step2done', PARAM_INT);

        $mform->addElement('hidden', 'step3done');
        $mform->setType('step3done', PARAM_INT);

        $mform->addElement('header', 'step1', '1. Paste in an exported JSON import file');

        $mform->addElement('filepicker', 'importjson', 'Import JSON', null, ['accepted_types' => 'json']);

        $mform->registerNoSubmitButton('savestep1');
        $mform->addElement('submit', 'savestep1', 'Import JSON');

        $mform->_registerCancelButton('back');
    }

    public function definition_after_data()
    {
        global $DB, $PAGE;
        $mform = $this->_form;
        $id = $this->get_element_value('id');
        $step1done = $this->get_element_value('step1done');
        $step2done = $this->get_element_value('step2done');
        $step3done = $this->get_element_value('step3done');

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->_customdata['cm'] = $cm;
        $this->_customdata['mod'] = $moduleinstance;

        if (!$step1done && $this->optional_param('savestep1', null, PARAM_BOOL)) {
            $mform->setExpanded('step1', false, true);
            $step1done = 1;
            $mform->setConstant('step1done', $step1done);
        }

        // Get an admin settings.
        $config = get_config(constants::M_COMPONENT);

        // Get token.
        $token = utils::fetch_token($config->apiuser, $config->apisecret);

        if (!$step1done) {
            $mform->addElement('cancel', 'back', get_string('back'));
            return;
        }

        $renderer = $PAGE->get_renderer('mod_minilesson');

        // Prepare the page template data.
        $tdata = ['uniqid' => (string) new mustache_uniqid_helper];
        $tdata['cloudpoodllurl'] = utils::get_cloud_poodll_server();
        $tdata['pageurl'] = $PAGE->url->out(false);
        $tdata['language'] = $moduleinstance->ttslanguage;
        $tdata['token'] = $token;
        $tdata['region'] = $moduleinstance->region;
        $tdata['cmid'] = $cm->id;
        // If the form is submitted, process the data.
        if (empty($this->_customdata['jsonfile']) || $this->is_submitted()) {
            $importjson = $this->get_draft_files('importjson');
            $this->_customdata['jsonfile'] = reset($importjson);
        }
        $jsonfile = $this->_customdata['jsonfile'];
        $jsoncontent = $jsonfile instanceof stored_file ? $jsonfile->get_content() : null;
        $theactivity = json_decode($jsoncontent, true);
        // These are the items in the imported activity (that is the template lesson)
        $tdata['items'] = $theactivity['items'];

        // We will also need to fetch the file areas for each item.
        $contextfileareas = [];
        $availablecontext = self::mappings();

        // Now we loop through the items in the activity and fetch the AI generation prompt for each item.
        // We also fetch the placeholders for each item, and update the available context fields.
        // We also parse the prompt to get the prompt fields that we will match with availablecontext to make the full AI generation prompt.
        foreach ($tdata['items'] as $itemnumber => $item) {
            $itemtype = $item['type'];
            $itemclass = '\\mod_minilesson\\local\\itemtype\\item_' . $itemtype;
            if (class_exists($itemclass)) {
                $tdata['items'][$itemnumber]['itemnumber'] = $itemnumber;

                // Get the generatemethod.
                $tdata['items'][$itemnumber]['methodreuse'] = $this->get_element_value('generatemethod[' . $itemnumber . ']') == 'reuse';

                // Fetch the prompt
                $generatemethods = ['generate', 'extract'];
                foreach ($generatemethods as $method) {
                    $theprompt = $itemclass::aigen_fetch_prompt($theactivity, $method);
                    $tdata['items'][$itemnumber]['aigenprompt' . $method] = $theprompt;
                    // Parse the prompt to get the fields that we will use in the AI generation
                    // Extract fields which are words in curly brackets from the prompt.
                    $tdata['items'][$itemnumber]['promptfields' . $method] = utils::extract_curly_fields($theprompt);
                }
                // By default we will set prompt fields and generate methods to 'generate'.
                $tdata['items'][$itemnumber]['aigenprompt'] = $tdata['items'][$itemnumber]['aigenpromptgenerate'];
                $tdata['items'][$itemnumber]['aigenpromptfields'] = $tdata['items'][$itemnumber]['promptfieldsgenerate'];

                // Fetch the placeholders for this item.
                // The placeholders are the fields in the import JSON that we have the option to replace.
                $thisplaceholders = $itemclass::aigen_fetch_placeholders($item);
                $tdata['items'][$itemnumber]['aigenplaceholders'] = $thisplaceholders;

                // Set the available context for this item. This expands as we go through the items.
                // Because previously generated data is added to it.
                $tdata['items'][$itemnumber]['availablecontext'] = $availablecontext;

                // Fetch datavars for the item. Datavars are fields that can contain generated data, and used as context later on.
                // But they don't represent a prompt field or a placeholder specifically. They are just free to use variables.
                $datavars = ['data1', 'data2', 'data3', 'data4', 'data5'];
                $tdata['items'][$itemnumber]['datavars'] = $datavars;

                // Fetch the file areas for this item.
                $thefiles = $theactivity['files'];
                $thisfileareas = $itemclass::aigen_fetch_fileareas($item, $thefiles, $contextfileareas);
                $tdata['items'][$itemnumber]['aigenfileareas'] = $thisfileareas;
                $tdata['items'][$itemnumber]['contextfileareas'] = $contextfileareas;

                // Update available context.
                $thiscontext = array_map(function ($placeholder) use ($itemnumber) {
                    return 'item' . $itemnumber . '_' . $placeholder;
                }, $thisplaceholders);
                $thisdatavars = array_map(function ($datavar) use ($itemnumber) {
                    return 'item' . $itemnumber . '_' . $datavar;
                }, $datavars);
                $availablecontext = array_merge($availablecontext, $thiscontext, $thisdatavars);

                // Update available file areas.
                $itemfileareas = array_map(function ($filearea) use ($itemnumber) {
                    return 'item' . $itemnumber . '_' . $filearea;
                }, $thisfileareas);
                $contextfileareas = array_merge($contextfileareas, $itemfileareas);

            } else {
                debugging('Item type ' . $itemtype . ' does not exist', DEBUG_DEVELOPER);
            }
        }

        $mform->addElement('header', 'step2', '2. Configure Items');
        $mform->setExpanded('step2');
        $mform->addElement('html', $renderer->render_from_template(constants::M_COMPONENT . '/aigen', $tdata));

        // This is where we put the AI generation config.
        $mform->addElement(
            'hidden',
            'aigen_config',
            '',
            ['id' => "{$tdata['uniqid']}_aigen_make_textarea", 'class' => 'ml_aigen_make_textarea']
        );
        $mform->setType('aigen_config', PARAM_RAW);

        $mform->registerNoSubmitButton('savestep2');
        $mform->addElement(
            'submit',
            'savestep2',
            'Save Configuration',
            ['id' => "{$tdata['uniqid']}_aigen_make_btn", 'class' => 'ml_aigen_gen_aigen_make_btn']
        );

        if (!$step2done && $this->optional_param('savestep2', null, PARAM_BOOL)) {
            $mform->setExpanded('step2', false, true);
            $step2done = 1;
            $mform->setConstant('step2done', $step2done);
        }

        if (!$step2done) {
            $mform->addElement('cancel', 'back', get_string('back'));
            return;
        }

        $mform->addElement('header', 'step3', '3. Configure User Context Fields');
        $mform->setExpanded('step3');

        $typeoptions = self::type_options();

        foreach (self::mappings() as $fieldname) {
            $controls = [];
            $controls[0] = $mform->createElement('selectyesno', 'enabled', get_string('contextmapping:enabled', constants::M_COMPONENT));
            $controls[1] = $mform->createElement(
                'text',
                'title',
                get_string('contextmapping:title', constants::M_COMPONENT),
                ['placeholder' => get_string('contextmapping:title_desc', constants::M_COMPONENT)]
            );
            $controls[2] = $mform->createElement(
                'textarea',
                'description',
                get_string('contextmapping:description', constants::M_COMPONENT),
                ['placeholder' => get_string('contextmapping:description_desc', constants::M_COMPONENT)]
            );
            $controls[3] = $mform->createElement('select', 'type', get_string('contextmapping:description', constants::M_COMPONENT), $typeoptions);
            $controls[4] = $mform->createElement(
                'textarea',
                'options',
                get_string('contextmapping:options', constants::M_COMPONENT),
                ['placeholder' => get_string('contextmapping:options_desc', constants::M_COMPONENT)]
            );
            $mform->setType("{$fieldname}[title]", PARAM_TEXT);
            $mform->disabledIf("{$fieldname}[options]", "{$fieldname}[type]", 'neq', end($typeoptions));
            $mform->addGroup($controls, $fieldname, $fieldname);
        }

        $mform->registerNoSubmitButton('savestep3');
        $mform->addElement('submit', 'savestep3', 'Save Configuration');

        if (!$step3done && $this->optional_param('savestep3', null, PARAM_BOOL)) {
            $mform->setExpanded('step3', false, true);
            $step3done = 1;
            $mform->setConstant('step3done', $step3done);
        }

        if (!$step3done) {
            $mform->setExpanded('step3', true, true);
            $mform->addElement('cancel', 'back', get_string('back'));
            return;
        }

        $mform->addElement('header', 'step4', '4. Create the Lesson Template JSON config');
        $mform->setExpanded('step4');

        $mform->addElement(
            'text',
            'lessontitle',
            'Lesson Template Title',
            ['id' => "{$tdata['uniqid']}_ml_aigen_lesson_title", 'class' => 'ml_aigen_lesson_title', 'size' => 50]
        );
        $mform->setType('lessontitle', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'lessondescription',
            'Lesson Template Title Description',
            ['id' => "{$tdata['uniqid']}_ml_aigen_lesson_description", 'class' => 'ml_aigen_lesson_description', 'rows' => 5, 'cols' => 100]
        );
        $mform->setType('lessondescription', PARAM_TEXT);

        $mform->addElement('text', 'uniqueid', get_string('uniqueid', constants::M_COMPONENT), ['size' => 50]);
        $mform->setType('uniqueid', PARAM_ALPHANUM);
        if (!empty($this->_customdata['freezeuniqueid'])) {
            $mform->freeze('freezeuniqueid');
            $mform->setConstant('uniqueid', $this->_customdata['freezeuniqueid']);
        } else {
            $mform->addRule('uniqueid',  get_string('required'), 'required');
            $mform->applyFilter('uniqueid', 'trim');
        }

        $predefinedoptions = template_tag_manager::get_predefined_tags();
        $predefinedoptions = array_combine($predefinedoptions, $predefinedoptions);
        $mform->addElement(
            'autocomplete',
            'tags',
            get_string('templatetags', constants::M_COMPONENT),
            $predefinedoptions,
            'multiple'
        );

        $mform->addElement('text', 'version', get_string('version', constants::M_COMPONENT));
        $mform->setType('version', PARAM_INT);
        $mform->addRule('version',  get_string('required'), 'required');

        $mform->addElement('submit', 'savestep4', 'Create JSON Config');
        $mform->setExpanded('step4');
        $mform->addElement('cancel', 'back', get_string('back'));
    }

    public function set_data_for_dynamic_submission()
    {
        global $DB, $USER;
        $fs = get_file_storage();

        $formdata = [
            'id' => $this->optional_param('id', null, PARAM_INT),
            'templateid' => $this->optional_param('templateid', 0, PARAM_INT),
            'action' => $this->optional_param('action', null, PARAM_ALPHA),
            'uniqueid' => $this->optional_param('uniqueid', uniqid(), PARAM_ALPHANUM),
            'version' => $this->optional_param('version', 0, PARAM_ALPHANUM),
        ];
        if ($template = $DB->get_record('minilesson_templates', ['id' => $formdata['templateid']])) {
            $formdata['aigen_config'] = $template->config;
            $formdata['importjson'] = $this->optional_param('importjson', null, PARAM_INT);
            $formdata['lessontitle'] = $template->name;
            $formdata['lessondescription'] = $template->description;
            $formdata['step1done'] = $formdata['step2done'] = $formdata['step3done'] = 1;
            $formdata['uniqueid'] = $template->uniqueid;
            $formdata['version'] = $template->version;
            if ($DB->record_exists_select('minilesson_templates', 'uniqueid = :uniqueid AND version > :version',
            ['uniqueid' => $template->uniqueid, 'version' => $template->version])) {
                $this->_customdata['freezeuniqueid'] = $template->uniqueid;
            }
            if (empty($formdata['importjson'])) {
                $draftitemid = file_get_unused_draft_itemid();
                $usercontext = context_user::instance($USER->id);
                $filerecord = [
                    'contextid' => $usercontext->id,
                    'component' => 'user',
                    'filearea' => 'draft',
                    'itemid' => $draftitemid,
                    'filepath' => '/',
                    'filename' => 'template.json',
                ];
                $jsonfile = $fs->create_file_from_string($filerecord, $template->template);
                $this->_customdata['jsonfile'] = $jsonfile;
                $formdata['importjson'] = $draftitemid;
            }
            $jsonconfig = json_decode($template->config);
            if (!json_last_error() && !empty($jsonconfig->fieldmappings)) {
                foreach ($jsonconfig->fieldmappings as $fieldname => $fieldconfig) {
                    $formdata[$fieldname] = [
                        'enabled' => !empty($fieldconfig->enabled),
                        'title' => $fieldconfig->title,
                        'description' => $fieldconfig->description,
                        'type' => $fieldconfig->type,
                        'options' => isset($fieldconfig->options) ?
                            join(PHP_EOL, (array) $fieldconfig->options) : null,
                    ];
                }
            }

            $tabobjects = template_tag_manager::get_current_tags($template->id);
            $formdata['tags'] = array_column($tabobjects, 'tagname');
        }
        $this->set_data($formdata);
    }

    public function process_dynamic_submission()
    {
        global $DB;
        if (!$this->is_cancelled() && $this->is_submitted() && $this->is_validated()) {
            $formdata = $this->get_data();
            $tags = !empty($formdata->tags) ? $formdata->tags: [];

            $template = $DB->get_record('minilesson_templates', ['id' => $formdata->templateid]);
            if (!$template) {
                $template = new stdClass;
                $template->timecreated = time();
                $template->timemodified = 0;
            } else {
                $template->timemodified = time();
            }
            $template->name = $formdata->lessontitle;
            $template->description = $formdata->lessondescription;
            $template->config = $formdata->aigen_config;
            $template->uniqueid = $formdata->uniqueid;
            $template->version = $formdata->version;
            $template->template = $this->get_file_content('importjson');
            $jsonconfig = json_decode($template->config);
            if (!json_last_error()) {
                $jsonconfig->lessonTitle = $template->name;
                $jsonconfig->lessonDescription = $template->description;
                $jsonconfig->uniqueid = $template->uniqueid;
                $jsonconfig->version = $template->version;
                $availablecontext = self::mappings();
                $typeoptions = self::type_options();
                $fielddatas = array_filter((array) $formdata, function ($k) use ($availablecontext) {
                    return in_array($k, $availablecontext);
                }, ARRAY_FILTER_USE_KEY);
                foreach ($fielddatas as $fieldname => $fielddata) {
                    $fielddatas[$fieldname]['enabled'] = !empty($fielddata['enabled']);
                    if ($fielddata['type'] === end($typeoptions)) {
                        $fieldoptions = explode(PHP_EOL, $fielddata['options']);
                        $fieldoptions = array_map('trim', $fieldoptions);
                        $fieldoptions = array_filter($fieldoptions, 'trim');
                        $fielddatas[$fieldname]['options'] = $fieldoptions;
                    } else {
                        unset($fielddatas[$fieldname]['options']);
                    }
                }
                $jsonconfig->fieldmappings = $fielddatas;
                $template->config = json_encode($jsonconfig, JSON_PRETTY_PRINT);
            }
            $jsontemplate = json_decode($template->template);
            if (!json_last_error() && !empty($jsontemplate->files)) {
                // Files will be an array of fileareas each containing of files: filename = file content.
                // We don't want the file content in the template because its saved in DB, so we replace it with a placeholder'QQQQ'.
                // Test case: we want to translate an existing activity (and keep the images)
                $clearfiles = true;
                // If template name contains 'translate' we assume we want to keep the files.
                if (stripos($template->name, 'translate') !== false) {
                    $clearfiles = false;
                }
                if ($clearfiles) {
                    foreach ($jsontemplate->files as $fileareas) {
                        foreach ($fileareas as $j => $filearea) {
                            $fileareas->{$j} = array_fill_keys(array_keys((array) $filearea), 'QQQQ');
                        }
                    }
                }
                // Encode the template.
                $template->template = json_encode($jsontemplate, JSON_PRETTY_PRINT);
            }

            // Save the template
            if (!empty($template->id)) {
                $DB->update_record('minilesson_templates', $template);
            } else {
                $template->id = $DB->insert_record('minilesson_templates', $template);
            }

            template_tag_manager::store_template_tags($template, $tags);

            return $template;
        }
        return false;
    }

    public function get_element_value($elname)
    {
        $mform = $this->_form;
        return $mform->elementExists($elname) ? $mform->getElement($elname)->getValue() : null;
    }

    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        if ($record = $DB->get_record('minilesson_templates', ['uniqueid' => $data['uniqueid']])) {
            if ($record->id != $data['templateid']) {
                $errors['uniqueid'] = get_string('sametemplatefound', constants::M_COMPONENT, ['templatename' => $record->name]);
            }
        }
        return $errors;
    }

    public static function mappings()
    {
        // This will return a 1D list of field names, eg 'user_topic', 'user_level', 'user_text', etc.
        $contextdata = utils::fetch_usercontext_fields();
        $availablecontext = array_keys($contextdata);
        return $availablecontext;
    }

    public static function type_options()
    {
        $types = ['text', 'textarea', 'dropdown'];
        return array_combine($types, $types);
    }

}

