<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_poodlltime\rsquestion;

use \mod_poodlltime\constants;

class multichoiceform extends baseform
{

    public $type = constants::TYPE_MULTICHOICE;

    public function custom_definition() {

        $this->add_correctanswer();
        $this->add_textboxresponse(1,'answer1',true);
        $this->add_textboxresponse(2,'answer2',true);
        $this->add_textboxresponse(3,'answer3',true);
        $this->add_textboxresponse(4,'answer4',true);
    }

}