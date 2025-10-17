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

namespace mod_minilesson;

use moodleform;
require_once($CFG->libdir . '/formslib.php');
/**
 * Class lessonbank_form
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonbank_form extends moodleform
{
    protected function definition()
    {

        $form = $this->_form;

        $languages = ['' => get_string('choose')] + utils::get_lang_options();

        $grouparray = [];
        $grouparray[] = $form->createElement('select', 'language', get_string('language'), $languages);
        $grouparray[] = $form->createElement('text', 'keyword', get_string('keyword', constants::M_COMPONENT), 'size="40" 
                placeholder="' . get_string('keyword', constants::M_COMPONENT) . '"');
        $grouparray[] = $form->createElement('submit', 'search', get_string('search'));
        $grouparray[] = $form->createElement('html', '<a class="btn text-primary" href="#advancesearch" data-toggle="collapse" data-bs-toggle="collapse" 
            role="button" aria-expanded="false" aria-controls="advancesearch">' .
            get_string('showadvanced', constants::M_COMPONENT) . '</a>');

        $form->setType('searchgroup[keyword]', PARAM_RAW);
        $form->addGroup($grouparray, 'searchgroup', get_string('language'), '', true);

        $levels = [
            "CEFR A1" => 'CEFR A1',
            "CEFR A2" => 'CEFR A2',
            "CEFR B1" => "CEFR B1",
            "CEFR B2" => "CEFR B2",
            "CEFR C1" => "CEFR C1",
            "CEFR C2" => "CEFR C2"
        ];
        $form->addElement('html', '<div class="collapse w-100" id="advancesearch">');
        $form->addElement('autocomplete', 'level', get_string('level', constants::M_COMPONENT), $levels, 'multiple');
        $form->setType('level', PARAM_INT);
        $form->addElement('html', '</div>');
    }

}
