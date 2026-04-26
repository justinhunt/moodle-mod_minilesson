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

/**
 * Lesson bank search form: prepares data and renders via mustache template.
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lessonbank_form {

    /** @var string The selected language. */
    private $selectedlanguage;

    /**
     * Constructor.
     *
     * @param string $selectedlanguage The language to pre-select.
     */
    public function __construct(string $selectedlanguage = '') {
        $this->selectedlanguage = $selectedlanguage;
    }

    /**
     * Build the template context data.
     *
     * @return array
     */
    public function export_for_template(): array {
        $data = [
            'languages' => $this->get_languages(),
            'haslevels' => false,
            'hasskills' => false,
            'hastopics' => false,
            'levels' => [],
            'skills' => [],
            'topics' => [],
            'levelname' => '',
            'skillname' => '',
            'topicname' => '',
            'itemtypes' => $this->get_itemtypes(),
        ];

        $this->load_customfield_options($data);

        return $data;
    }

    /**
     * Render the form HTML.
     *
     * @return string
     */
    public function render(): string {
        global $OUTPUT;
        return $OUTPUT->render_from_template('mod_minilesson/lessonbank_searchform', $this->export_for_template());
    }

    /**
     * Build language options array.
     *
     * @return array
     */
    private function get_languages(): array {
        $languages = [];
        foreach (utils::get_lang_options() as $value => $label) {
            $languages[] = [
                'value' => $value,
                'label' => $label,
                'selected' => ($value === $this->selectedlanguage),
            ];
        }
        return $languages;
    }

    /**
     * Build item types array.
     *
     * @return array
     */
    private function get_itemtypes(): array {
        $itemtypes = [];
        foreach (constants::ITEMTYPES as $itemtype) {
            $itemtypes[] = [
                'value' => $itemtype,
                'label' => get_string($itemtype, constants::M_COMPONENT),
            ];
        }
        return $itemtypes;
    }

    /**
     * Load custom field options (level, skills, topic) from the remote API.
     *
     * @param array $data Template data array, modified by reference.
     */
    private function load_customfield_options(array &$data): void {
        $t = mod_minilesson_external::lessonbank('local_lessonbank_fetch_customfield_options');
        if (empty($t->data)) {
            return;
        }

        $jsonoptions = json_decode($t->data);
        $fieldmap = [
            'languagelevel' => ['key' => 'levels', 'has' => 'haslevels', 'name' => 'levelname'],
            'skills' => ['key' => 'skills', 'has' => 'hasskills', 'name' => 'skillname'],
            'topic' => ['key' => 'topics', 'has' => 'hastopics', 'name' => 'topicname'],
        ];

        foreach ($jsonoptions as $field) {
            if (!isset($fieldmap[$field->shortname])) {
                continue;
            }
            $map = $fieldmap[$field->shortname];
            $options = [];
            foreach ($field->options as $opt) {
                $options[] = ['value' => $opt->value, 'label' => $opt->text];
            }
            $data[$map['key']] = $options;
            $data[$map['has']] = !empty($options);
            $data[$map['name']] = $field->name;
        }
    }
}
