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
        //add a heading for this form
        $this->add_itemsettings_heading();
        $this->add_textarearesponse(1,'SpacE gamE words on new line',true);
        

       // $this->add_repeating_textboxes('sentence',5);
    }

}