<?php

/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace minilessonitem_spacegame;

use mod_minilesson\local\itemform\baseform;

use mod_minilesson\constants;

class itemform extends baseform
{
    public function custom_definition()
    {
        global $CFG;
        $mform = $this->_form;
        $mform->setDefault(constants::TEXTINSTRUCTIONS, get_string('spacegame_instructions1', constants::M_COMPONENT));

        //add a heading for this form
        $this->add_itemsettings_heading();
        $this->add_static_text(
            'enterspacegameitems',
            '',
            get_string('enterspacegameitems', constants::M_COMPONENT)
        );
        $this->add_textarearesponse(1, get_string('spacegameitems', constants::M_COMPONENT), true);
        $this->add_allowretry(constants::SG_ALLOWRETRY, '', 1);

        $multichoicealiencount = get_string('aliencount_mc', constants::M_COMPONENT);
        $this->add_aliencount(constants::SG_ALIENCOUNT_MULTICHOICE, $multichoicealiencount, 5);

        $mform->addElement(
            'advcheckbox',
            constants::SG_INCLUDEMATCHING,
            get_string('includematching', constants::M_COMPONENT),
            get_string('includematching_desc', constants::M_COMPONENT),
            [],
            [0, 1]
        );
        $mform->setDefault(constants::SG_INCLUDEMATCHING, 1);

        $matchingaliencount = get_string('aliencount_match', constants::M_COMPONENT);
        $this->add_aliencount(constants::SG_ALIENCOUNT_MATCHING, $matchingaliencount, 3);

        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $mform->hideIf(constants::SG_ALIENCOUNT_MATCHING, constants::SG_INCLUDEMATCHING, 'eq', 0);
        } else {
            $mform->disabledIf(constants::SG_ALIENCOUNT_MATCHING, constants::SG_INCLUDEMATCHING, 'eq', 0);
        }
    }
}
