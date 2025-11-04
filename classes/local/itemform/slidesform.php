<?php
/**
 * Form for creating/editing a slides item in a MiniLesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson\local\itemform;

use mod_minilesson\constants;

class slidesform extends baseform
{
    public $type = constants::TYPE_SLIDES;

    // Got list from https://api.github.com/repos/hakimel/reveal.js/contents/css/theme/source?ref=5.2.1
    const THEMES = [
        'beige',
        'black',
        'black-contrast',
        'blood',
        'dracula',
        'league',
        'moon',
        'night',
        'serif',
        'simple',
        'sky',
        'solarized',
        'white',
        'white_contrast_compact_verbatim_headers',
        'white-contrast',
    ];

    /**
     * Add any form fields specific to this item type.
     */
    public function custom_definition()
    {
        global $PAGE;
        $this->add_itemsettings_heading();
        $mform = $this->_form;
        $this->add_static_text('instructions', '', get_string('enterslidesmarkdown', constants::M_COMPONENT));

        // Markdown text area.
        $this->add_textarearesponse(constants::SLIDES_MARKDOWN, get_string('slidesmarkdown', constants::M_COMPONENT), true);
        $mform->setDefault(constants::SLIDES_MARKDOWN, constants::SLIDES_MARKDOWN_DEFAULT);

        // Files upload area.
        $this->add_media_upload(constants::FILEANSWER . '1', get_string('slides:attachments', constants::M_COMPONENT), false, 'image,audio,video', -1);

        $themeoptions = array_combine(self::THEMES, self::THEMES);
        $mform->addElement('select', constants::SLIDETHEME, get_string('slides:theme', constants::M_COMPONENT), $themeoptions, ['data-control' => 'theme']);
        $mform->setType(constants::SLIDETHEME, PARAM_ALPHA);

        // Font size emtopx = 16; approx 1.6 = 24px,  1.8 = 28px,  2.0 = 32px, 2.2 = 36px,  2.4 = 40px
        $fontsizes[32] = get_string('slides:fontsmall', constants::M_COMPONENT);
        $fontsizes[36] = get_string('slides:fontsmaller', constants::M_COMPONENT);
        $fontsizes[40] = get_string('slides:fontstandard', constants::M_COMPONENT);
        $fontsizes[44] = get_string('slides:fontlarger', constants::M_COMPONENT);
        $fontsizes[48] = get_string('slides:fontlarge', constants::M_COMPONENT);
        $mform->addElement('select', constants::SLIDEFONTSIZE, get_string('slides:fontsize', constants::M_COMPONENT), $fontsizes);
        $mform->setType(constants::SLIDEFONTSIZE, PARAM_FLOAT);
        $mform->setDefault(constants::SLIDEFONTSIZE, 32);

        $mform->registerNoSubmitButton('previewbutton');
        $previewbtn = $mform->addElement('submit', 'previewbutton', get_string('slides:preview', constants::M_COMPONENT));
        $previewbtn->_generateId();
        $previewbtn->updateAttributes(['id' => $previewbtn->getAttribute('id') . '_' . random_string()]);
        // There is an issue because the region by default may not work in China (slides is not properly init with region here).
        // So preview may not work in China region unless we load from different CDN. TBD.
        $PAGE->requires->js_call_amd(
            constants::M_COMPONENT . '/slides',
            'register_previewbutton',
            [
                $previewbtn->getAttribute('id')
            ]
        );
    }
}
