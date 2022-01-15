<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/03/13
 * Time: 19:31
 */

namespace mod_minilesson\local\rsquestion;

use \mod_minilesson\constants;

class qcardform extends baseform
{

    public $type = constants::TYPE_QCARD;

    public function custom_definition() {
      //    $this->add_qtype();
        $this->add_qcarditems();
        $this->add_repeating_qcardbuttons('qcardbutton',2);

    }

}