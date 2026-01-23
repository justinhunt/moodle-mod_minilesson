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

namespace mod_minilesson\local\formelement;

use mod_minilesson\constants;
use MoodleQuickForm;
use MoodleQuickForm_editor;

/**
 * Class sentenceprompt
 *
 * @package    mod_minilesson
 * @copyright  2025 YOUR NAME <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/form/editor.php');

class sentenceprompt extends MoodleQuickForm_editor
{
    const ELNAME = 'sentenceprompt';

    /** @var string defines the type of editor */
    public $_type = sentenceprompt::ELNAME;

    function toHtml()
    {
        global $OUTPUT;

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }

        if (empty($this->_values['itemid'])) {
            $this->setValue(['itemid' => file_get_unused_draft_itemid()]);
        }

        $str = $this->_getTabs();
        $str .= '<div>';

        $rows = empty($this->_attributes['rows']) ? 15 : $this->_attributes['rows'];
        $cols = empty($this->_attributes['cols']) ? 80 : $this->_attributes['cols'];

        //Apply editor validation if required field
        $context = $this->_values;
        $context['rows'] = $rows;
        $context['cols'] = $cols;
        $context['frozen'] = $this->_flagFrozen;
        $context['format'] = FORMAT_PLAIN;
        foreach ($this->getAttributes() as $name => $value) {
            $context[$name] = $value;
        }

        if (!is_null($this->getAttribute('onblur')) && !is_null($this->getAttribute('onchange'))) {
            $context['changelistener'] = true;
        }

        $str .= $OUTPUT->render_from_template(constants::M_COMPONENT . '/form/element-' . $this->getType() . '_textarea', $context);

        $str .= '</div>';

        return $str;
    }

    public function exportValue(&$submitValues, $assoc = false)
    {
        $valuearray = $this->_findValue($submitValues);
        if (empty($valuearray)) {
            return null;
        }
        return $this->_prepareValue($valuearray['text'], $assoc);
    }

    public function onQuickFormEvent($event, $arg, &$caller)
    {
        switch ($event) {
            case 'updateValue':
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    if ($caller->isSubmitted() && !$caller->is_new_repeat($this->getName())) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                $finalvalue = null;
                if (null !== $value) {
                    if (!is_array($value)) {
                        $finalvalue = ['text' => $value];
                    } else {
                        $finalvalue = $value;
                    }
                }
                if (null !== $finalvalue) {
                    $this->setValue($finalvalue);
                }
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    public function accept(&$renderer, $required = false, $error = null)
    {
        global $OUTPUT;

        $uniqid = random_string();
        $this->_generateId();
        $this->updateAttributes(['id' => $this->getAttribute('id')  . '_' . $uniqid]);
        $advanced = isset($renderer->_advancedElements[$this->getName()]);
        $helpbutton = $this->getHelpButton();
        $label = $this->getLabel();
        $elementcontext = $this->export_for_template($OUTPUT);
        $elementcontext['wrapperid'] = 'fitem_' . $elementcontext['id'];

        $context = [
            'element' => $elementcontext,
            'label' => $label,
            'required' => $required,
            'advanced' => $advanced,
            'helpbutton' => $helpbutton,
            'error' => $error,
            'jsargs' => json_encode([
                'elementid' => $elementcontext['wrapperid']
            ])
        ];
        if (in_array($this->getName(), $renderer->_stopFieldsetElements) && $renderer->_fieldsetsOpen > 0) {
            $renderer->_html .= $renderer->_closeFieldsetTemplate;
            $renderer->_fieldsetsOpen--;
        }

        $renderer->_html .= $OUTPUT->render_from_template(
            constants::M_COMPONENT . '/form/element-' . $this->getType(),
            $context
        );
    }

    public static function register()
    {
        MoodleQuickForm::registerElementType(static::ELNAME, __FILE__, __CLASS__);
    }
}
