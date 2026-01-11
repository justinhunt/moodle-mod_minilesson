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

        // Markdown text area.
        $this->add_textarearesponse(constants::FICTION_YARN, get_string('fictionyarn', constants::M_COMPONENT), true);
        $mform->setDefault(constants::FICTION_YARN, constants::FICTION_YARN_DEFAULT);

        // Files upload area.
        $this->add_media_upload(constants::FILEANSWER . '1', get_string('fiction:attachments', constants::M_COMPONENT), false, 'image,audio,video', -1);

        /*
        $mform->registerNoSubmitButton('previewbutton');
        $previewbtn = $mform->addElement('submit', 'previewbutton', get_string('fiction:preview', constants::M_COMPONENT));
        $previewbtn->_generateId();
        $previewbtn->updateAttributes(['id' => $previewbtn->getAttribute('id') . '_' . random_string()]);
        // There is an issue because the region by default may not work in China (fiction is not properly init with region here).
        // So preview may not work in China region unless we load from different CDN. TBD.
        $PAGE->requires->js_call_amd(
            constants::M_COMPONENT . '/fiction',
            'register_previewbutton',
            [
                $previewbtn->getAttribute('id')
            ]
        );
        */
    }

}