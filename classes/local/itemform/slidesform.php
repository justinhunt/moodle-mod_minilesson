<?php
/**
 * Form for creating/editing a slides item in a MiniLesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class slidesform extends baseform
{
    public $type = constants::TYPE_SLIDES;

    /**
     * Add any form fields specific to this item type.
     */
    public function custom_definition() {
        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('enterslidesmarkdown', constants::M_COMPONENT));
        $this->add_textarearesponse(constants::SLIDES_MARKDOWN, get_string('slidesmarkdown', constants::M_COMPONENT), true);
    }
}
