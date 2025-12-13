<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;
use mod_minilesson\utils;

class freewritingform extends baseform
{

    public $type = constants::TYPE_FREEWRITING;

    public function custom_definition()
    {
        global $CFG, $PAGE;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        // Instructions.
        $this->add_static_text('instructions', '', get_string('freewritingdesc', constants::M_COMPONENT));

        // Total marks and target word count
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('freewritingtotalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));

        // Target word count
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);

        // Time limit and no pasting
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_nopasting(constants::NOPASTING, get_string('nopasting_desc', constants::M_COMPONENT));

        // AI Grading Instuctions - presets dropdown
        $mform->addElement('header', 'ai_settings', get_string('aigradingandfeedback', constants::M_COMPONENT));
        $options = utils::get_aiprompt_options('FREEWRITING_GRADINGSELECTION');
        $mform->addElement(
            'select',
            constants::FREEWRITING_GRADINGSELECTION,
            get_string('aigrade_instructions', constants::M_COMPONENT),
            $options,
            ['data-name' => 'gradingaiprompt', 'data-type' => 'freewriting']
        );
        $mform->setDefault(constants::FREEWRITING_GRADINGSELECTION, 0);
        $this->add_static_text('preset_instructions1', '', get_string('aigrade_instructions_preset', constants::M_COMPONENT));

        // AI Grading Instuctions.
        $this->add_textarearesponse(constants::AIGRADE_INSTRUCTIONS, '', true);
        $this->add_static_text('aigrade_instructions_desc', '', get_string('aigrade_instructions_desc', constants::M_COMPONENT));
        $mform->getElement(constants::AIGRADE_INSTRUCTIONS)->updateAttributes(['data-name' => 'aigrade_grade']);
        $default = get_config(constants::M_COMPONENT, 'freewriting_gradingprompt_1');
        $mform->setDefault(constants::AIGRADE_INSTRUCTIONS, $default);

        // AI Feedback - Presets Dropdown
        $options = utils::get_aiprompt_options('FREEWRITING_FEEDBACKSELECTION');
        $mform->addElement(
            'select',
            constants::FREEWRITING_FEEDBACKSELECTION,
            get_string('aigrade_feedback', constants::M_COMPONENT),
            $options,
            ['data-name' => 'feedbackaiprompt', 'data-type' => 'freewriting',]
        );
        $mform->setDefault(constants::FREEWRITING_FEEDBACKSELECTION, 0);
        $this->add_static_text('preset_instructions2', '', get_string('aigrade_instructions_preset', constants::M_COMPONENT));

        // AI Feedback.
        $this->add_textarearesponse(constants::AIGRADE_FEEDBACK, get_string('aigrade_feedback', constants::M_COMPONENT), true);
        $this->add_static_text('aigrade_feedback_desc', '', get_string('aigrade_feedback_desc', constants::M_COMPONENT));
        $mform->getElement(constants::AIGRADE_FEEDBACK)->updateAttributes(['data-name' => 'aigrade_feedback']);
        $default = get_config(constants::M_COMPONENT, 'freewriting_feedbackprompt_1');
        $mform->setDefault(constants::AIGRADE_FEEDBACK, $default);

        // AI Feedback language.
        $defaultfeedbacklang = $this->moduleinstance->nativelang ?
            $this->moduleinstance->nativelang : $this->moduleinstance->ttslanguage;
        $this->add_languageselect(
            constants::AIGRADE_FEEDBACK_LANGUAGE,
            get_string('aigrade_feedback_language', constants::M_COMPONENT),
            $defaultfeedbacklang
        );

        // Relevance settings.
        $this->add_relevanceoptions(
            constants::RELEVANCE,
            get_string('relevancetype', constants::M_COMPONENT),
            constants::RELEVANCETYPE_NONE
        );
        $mform->addHelpButton(constants::RELEVANCE, 'relevancetype', constants::M_COMPONENT);
        $this->add_textarearesponse(constants::AIGRADE_MODELANSWER, get_string('aigrade_modelanswer', constants::M_COMPONENT), false);
        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $mform->hideIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        } else {
            $mform->disabledIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        }

        // AI Topic and additional AI Data to be used as context in prompts
        $this->add_textarearesponse(constants::FREEWRITING_TOPIC, get_string('ai_topic', constants::M_COMPONENT), false);
        $mform->addHelpButton(constants::FREEWRITING_TOPIC, 'ai_topic', constants::M_COMPONENT);
        $this->add_textarearesponse(constants::FREEWRITING_AIDATA1, get_string('ai_data1', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREEWRITING_AIDATA1, '');
        $mform->addHelpButton(constants::FREEWRITING_AIDATA1, 'ai_data1', constants::M_COMPONENT);
        $this->add_textarearesponse(constants::FREEWRITING_AIDATA2, get_string('ai_data2', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREEWRITING_AIDATA2, '');
        $mform->addHelpButton(constants::FREEWRITING_AIDATA2, 'ai_data2', constants::M_COMPONENT);

        // Add the "Results Display" fieldset
        $mform->addElement('header', 'resultsdisplay', get_string('resultsdisplay', constants::M_COMPONENT));

        $options = [
            1 => get_string('starrating', constants::M_COMPONENT),
            2 => get_string('percentagescore', constants::M_COMPONENT),
        ];
        $this->add_dropdown(constants::FREEWRITING_SHOWGRADE, get_string('showgrade', constants::M_COMPONENT), $options, 1);

        // Result display options
        $options = [
            1 => get_string('detailedresults', constants::M_COMPONENT),
            2 => get_string('basicresult', constants::M_COMPONENT),
        ];
        $this->add_dropdown(constants::FREEWRITING_SHOWRESULT, get_string('showresult', constants::M_COMPONENT), $options, 1);
        $this->add_checkbox(constants::FREEWRITING_HIDECORRECTION, get_string('hidecorrection', constants::M_COMPONENT), null, 0);

        // JS for the AI Prompt Preset Dropdown fields
        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/aiprompt', 'init');
    }
}