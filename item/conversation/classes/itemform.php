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

namespace minilessonitem_conversation;

use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;

/**
 * Authoring form for the conversation item type.
 *
 * @package    minilessonitem_conversation
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {
    /** @var string The item type class. */
    public const ITEMCLASS = itemtype::class;

    /**
     * Add the item type specific form fields.
     *
     * @return void
     */
    public function custom_definition() {
        $mform = $this->_form;
        $component = 'minilessonitem_conversation';
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('defaultinstructions', $component));
        $this->add_itemsettings_heading();

        // The dialog.
        $this->add_textarearesponse(itemtype::DIALOG, get_string('dialog', $component), true, true);
        $mform->addHelpButton(itemtype::DIALOG, 'dialog', $component);

        // Who speaks which part.
        $this->add_dropdown(itemtype::STUDENTROLE, get_string('studentrole', $component), [
            itemtype::ROLE_A => get_string('role_a', $component),
            itemtype::ROLE_B => get_string('role_b', $component),
            itemtype::ROLE_CHOOSE => get_string('role_choose', $component),
        ], itemtype::ROLE_B);
        $mform->addHelpButton(itemtype::STUDENTROLE, 'studentrole', $component);

        $this->add_textboxresponse(itemtype::LABELA, get_string('labela', $component));
        $this->add_textboxresponse(itemtype::LABELB, get_string('labelb', $component));
        $mform->addHelpButton(itemtype::LABELA, 'labela', $component);
        $this->add_ttsaudioselect(itemtype::VOICEA, get_string('voicea', $component));
        $this->add_ttsaudioselect(itemtype::VOICEB, get_string('voiceb', $component));
        $this->add_voiceoptions(
            constants::POLLYOPTION,
            get_string('choosevoiceoption', constants::M_COMPONENT),
            false,
            [],
            true
        );

        // How the student's lines are checked and prompted.
        $this->add_dropdown(itemtype::CHECKMODE, get_string('checkmode', $component), [
            itemtype::CHECK_AUTO => get_string('check_auto', $component),
            itemtype::CHECK_SELF => get_string('check_self', $component),
        ], itemtype::CHECK_AUTO);
        $mform->addHelpButton(itemtype::CHECKMODE, 'checkmode', $component);

        $this->add_dropdown(itemtype::PROMPTHELP, get_string('prompthelp', $component), [
            itemtype::PROMPT_FULL => get_string('prompt_full', $component),
            itemtype::PROMPT_INITIALS => get_string('prompt_initials', $component),
            itemtype::PROMPT_HIDDEN => get_string('prompt_hidden', $component),
        ], itemtype::PROMPT_FULL);
        $mform->addHelpButton(itemtype::PROMPTHELP, 'prompthelp', $component);

        $this->add_dropdown(itemtype::PROMPTLOOK, get_string('promptlook', $component), [
            itemtype::LOOK_DOTTED => get_string('look_dotted', $component),
            itemtype::LOOK_GREY => get_string('look_grey', $component),
        ], itemtype::LOOK_DOTTED);

        // The scene picture behind the chat. Only one is used, but with room for two the AI image
        // repository stays open in the file picker after an image is added, so it can still be edited.
        $sceneoptions = array_merge($this->filemanageroptions, ['accepted_types' => 'image', 'maxfiles' => 2]);
        $mform->addElement('filemanager', itemtype::SCENE_FILEAREA, get_string('scene', $component), [], $sceneoptions);
        $mform->addHelpButton(itemtype::SCENE_FILEAREA, 'scene', $component);

        // Speech recognition tuning.
        $this->add_textarearesponse(constants::ALTERNATES, get_string('alternates', constants::M_COMPONENT), false);
        $this->add_static_text('alternates_instructions', '', get_string('pr_alternates_instructions', constants::M_COMPONENT));
        $mform->hideIf(constants::ALTERNATES, itemtype::CHECKMODE, 'eq', itemtype::CHECK_SELF);
        $mform->hideIf('alternates_instructions', itemtype::CHECKMODE, 'eq', itemtype::CHECK_SELF);
    }

    /**
     * Both speakers need at least one line.
     *
     * @param array $data
     * @param array $files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (isset($data[itemtype::DIALOG])) {
            $speakers = array_unique(array_map(function ($turn) {
                return $turn->speaker;
            }, itemtype::parse_dialog($data[itemtype::DIALOG])));
            if (count($speakers) < 2) {
                $errors[itemtype::DIALOG] = get_string('error:dialogspeakers', 'minilessonitem_conversation');
            }
        }
        return $errors;
    }
}
