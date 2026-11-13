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

/**
 * Authoring form for the speed reading item type.
 *
 * @package    minilessonitem_speedreading
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace minilessonitem_speedreading;

use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;

/**
 * Authoring form for the speed reading item type.
 *
 * The form is laid out in sections named after the screens the student sees: the introduction
 * (the standard title, instructions, item text and a picture), the reading passage, the questions,
 * and the results. The standard layout choice and media prompt chooser do not apply to this item and
 * are left out.
 *
 * @package    minilessonitem_speedreading
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {
    /** @var string The id of the passage editor, for the live word count. */
    const PASSAGE_EDITOR_ID = 'ml_speedreading_passage';

    /**
     * The only media this item has is an optional picture on the introduction screen. Audio, video or
     * text to speech would undo the reading exercise, so the standard media prompt chooser is replaced.
     *
     * @return void
     */
    protected function add_media_prompts() {
        $this->add_media_upload(constants::MEDIAQUESTION, get_string('picture', self::fetch_component()), false, 'image', 1);
    }

    /**
     * Add the item type specific form fields.
     *
     * @return void
     */
    public function custom_definition() {
        global $OUTPUT, $PAGE;

        $mform = $this->_form;
        $component = self::fetch_component();
        $characters = itemtype::counts_characters((string) ($this->moduleinstance->ttslanguage ?? ''));
        $unitstring = $characters ? 'charactersperminute' : 'wordsperminute';

        // How the item works, above everything else.
        $explainer = $OUTPUT->render_from_template($component . '/formexplainer', []);
        $mform->insertElementBefore($mform->createElement('static', 'speedreadingexplainer', '', $explainer), 'name');

        // Screen 1, the introduction: the standard fields, relabelled for this item.
        $mform->insertElementBefore(
            $mform->createElement('header', 'screen1heading', get_string('screen1', $component)),
            'name'
        );
        $mform->setExpanded('screen1heading', true);
        if ($mform->elementExists(constants::TEXTINSTRUCTIONS)) {
            $mform->getElement(constants::TEXTINSTRUCTIONS)->setLabel(get_string('instructions', $component));
            $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('defaultinstructions', $component));
        }
        if ($mform->elementExists(constants::TEXTQUESTION)) {
            $mform->getElement(constants::TEXTQUESTION)->setLabel(get_string('introtext', $component));
        }
        if ($mform->elementExists(constants::TEXTQUESTION . '_editor')) {
            $mform->getElement(constants::TEXTQUESTION . '_editor')->setLabel(get_string('introtext', $component));
        }
        foreach ([constants::LAYOUT, 'mediapromptsheading'] as $unused) {
            if ($mform->elementExists($unused)) {
                $mform->removeElement($unused);
            }
        }
        // With rich text prompts the standard fields are a single editor and no media, so add the picture here.
        if (!$mform->elementExists(constants::MEDIAQUESTION)) {
            $this->add_media_prompts();
        }
        $this->add_static_text('introtext_details', '', get_string('introtext_details', $component));

        // Screen 2, the reading passage.
        $mform->addElement('header', 'screen2heading', get_string('screen2', $component));
        $mform->setExpanded('screen2heading', true);
        $mform->addElement(
            'editor',
            constants::QUESTIONTEXTAREA . '_editor',
            get_string('passage', $component),
            ['id' => self::PASSAGE_EDITOR_ID, 'rows' => 12],
            constants::ITEMTEXTAREA_EDOPTIONS
        );
        $mform->setDefault(constants::QUESTIONTEXTAREA . '_editor', ['text' => '', 'format' => FORMAT_HTML]);
        $mform->setType(constants::QUESTIONTEXTAREA, PARAM_RAW);
        $mform->addElement(
            'static',
            'passagestatus',
            '',
            \html_writer::div('', 'ml_speedreading_passagestatus', ['data-region' => 'passagestatus'])
                . \html_writer::div(get_string('passage_details', $component), 'text-muted small')
        );

        $mform->addElement('text', itemtype::WORDCOUNT, get_string('wordcount', $component), ['size' => 6]);
        $mform->setType(itemtype::WORDCOUNT, PARAM_INT);
        $this->add_static_text('wordcount_details', '', get_string('wordcount_details', $component));

        $this->add_checkbox(
            itemtype::SHOWTIMER,
            get_string('showtimer', $component),
            get_string('showtimer_details', $component),
            1
        );

        // Screen 3, the questions.
        $mform->addElement('header', 'screen3heading', get_string('screen3', $component));
        $mform->setExpanded('screen3heading', true);
        $this->add_static_text('questions_details', '', get_string('questions_details', $component));
        $this->add_checkbox(
            itemtype::SHOWALLQUESTIONS,
            get_string('showallquestions', $component),
            get_string('showallquestions_details', $component)
        );
        $this->add_checkbox(itemtype::SHUFFLEANSWER, get_string('shuffleanswer', constants::M_COMPONENT));

        $correctanswernumbers = range(1, constants::MAXANSWERS);
        $correctansweroptions = array_combine($correctanswernumbers, $correctanswernumbers);
        for ($qnumber = 1; $qnumber <= itemtype::MAXQUESTIONS; $qnumber++) {
            $required = $qnumber === 1;
            $mform->addElement('header', 'questionheading' . $qnumber, get_string('questionno', $component, $qnumber));
            $mform->setExpanded('questionheading' . $qnumber, $required);

            $questionfield = itemtype::col_questiontext($qnumber);
            $this->add_textarearesponse($questionfield, get_string('questiontext', $component), $required);
            $mform->setType($questionfield, PARAM_RAW);

            $answersfield = itemtype::col_answers($qnumber);
            $this->add_sentenceprompt($answersfield, get_string('questionanswers', $component), $required);

            $this->add_dropdown(
                itemtype::col_correctanswer($qnumber),
                get_string('correctanswer', constants::M_COMPONENT),
                $correctansweroptions,
                1
            );

            $feedbackfield = itemtype::col_feedback($qnumber);
            $mform->addElement(
                'textarea',
                $feedbackfield,
                get_string('questionfeedback', $component),
                ['wrap' => 'virtual', 'style' => 'width: 100%;']
            );
            $mform->setType($feedbackfield, PARAM_RAW);
        }

        // Screen 4, the results and grading.
        $mform->addElement('header', 'screen4heading', get_string('screen4', $component));
        $mform->setExpanded('screen4heading', true);

        $passmark = [
            $mform->createElement('text', itemtype::PASSMARK, '', ['size' => 4]),
            $mform->createElement('static', 'passmarkunit', '', '%'),
        ];
        $mform->addGroup($passmark, 'passmarkgroup', get_string('passmark', $component), ' ', false);
        $mform->setType(itemtype::PASSMARK, PARAM_INT);
        $mform->setDefault(itemtype::PASSMARK, itemtype::DEFAULT_PASSMARK);
        $mform->addElement(
            'static',
            'passmark_details',
            '',
            get_string('passmark_details', $component)
                . ' ' . \html_writer::span('', 'font-weight-bold', ['data-region' => 'passmarkstatus'])
        );

        $target = [
            $mform->createElement('text', itemtype::TARGETWPM, '', ['size' => 5]),
            $mform->createElement('static', 'targetwpmunit', '', get_string($unitstring, $component)),
        ];
        $mform->addGroup($target, 'targetwpmgroup', get_string('targetwpm', $component), ' ', false);
        $mform->setType(itemtype::TARGETWPM, PARAM_INT);
        $mform->setDefault(itemtype::TARGETWPM, itemtype::DEFAULT_TARGETWPM);
        $this->add_static_text('targetwpm_details', '', get_string('targetwpm_details', $component));

        $this->add_dropdown(
            itemtype::NOGRADE,
            get_string('gradeitem', $component),
            [0 => get_string('yes'), 1 => get_string('no')],
            0
        );
        $this->add_static_text('gradeitem_details', '', get_string('gradeitem_details', $component));

        // The live word count and pass mark notes.
        $questionfields = [];
        for ($qnumber = 1; $qnumber <= itemtype::MAXQUESTIONS; $qnumber++) {
            $questionfields[] = itemtype::col_questiontext($qnumber);
        }
        $PAGE->requires->js_call_amd($component . '/formhelper', 'init', [[
            'editorid' => self::PASSAGE_EDITOR_ID,
            'characters' => $characters,
            'wordcountfield' => itemtype::WORDCOUNT,
            'targetfield' => itemtype::TARGETWPM,
            'passmarkfield' => itemtype::PASSMARK,
            'questionfields' => $questionfields,
        ]]);
    }

    /**
     * An automatic word count shows as an empty box, not as 0.
     *
     * @return void
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        if ($mform->elementExists(itemtype::WORDCOUNT)) {
            $element = $mform->getElement(itemtype::WORDCOUNT);
            if ((int) $element->getValue() <= 0) {
                $element->setValue('');
            }
        }
    }

    /**
     * Frankenstyle component name of this subplugin (for get_string calls).
     *
     * @return string
     */
    protected static function fetch_component(): string {
        return 'minilessonitem_speedreading';
    }

    /**
     * Check the passage, the questions and the results settings.
     *
     * @param array $data form data
     * @param array $files form files
     * @return array errors, keyed by form element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $component = self::fetch_component();

        $passage = $data[constants::QUESTIONTEXTAREA . '_editor']['text'] ?? '';
        if (trim(strip_tags($passage)) === '') {
            $errors[constants::QUESTIONTEXTAREA . '_editor'] = get_string('required');
        }

        for ($qnumber = 1; $qnumber <= itemtype::MAXQUESTIONS; $qnumber++) {
            $questiontext = trim($data[itemtype::col_questiontext($qnumber)] ?? '');
            $answersraw = trim($data[itemtype::col_answers($qnumber)] ?? '');
            if ($questiontext === '' && $answersraw === '') {
                continue;
            }
            if ($questiontext === '') {
                $errors[itemtype::col_questiontext($qnumber)] = get_string('required');
                continue;
            }
            $answers = array_filter(array_map('trim', explode(PHP_EOL, $answersraw)), function ($answer) {
                return $answer !== '';
            });
            if (count($answers) < 2) {
                $errors[itemtype::col_answers($qnumber)] = get_string('error:needtwoanswers', $component);
                continue;
            }
            $correctanswer = (int) ($data[itemtype::col_correctanswer($qnumber)] ?? 1);
            if ($correctanswer > count($answers)) {
                $errors[itemtype::col_correctanswer($qnumber)] = get_string('error:correctanswertoohigh', $component);
            }
        }

        $passmark = (int) ($data[itemtype::PASSMARK] ?? 0);
        if ($passmark < 0 || $passmark > 100) {
            $errors['passmarkgroup'] = get_string('error:passmark', $component);
        }
        if ((int) ($data[itemtype::TARGETWPM] ?? 0) < 1) {
            $errors['targetwpmgroup'] = get_string('error:targetwpm', $component);
        }
        if ((int) ($data[itemtype::WORDCOUNT] ?? 0) < 0) {
            $errors[itemtype::WORDCOUNT] = get_string('error:wordcount', $component);
        }
        return $errors;
    }
}
