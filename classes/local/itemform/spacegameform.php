<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class spacegameform extends baseform
{

    public $type = constants::TYPE_SPACEGAME;

    public function custom_definition() {
        global $CFG;

        //add a heading for this form
        $this->add_itemsettings_heading();
        $this->add_static_text('enterspacegameitems', '', 
        get_string('enterspacegameitems', constants::M_COMPONENT));
        $this->add_textarearesponse(1, get_string('spacegameitems',  constants::M_COMPONENT), true);

        $multichoicealiencount = get_string('aliencount_mc', constants::M_COMPONENT);
        $this->add_aliencount(constants::SG_ALIENCOUNT_MULTICHOICE, $multichoicealiencount, 5);

        $this->_form->addElement('advcheckbox', constants::SG_INCLUDEMATCHING,
            get_string('includematching', constants::M_COMPONENT),
            get_string('includematching_desc', constants::M_COMPONENT), [], [0, 1]);
        $this->_form->setDefault(constants::SG_INCLUDEMATCHING, 1);

        $matchingaliencount = get_string('aliencount_match', constants::M_COMPONENT);
        $this->add_aliencount(constants::SG_ALIENCOUNT_MATCHING, $matchingaliencount, 3);
 
        $m35 = $CFG->version >= 2018051700;
        if ($m35) {
            $this->_form->hideIf(constants::SG_ALIENCOUNT_MATCHING, constants::SG_INCLUDEMATCHING, 'eq', 0);
        } else {
            $this->_form->disabledIf(constants::SG_ALIENCOUNT_MATCHING, constants::SG_INCLUDEMATCHING, 'eq', 0);
        }
    }

}