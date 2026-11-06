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

namespace minilessonitem_smartframe;

use minilessonitem_smartframe\local\package;
use minilessonitem_smartframe\local\package_exception;
use minilessonitem_smartframe\local\publisher;
use mod_minilesson\local\itemform\baseform;
use mod_minilesson\constants;

/**
 * Authoring form for a widget item.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemform extends baseform {
    /** @var string Component for this item type's strings. */
    const COMPONENT = 'minilessonitem_smartframe';

    /**
     * Add the widget fields.
     */
    public function custom_definition() {
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('widget_instructions1', self::COMPONENT));
        $this->add_itemsettings_heading();

        if (!publisher::instance()->is_configured()) {
            $mform->addElement(
                'static',
                'widgethostwarning',
                '',
                \html_writer::div(get_string('error:publishernotconfigured', self::COMPONENT), 'alert alert-warning')
            );
        }

        $mform->addElement('filemanager', itemtype::FILE, get_string('widgetpackage', self::COMPONENT), null, [
            'accepted_types' => ['.zip'],
            'maxbytes' => package::MAX_ZIP_BYTES,
            'maxfiles' => 1,
            'subdirs' => 0,
        ]);
        $mform->addHelpButton(itemtype::FILE, 'widgetpackage', self::COMPONENT);
        $mform->addRule(itemtype::FILE, null, 'required');

        // Filled in by definition_after_data() once the item's manifest is known.
        $mform->addElement('static', 'widgetsummary', get_string('widgetsummary', self::COMPONENT), '');

        $mform->addElement(
            'select',
            itemtype::UILANGUAGE,
            get_string('uilanguage', self::COMPONENT),
            [itemtype::UILANGUAGE_AUTO => get_string('uilanguage_auto', self::COMPONENT)]
        );
        $mform->setDefault(itemtype::UILANGUAGE, itemtype::UILANGUAGE_AUTO);
        $mform->addHelpButton(itemtype::UILANGUAGE, 'uilanguage', self::COMPONENT);

        $mform->addElement('select', itemtype::GRADEPOLICY, get_string('gradepolicy', self::COMPONENT), [
            itemtype::GRADE_IGNORE => get_string('gradepolicy_ignore', self::COMPONENT),
            itemtype::GRADE_RECORD => get_string('gradepolicy_record', self::COMPONENT),
            itemtype::GRADE_USE => get_string('gradepolicy_use', self::COMPONENT),
        ]);
        $mform->setDefault(itemtype::GRADEPOLICY, itemtype::GRADE_IGNORE);
        $mform->addHelpButton(itemtype::GRADEPOLICY, 'gradepolicy', self::COMPONENT);

        $this->add_numericboxresponse(constants::TOTALMARKS, get_string('totalmarks', constants::M_COMPONENT));
        $mform->setDefault(constants::TOTALMARKS, 1);
        $mform->hideIf(constants::TOTALMARKS, itemtype::GRADEPOLICY, 'neq', itemtype::GRADE_USE);
    }

    /**
     * Show the saved widget's details, and offer its languages.
     */
    public function definition_after_data() {
        global $DB;
        parent::definition_after_data();

        $mform = $this->_form;
        $itemid = $mform->elementExists('itemid') ? (int) $mform->getElement('itemid')->getValue() : 0;
        $record = $itemid ? $DB->get_record(
            constants::M_QTABLE,
            ['id' => $itemid, 'minilesson' => $this->moduleinstance->id]
        ) : false;
        $manifest = $record && $record->{itemtype::MANIFEST} ? json_decode($record->{itemtype::MANIFEST}) : null;

        if (!$manifest) {
            $mform->removeElement('widgetsummary');
            return;
        }

        $select = $mform->getElement(itemtype::UILANGUAGE);
        $names = get_string_manager()->get_list_of_languages();
        foreach ($manifest->uiLanguages as $lang) {
            $base = strtolower(explode('-', $lang)[0]);
            $label = isset($names[$base]) ? $names[$base] . ' (' . $lang . ')' : $lang;
            $select->addOption($label, $lang);
        }

        $a = (object) [
            'title' => s($manifest->title),
            'languages' => s(implode(', ', $manifest->uiLanguages)),
            'grade' => get_string(!empty($manifest->capabilities->grade) ? 'yes' : 'no'),
        ];
        $summary = get_string('widgetsummary_text', self::COMPONENT, $a);
        if (!publisher::is_revision($record->{itemtype::REVISION})) {
            $summary .= \html_writer::div(get_string('error:notpublished', self::COMPONENT), 'alert alert-warning');
        }
        $mform->getElement('widgetsummary')->setValue($summary);
    }

    /**
     * Check the uploaded ZIP is a widget package that can be used, before anything is saved.
     *
     * @param array $data submitted data
     * @param array $files submitted files
     * @return array errors
     */
    public function validation($data, $files) {
        global $USER;
        $errors = parent::validation($data, $files);

        if (!publisher::instance()->is_configured()) {
            $errors[itemtype::FILE] = get_string('error:publishernotconfigured', self::COMPONENT);
            return $errors;
        }

        $draftid = (int) ($data[itemtype::FILE] ?? 0);
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $drafts = $draftid ? $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id DESC', false) : [];
        if (!$drafts) {
            $errors[itemtype::FILE] = get_string('required');
            return $errors;
        }
        // The check must be of the file that will be saved, so there must be just one.
        if (count($drafts) > 1) {
            $errors[itemtype::FILE] = get_string('error:onezip', self::COMPONENT);
            return $errors;
        }
        $file = reset($drafts);
        try {
            package::from_stored_file($file);
        } catch (package_exception $e) {
            $errors[itemtype::FILE] = $e->getMessage();
        }
        return $errors;
    }
}
