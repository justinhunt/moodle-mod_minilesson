<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class freewritingform extends baseform {

    public $type = constants::TYPE_FREEWRITING;

    public function custom_definition() {
        global $CFG, $PAGE;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('freewritingdesc', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('freewritingtotalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);

        $options = [
            0 => '--',
            1 => get_string('default'),
            2 => get_string('freewriting:gradingprompt1', constants::M_COMPONENT),
            3 => get_string('freewriting:gradingprompt2', constants::M_COMPONENT),
        ];
        $mform->addElement('select', constants::FREEWRITING_GRADINGSELECTION, get_string('aigrade_instructions', constants::M_COMPONENT), $options,
            ['data-name' => 'gradingaiprompt', 'data-type' => 'freewriting']);
        $mform->setDefault(constants::FREEWRITING_GRADINGSELECTION, 1);

        $this->add_textarearesponse(constants::AIGRADE_INSTRUCTIONS, '', true);
        $mform->getElement(constants::AIGRADE_INSTRUCTIONS)->updateAttributes(['data-name' => 'aigrade_instructions']);
        $mform->setDefault(constants::AIGRADE_INSTRUCTIONS, get_string('freewriting:gradingprompt_dec1', constants::M_COMPONENT));

        $options = [
            0 => '--',
            1 => get_string('default'),
            2 => get_string('freewriting:feedbackprompt1', constants::M_COMPONENT),
        ];
        $mform->addElement('select', constants::FREEWRITING_FEEDBACKSELECTION, get_string('aigrade_feedback', constants::M_COMPONENT), $options,
            ['data-name' => 'feedbackaiprompt', 'data-type' => 'freewriting',]);
        $mform->setDefault(constants::FREEWRITING_FEEDBACKSELECTION, 1);

        $this->add_textarearesponse(constants::AIGRADE_FEEDBACK, get_string('aigrade_feedback', constants::M_COMPONENT), true);
        $mform->getElement(constants::AIGRADE_FEEDBACK)->updateAttributes(['data-name' => 'aigrade_feedback']);
        $mform->setDefault(constants::AIGRADE_FEEDBACK, get_string('freewriting:feedbackprompt_dec1', constants::M_COMPONENT));
        // Feedback language.
        $this->add_languageselect(constants::AIGRADE_FEEDBACK_LANGUAGE,
            get_string('aigrade_feedback_language', constants::M_COMPONENT),
            constants::M_LANG_ENUS
        );

        $this->add_relevanceoptions(constants::RELEVANCE, get_string('relevancetype', constants::M_COMPONENT),
            constants::RELEVANCETYPE_NONE);
        $this->add_textarearesponse(constants::AIGRADE_MODELANSWER, get_string('aigrade_modelanswer', constants::M_COMPONENT), false);
        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $mform->hideIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        } else {
            $mform->disabledIf(constants::AIGRADE_MODELANSWER, constants::RELEVANCE, 'neq', constants::RELEVANCETYPE_MODELANSWER);
        }

        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));
        $this->add_nopasting(constants::NOPASTING, get_string('nopasting_desc', constants::M_COMPONENT));

        $this->add_textarearesponse(constants::FREEWRITING_TOPIC, get_string('topic_placeholder', constants::M_COMPONENT),  false);

        $this->add_textarearesponse(constants::FREEWRITING_AIDATA1, get_string('aidata1_placeholder', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREEWRITING_AIDATA1, '');
        $this->add_textarearesponse(constants::FREEWRITING_AIDATA2, get_string('aidata2_placeholder', constants::M_COMPONENT), false);
        $mform->setDefault(constants::FREEWRITING_AIDATA2, '');

        $this->add_checkbox(constants::FREEWRITING_HIDECORRECTION, get_string('hidecorrection', constants::M_COMPONENT), null, 0);

        $options  = [
            1 => get_string('starrating', constants::M_COMPONENT),
            2 => get_string('percentagescore', constants::M_COMPONENT)
        ];
        $this->add_dropdown(constants::FREEWRITING_SHOWGRADE, get_string('showgrade', constants::M_COMPONENT), $options, 1);

        $options  = [
            1 => get_string('detailedresults', constants::M_COMPONENT),
            2 => get_string('basciresult', constants::M_COMPONENT)
        ];
        $this->add_dropdown(constants::FREEWRITING_SHOWRESULT, get_string('showresult', constants::M_COMPONENT), $options, 1);

        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/aiprompt', 'init');
    }
}