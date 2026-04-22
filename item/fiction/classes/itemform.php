<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Form for creating/editing a fiction item in a MiniLesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_fiction;

use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;
use mod_minilesson\utils;

class itemform extends baseform
{
    /**
     * Add any form fields specific to this item type.
     */
    public function custom_definition() {
        global $PAGE;
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('fiction_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();
        $this->add_static_text('instructions', '', get_string('enterfictionyarn', constants::M_COMPONENT));

        // Yarn format text area.
        $fixedwidthfont = true;
        $this->add_textarearesponse(itemtype::YARN,
            get_string('fictionyarn', constants::M_COMPONENT), true, $fixedwidthfont);
        $mform->setDefault(itemtype::YARN, itemtype::YARN_DEFAULT);

        // Initialize CodeMirror generic Editor for Yarn
        $PAGE->requires->js_call_amd(
            constants::M_COMPONENT . '/codeeditor',
            'setupCodeEditor',
            ['id_' . itemtype::YARN, ['language' => 'yarn']]
        );

        // Syntax Checker
        $mform->registerNoSubmitButton('syntaxcheckbutton');
        $buttonid = 'syntaxcheckbutton_' . random_string(10);
        $mform->addElement('submit', 'syntaxcheckbutton', 
            get_string('fiction:syntaxcheckbutton', constants::M_COMPONENT), 
            ['id' => $buttonid]
        );        
        $PAGE->requires->js_call_amd(
            'minilessonitem_fiction/itemtype',
            'register_syntaxcheckbutton',
            [
                $buttonid,
                'id_' . itemtype::YARN,
                'syntaxcheckresults',
            ]
        );
        $this->add_static_text('yarnsyntaxcheckresults', '', '<div id="syntaxcheckresults"></div>');

        // Files upload area.
        $this->add_media_upload(constants::FILEANSWER . '1', get_string('fiction:attachments', constants::M_COMPONENT),
         false, 'image,audio,video', -1);

        $this->add_dropdown(itemtype::PRESENTATION_MODE, get_string('presentationmode', constants::M_COMPONENT),
         [
            0 => get_string('presentationmode_plain', constants::M_COMPONENT),
            1 => get_string('presentationmode_mobile_chat', constants::M_COMPONENT),
            2 => get_string('presentationmode_storymode', constants::M_COMPONENT),
        ], 0);

        $this->add_dropdown(itemtype::FLOWTHROUGH_MESSAGES, get_string('flowthroughmessages', constants::M_COMPONENT),
         [
            0 => get_string('no'),
            1 => get_string('yes'),
        ], 0);
        $this->add_static_text('flowthroughmessages_desc', '', get_string('flowthroughmessages_desc', constants::M_COMPONENT));

        $this->add_dropdown(itemtype::SHOW_NONOPTIONS, get_string('shownonoptions', constants::M_COMPONENT),
         [
            0 => get_string('no'),
            1 => get_string('yes'),
        ], 0);
        $this->add_static_text('shownonoptions_desc', '', get_string('shownonoptions_desc', constants::M_COMPONENT));

    }
}
