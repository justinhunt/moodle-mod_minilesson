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
 * Form for creating/editing a slides item in a MiniLesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_slides;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform {

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
    public function custom_definition() {
        global $PAGE;
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('slides_instructions1', constants::M_COMPONENT));
        $this->add_itemsettings_heading();

        $this->add_dropdown(
            itemtype::CONTENTTYPE,
            get_string('slides:contenttype', 'minilessonitem_slides'),
            [
                itemtype::CONTENTTYPE_MARKDOWN => get_string('slides:contenttype_markdown', 'minilessonitem_slides'),
                itemtype::CONTENTTYPE_HTML => get_string('slides:contenttype_html', 'minilessonitem_slides'),
            ],
            itemtype::CONTENTTYPE_MARKDOWN,
            ['data-control' => 'slidescontenttype']
        );

        $this->add_static_text('markdowninstructions', '', get_string('enterslidesmarkdown', 'minilessonitem_slides'), 'minilessonitem_slides');
        $this->add_static_text('htmlinstructions', '', get_string('enterslideshtml', 'minilessonitem_slides'), 'minilessonitem_slides');
        $mform->hideif('htmlinstructions', itemtype::CONTENTTYPE, 'eq', itemtype::CONTENTTYPE_MARKDOWN);
        $mform->hideif('markdowninstructions', itemtype::CONTENTTYPE, 'eq', itemtype::CONTENTTYPE_HTML);

        // Markdown/HTML text area.
        $this->add_textarearesponse(itemtype::MARKDOWN, get_string('slidesmarkdown', 'minilessonitem_slides'), true);
        $mform->setDefault(itemtype::MARKDOWN, itemtype::MARKDOWN_DEFAULT);

        // Initialize CodeMirror editor.
        $slidescontenttype = $this->_customdata['item']->{itemtype::CONTENTTYPE} ?? itemtype::CONTENTTYPE_MARKDOWN;
        $initiallanguage = $slidescontenttype == itemtype::CONTENTTYPE_HTML ? 'html' : 'markdown';
        $PAGE->requires->js_call_amd(
            constants::M_COMPONENT . '/codeeditor',
            'setupCodeEditor',
            [
                'id_' . itemtype::MARKDOWN,
                [
                    'language' => $initiallanguage,
                    'aihelper' => true,
                    'itemtype' => 'slides',
                    'contextid' => $this->context->id,
                ],
            ]   
        );

        // Add JS to switch editor language when Content Mode changes.
        $js = "
            (function() {
                const selectElement = document.querySelector('[data-control=\"slidescontenttype\"]');
                if (selectElement) {
                    selectElement.addEventListener('change', function() {
                        const lang = this.value == " . itemtype::CONTENTTYPE_HTML . " ? 'html' : 'markdown';
                        // The codeeditor AMD module should have a way to refresh or we just re-init if possible.
                        // However, Moodle's AMD may not easily allow re-calling setupCodeEditor on the same ID.
                        // Usually, the best way in mod_minilesson's generic codeeditor is to refresh it.
                        // Let's assume for now that we might need an update to codeeditor.js or a specific call.
                        // For this implementation, we will try to dispatch a custom event that codeeditor.js can listen to.
                        const event = new CustomEvent('ml_slides_contenttype_change', { detail: { language: lang } });
                        document.getElementById('id_" . itemtype::MARKDOWN . "').dispatchEvent(event);
                    });
                }
            })();
        ";
        $PAGE->requires->js_amd_inline($js);

        // Files upload area.
        $this->add_media_upload(constants::FILEANSWER . '1', get_string('slides:attachments', constants::M_COMPONENT), false, 'image,audio,video', -1);

        $themeoptions = array_combine(self::THEMES, self::THEMES);
        $mform->addElement('select', itemtype::SLIDETHEME, get_string('slides:theme', constants::M_COMPONENT), $themeoptions, ['data-control' => 'theme']);
        $mform->setType(itemtype::SLIDETHEME, PARAM_ALPHA);

        // Font size emtopx = 16; approx 1.6 = 24px,  1.8 = 28px,  2.0 = 32px, 2.2 = 36px,  2.4 = 40px
        $fontsizes[16] = get_string('slides:fontsmallest', constants::M_COMPONENT);
        $fontsizes[24] = get_string('slides:fontsmaller', constants::M_COMPONENT);
        $fontsizes[32] = get_string('slides:fontsmall', constants::M_COMPONENT);
        $fontsizes[36] = get_string('slides:fontstandard', constants::M_COMPONENT);
        $fontsizes[40] = get_string('slides:fontlarge', constants::M_COMPONENT);
        $fontsizes[44] = get_string('slides:fontlarger', constants::M_COMPONENT);
        $fontsizes[48] = get_string('slides:fontlargest', constants::M_COMPONENT);
        $mform->addElement('select', itemtype::SLIDEFONTSIZE, get_string('slides:fontsize', constants::M_COMPONENT), $fontsizes);
        $mform->setType(itemtype::SLIDEFONTSIZE, PARAM_FLOAT);
        $mform->setDefault(itemtype::SLIDEFONTSIZE, 32);

        $this->add_dropdown(
            itemtype::FULLSCREEN,
            get_string('fullscreen', constants::M_COMPONENT),
            [
                0 => get_string('no'),
                1 => get_string('yes'),
            ],
            0
        );

        $mform->registerNoSubmitButton('previewbutton');
        $previewbtn = $mform->addElement('submit', 'previewbutton', get_string('slides:preview', constants::M_COMPONENT));
        $previewbtn->_generateId();
        $previewbtn->updateAttributes(['id' => $previewbtn->getAttribute('id') . '_' . random_string()]);
        // There is an issue because the region by default may not work in China (slides is not properly init with region here).
        // So preview may not work in China region unless we load from different CDN. TBD.
        $PAGE->requires->js_call_amd(
            'minilessonitem_slides/itemtype',
            'register_previewbutton',
            [
                $previewbtn->getAttribute('id'),
            ]
        );
    }
}
