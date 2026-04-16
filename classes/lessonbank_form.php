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

defined('MOODLE_INTERNAL') || die();

use mod_minilesson_external;
use moodleform;
require_once($CFG->libdir . '/formslib.php');
/**
 * Class lessonbank_form
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonbank_form extends moodleform {

    /**
     * Lessons per page
     */
    const PERPAGE = 10;

    /**
     * Form definition
     */
    protected function definition() {
        $form = $this->_form;

        $languages = ['' => get_string('choose')] + utils::get_lang_options();

        $grouparray = [];
        $grouparray[] = $form->createElement('select', 'language', get_string('language'), $languages);
        $grouparray[] = $form->createElement('text', 'keyword', get_string('keyword', constants::M_COMPONENT), 'size="40"
                placeholder="' . get_string('keyword', constants::M_COMPONENT) . '"');
        $grouparray[] = $form->createElement('submit', 'search', get_string('search'));
        $grouparray[] = $form->createElement(
            'html',
            '<a class="btn text-primary" href="#advancesearch" data-toggle="collapse" data-bs-toggle="collapse"
            role="button" aria-expanded="false" aria-controls="advancesearch">' .
            get_string('showadvanced', constants::M_COMPONENT) . '</a>'
        );

        $form->setType('searchgroup[keyword]', PARAM_RAW);
        $form->addGroup($grouparray, 'searchgroup', get_string('language'), '', true);

        // Add the advanced search collapse div.
        $form->addElement('html', '<div class="collapse w-100" id="advancesearch">');

        $t = mod_minilesson_external::lessonbank('local_lessonbank_fetch_customfield_options');
        if (!empty($t->data)) {
            $jsonoptions = json_decode($t->data);
            // Loop through custom fields and add to form.
            foreach ($jsonoptions as $field) {
                if (in_array($field->shortname, ['languagelevel', 'skills', 'topic'])) {
                    $options = array_column($field->options, 'text', 'value');
                    $fieldname = $field->shortname === 'languagelevel' ? 'level' :
                        ($field->shortname === 'skills' ? 'skill' : $field->shortname);
                    $form->addElement('html', '<div class="d-flex flex-wrap">');
                    $form->addElement('autocomplete', $fieldname, $field->name, $options, ['multiple' => true]);
                    $form->addElement('html', '</div>');
                    $form->setType($fieldname, PARAM_RAW);
                }
            }
        }

        // Add an optional item types multiselect area.
        $itemtypes = constants::ITEMTYPES;
        $itemtypesandlabels = [];
        foreach ($itemtypes as $itemtype) {
            $itemtypesandlabels[$itemtype] = get_string($itemtype, 'mod_minilesson');
        }
        $form->addElement('html', '<div class="d-flex flex-wrap">');
        $form->addElement('autocomplete', 'itemtype', get_string('itemtypes', 'mod_minilesson'), $itemtypesandlabels, ['multiple' => true]);
        $form->addElement('html', '</div>');

        // Close the collapse div.
        $form->addElement('html', '</div>');

        // Add the page and perpage hidden fields.
        $form->addElement('hidden', 'page');
        $form->setType('page', PARAM_INT);
        $form->setDefault('page', 1);

        $form->addElement('hidden', 'perpage');
        $form->setType('perpage', PARAM_INT);
        $form->setDefault('perpage', self::PERPAGE);
    }
}
