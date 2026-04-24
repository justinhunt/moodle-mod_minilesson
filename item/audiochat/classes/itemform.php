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

namespace minilessonitem_audiochat;

use html_writer;
use mod_minilesson\constants;
use mod_minilesson\local\itemform\baseform;
use mod_minilesson\utils;
use moodle_url;

/*
 * Class itemform
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class itemform extends baseform {

    /**
     * item form definition
     * @return void
     */
    public function custom_definition() {
        global $CFG, $PAGE, $OUTPUT;

        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $itemid = $this->optional_param('itemid', 0, PARAM_INT);
        $moduleinstance = $this->_customdata['moduleinstance'];
        $this->add_static_text('instructions', '', get_string('audiochatdesc', constants::M_COMPONENT));

        $mform->setDefault(
            constants::TEXTINSTRUCTIONS,
            get_string('audiochat_instructions1', constants::M_COMPONENT)
        );

        // Total marks and target word count.
        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT), true);
        $mform->setDefault(constants::TOTALMARKS, 5);
        $this->add_static_text('totalmarks_instructions', '', get_string('totalmarks_instructions', constants::M_COMPONENT));
        $this->add_numericboxresponse(constants::TARGETWORDCOUNT, get_string('targetwordcount_title', constants::M_COMPONENT), false);
        $mform->setDefault(constants::TARGETWORDCOUNT, 60);

        $provideroptions = [
            'openai' => get_string('openai', 'minilessonitem_audiochat'),
            'gemini' => get_string('gemini', 'minilessonitem_audiochat'),
        ];
        $defaultprovider = get_config(constants::M_COMPONENT, 'provider') ?: 'gemini';
        $this->add_dropdown(
            itemtype::CHAT_PROVIDER,
            get_string('provider', 'minilessonitem_audiochat'),
            $provideroptions,
            $defaultprovider
        );

        // The topic for the audio chat.
        $this->add_textarearesponse(itemtype::TOPIC, get_string('audiochat_topic', constants::M_COMPONENT), true);
        $mform->setDefault(itemtype::TOPIC, get_string('audiochat_topic_default', constants::M_COMPONENT));

        // The role of the AI.
        $this->add_textarearesponse(itemtype::ROLE, get_string('audiochat_role', constants::M_COMPONENT), true);
        $mform->setDefault(itemtype::ROLE, get_string('audiochat_role_default', constants::M_COMPONENT));

        // The voice of the AI.
        $options = ['alloy' => 'Alloy', 'ash' => 'Ash', 'ballad' => 'Ballad',
            'coral' => 'Coral', 'echo' => 'Echo', 'sage' => 'Sage', 'shimmer' => 'Shimmer', 'verse' => 'Verse', 'marin' => 'Marin', 'cedar' => 'Cedar'];
        $this->add_dropdown(
            itemtype::VOICE,
            get_string('audiochat_voice', constants::M_COMPONENT),
            $options,
            'alloy'
        );

        // Students native language.
        $defaultfeedbacklang = $this->moduleinstance->nativelang ?
                    $this->moduleinstance->nativelang : $this->moduleinstance->ttslanguage;
        $this->add_languageselect(
            itemtype::NATIVE_LANGUAGE,
            get_string('audiochat_native_language', constants::M_COMPONENT),
            $defaultfeedbacklang
        );

        $options = utils::get_aiprompt_options('AUDIOCHAT_INSTRUCTIONSSELECTION');
        $mform->addElement(
            'select',
            itemtype::INSTRUCTIONSSELECTION,
            get_string('audiochat_instructions', constants::M_COMPONENT),
            $options,
            ['data-name' => 'instructionsaiprompt', 'data-type' => 'audiochat']
        );
        $mform->setDefault(itemtype::INSTRUCTIONSSELECTION, 0);
        $this->add_static_text('preset_instructions1', '', get_string('aigrade_instructions_preset', constants::M_COMPONENT));

        // The instructions template for the audio chat.
        $this->add_textarearesponse(itemtype::INSTRUCTIONS, '', true);
        $mform->getElement(itemtype::INSTRUCTIONS)->updateAttributes(['data-name' => 'aigrade_instructions']);
        $default = get_config(constants::M_COMPONENT, 'audiochat_instructionsprompt_1');
        $mform->setDefault(itemtype::INSTRUCTIONS, $default);
        $this->add_static_text('audiochat_instructions_instructions', '', get_string('audiochat_instructions_instructions', constants::M_COMPONENT));

        // The grading/feedback template for the audio chat.
        $options = utils::get_aiprompt_options('AUDIOCHAT_FEEDBACKSELECTION');
        $mform->addElement(
            'select',
            itemtype::FEEDBACKSELECTION,
            get_string('aigrade_feedback', constants::M_COMPONENT),
            $options,
            ['data-name' => 'feedbackaiprompt', 'data-type' => 'audiochat',]
        );
        $mform->setDefault(itemtype::FEEDBACKSELECTION, 0);
        $this->add_static_text('preset_instructions2', '', get_string('aigrade_instructions_preset', constants::M_COMPONENT));

        // The instructions for grading the audio chat.
        $this->add_textarearesponse(itemtype::FEEDBACKINSTRUCTIONS, '', false);
        $mform->getElement(itemtype::FEEDBACKINSTRUCTIONS)->updateAttributes(['data-name' => 'aigrade_feedback']);
        $default = get_config(constants::M_COMPONENT, 'audiochat_feedbackprompt_1');
        $mform->setDefault(itemtype::FEEDBACKINSTRUCTIONS, $default);
        $this->add_static_text('audiochat_gradeinstructions_instructions', '', get_string('audiochat_gradeinstructions_instructions', constants::M_COMPONENT));

        // Auto Response.
        $mform->addElement(
            'advcheckbox',
            itemtype::AUTORESPONSE,
            get_string('audiochat_autosend', constants::M_COMPONENT),
            get_string('audiochat_autosend_desc', constants::M_COMPONENT),
            [],
            [0, 1]
        );
        $mform->setDefault(itemtype::AUTORESPONSE, 1);

        // Allow Retry.
        $this->add_allowretry(itemtype::ALLOWRETRY, get_string('audiochatretry_desc', constants::M_COMPONENT));

        // Time limit if we need one.
        $this->add_timelimit(constants::TIMELIMIT, get_string(constants::TIMELIMIT, constants::M_COMPONENT));

        // Avatar image selection.
        $imagefiles = glob($CFG->dirroot . '/mod/minilesson/item/audiochat/pix/audiochatavatar*.{jpg,jpeg,png}', GLOB_BRACE);
        if (!empty($imagefiles)) {
            foreach ($imagefiles as $imagefilepath) {
                if (file_exists($imagefilepath)) {
                    if (empty($groupelements)) {
                        $defaultimagepath = str_replace(basename($imagefilepath), itemtype::DEFAULT_AVATAR, $imagefilepath);
                        $groupelements[] = $mform->createElement('html', '<div class="audiochatavtarimage-container">');
                        $groupelements[] = $mform->createElement(
                            'radio',
                            itemtype::AUDIOAVATAR,
                            null,
                            html_writer::img(
                                $OUTPUT->image_url(pathinfo($defaultimagepath, PATHINFO_FILENAME), self::get_component()),
                                basename($defaultimagepath),
                                ['class' => 'avatar-img']
                            ),
                            basename($defaultimagepath),
                            ['class' => 'avatar-image-check']
                        );

                        $mform->setDefault(itemtype::AUDIOAVATAR, basename($defaultimagepath));
                    }
                    $groupelements[] = $mform->createElement(
                        'radio',
                        itemtype::AUDIOAVATAR,
                        null,
                        html_writer::img(
                            $OUTPUT->image_url(pathinfo($imagefilepath, PATHINFO_FILENAME), self::get_component()),
                            basename($imagefilepath),
                            ['class' => 'avatar-img']
                        ),
                        basename($imagefilepath),
                        ['class' => 'avatar-image-check']
                    );
                }
            }

            if (!empty($groupelements)) {
                $groupelements[] = $mform->createElement('html', '</div>');
                $mform->addGroup($groupelements, 'avatargroup', get_string('audioavatar', constants::M_COMPONENT), '<!--br-->', false);
            }
        }

        // The custom AI data fields.
        $mform->addElement('header', 'aicontextheading', get_string('aicontextheading', constants::M_COMPONENT));
        $mform->setExpanded('aicontextheading');
        $this->add_static_text('aicontext_instructions', '', get_string('aicontext_instructions', constants::M_COMPONENT));

        // Student submission.
        if ($moduleinstance) {
            $submissionoptions = [0 => get_string('choose')];
            $lessonitems = utils::get_lesson_items($moduleinstance->id, $itemid);
            foreach ($lessonitems as $item) {
                $submissionoptions[$item->id] = $item->name;
            }
            $this->add_dropdown(
                itemtype::STUDENT_SUBMISSION,
                get_string('audiochat_student_submission', constants::M_COMPONENT),
                $submissionoptions
            );
            $this->add_static_text('audiochat_student_submission_instructions', '', get_string('audiochat_student_submission_instructions', constants::M_COMPONENT));
        }
        // AI Data 1 and 2.
        $this->add_textarearesponse(itemtype::AIDATA1, get_string('audiochat_aidata1', constants::M_COMPONENT), false);
        $mform->setDefault(itemtype::AIDATA1, '');
        $this->add_textarearesponse(itemtype::AIDATA2, get_string('audiochat_aidata2', constants::M_COMPONENT), false);
        $mform->setDefault(itemtype::AIDATA2, '');

        $PAGE->requires->js_call_amd(static::get_component() . '/aiprompt', 'init');
    }
}
