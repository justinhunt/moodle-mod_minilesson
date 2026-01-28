<?php

/**
 * Form for creating/editing a fiction item in a MiniLesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class fictionform extends baseform
{
    public $type = constants::TYPE_FICTION;

    /**
     * Add any form fields specific to this item type.
     */
    public function custom_definition()
    {
        global $PAGE;
        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('enterfictionyarn', constants::M_COMPONENT));

        // Yarn format text area.
        $fixedwidthfont = true;
        $this->add_textarearesponse(constants::FICTION_YARN,
            get_string('fictionyarn', constants::M_COMPONENT), true, $fixedwidthfont);
        $mform->setDefault(constants::FICTION_YARN, constants::FICTION_YARN_DEFAULT);

        // Syntax Checker
        $mform->registerNoSubmitButton('syntaxcheckbutton');
        $syntaxcheckbtn = $mform->addElement('submit', 'syntaxcheckbutton', get_string('fiction:syntaxcheckbutton', constants::M_COMPONENT));
        $syntaxcheckbtn->_generateId();
        $syntaxcheckbtn->updateAttributes(['id' => $syntaxcheckbtn->getAttribute('id') . '_' . random_string()]);
        $PAGE->requires->js_call_amd(
            constants::M_COMPONENT . '/fiction',
            'register_syntaxcheckbutton',
            [
                $syntaxcheckbtn->getAttribute('id'),
                'id_' . constants::FICTION_YARN,
                'syntaxcheckresults',
            ]
        );
        $this->add_static_text('yarnsyntaxcheckresults', '','<div id="syntaxcheckresults"></div>');


        // Files upload area.
        $this->add_media_upload(constants::FILEANSWER . '1', get_string('fiction:attachments', constants::M_COMPONENT),
         false, 'image,audio,video', -1);

        $this->add_dropdown(constants::FICTION_PRESENTATION_MODE, get_string('presentationmode', constants::M_COMPONENT),
         [
            0 => get_string('presentationmode_plain', constants::M_COMPONENT),
            1 => get_string('presentationmode_mobile_chat', constants::M_COMPONENT),
            2 => get_string('presentationmode_storymode', constants::M_COMPONENT),
        ], 0);

        $this->add_dropdown(constants::FICTION_FLOWTHROUGH_MESSAGES, get_string('flowthroughmessages', constants::M_COMPONENT),
         [
            0 => get_string('no'),
            1 => get_string('yes'),
        ], 0);
        $this->add_static_text('flowthroughmessages_desc', '', get_string('flowthroughmessages_desc', constants::M_COMPONENT));

        $this->add_dropdown(constants::FICTION_SHOW_NONOPTIONS, get_string('shownonoptions', constants::M_COMPONENT),
         [
            0 => get_string('no'),
            1 => get_string('yes'),
        ], 0);
        $this->add_static_text('shownonoptions_desc', '', get_string('shownonoptions_desc', constants::M_COMPONENT));

    }
}
