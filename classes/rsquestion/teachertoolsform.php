<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_poodlltime\rsquestion;

use \mod_poodlltime\constants;

class teachertoolsform extends baseform
{

    public $type = constants::TYPE_TEACHERTOOLS;

    public function custom_definition() {

        $this->add_textboxresponse(1,'ttshorturl',true);

    }

}