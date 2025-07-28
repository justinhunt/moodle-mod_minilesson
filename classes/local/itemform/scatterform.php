<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\itemform;

use \mod_minilesson\constants;

class scatterform extends baseform
{

    public $type = constants::TYPE_SCATTER;

    public function custom_definition() {
        global $CFG;

        //add a heading for this form
        $this->add_itemsettings_heading();
        $this->add_static_text('enterscatteritems', '', 
        get_string('enterscatteritems', constants::M_COMPONENT));
        $this->add_textarearesponse(1, get_string('scatteritems',  constants::M_COMPONENT), true);
        $this->add_allowretry(constants::SCATTER_ALLOWRETRY, '', 1);
    }

}