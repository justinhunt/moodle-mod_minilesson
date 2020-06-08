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

/**
 * Flower handler for poodlltime plugin
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_poodlltime;
defined('MOODLE_INTERNAL') || die();

use \mod_poodlltime\constants;


/**
 * Functions used generally across this mod
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flower{


    //fetch a flower item for the completed attempt
    public static function fetch_newflower(){
        global $CFG, $USER,$DB;
        $flowers = self::fetch_flowers();
        $used_flowerids = $DB->get_fieldset_select(constants::M_USERTABLE, 'flowerid', 'userid =:userid', array('userid'=>$USER->id));
        //if we have used flowers and we have not used all our flowers, we reduce the flowers array to the ones we have not allocated yet.
        $candidates = array_filter($flowers, function($flower) use ($used_flowerids) {
            return !in_array($flower['id'], $used_flowerids);
        });
        if (empty($candidates)) {
            $candidates = $flowers;
        }

        $flowerid = array_rand($candidates);
        $flower= $flowers[$flowerid];
        return $flower;
    }
    public static function fetch_flowers(){
        $flowers = array(
            0=>array('id'=>0,'name'=>'ninja','displayname'=>'Ninja'),
            1=>array('id'=>1,'name'=>'seedles', 'displayname'=>'Seedles'),
            2=>array('id'=>2,'name'=>'pipi','displayname'=>'Pippi Longseed'),
            3=>array('id'=>3,'name'=>'bleep','displayname'=>'Bleep'),
            4=>array('id'=>4,'name'=>'speedseed','displayname'=>'Speed Seed'),
            5=>array('id'=>5,'name'=>'mermaid', 'displayname'=>'Mermaid'),
            6=>array('id'=>6,'name'=>'alien', 'displayname'=>'3-Eyes'),
            7=>array('id'=>7,'name'=>'missseedy', 'displayname'=>'Miss Seedy'),
            8=>array('id'=>8,'name'=>'shark', 'displayname'=>'Shark'),
            9=>array('id'=>9,'name'=>'batseed', 'displayname'=>'Bat Seed'),
            10=>array('id'=>10,'name'=>'tripleplay', 'displayname'=>'Triple Play'),
            11=>array('id'=>11,'name'=>'slugger', 'displayname'=>'Slugger'),
            12=>array('id'=>12,'name'=>'billytheseed', 'displayname'=>'Billy the Seed'),
            13=>array('id'=>13,'name'=>'sirseed', 'displayname'=>'Sir Seed'),
            14=>array('id'=>14,'name'=>'nedkelly', 'displayname'=>'Ned'),
            15=>array('id'=>15,'name'=>'discogirl', 'displayname'=>'Disco Girl'),
            16=>array('id'=>16,'name'=>'discoboy', 'displayname'=>'Disco Boy'),
            17=>array('id'=>17,'name'=>'snowseed', 'displayname'=>'Snowseed'),
            18=>array('id'=>18,'name'=>'seah', 'displayname'=>'Princess Seah'),
            19=>array('id'=>19,'name'=>'redridingseed', 'displayname'=>'Red Riding Seed'),
            20=>array('id'=>20,'name'=>'monseed', 'displayname'=>'Mon Seed'),
            21=>array('id'=>21,'name'=>'wonderseed', 'displayname'=>'Wonder Seed'),
            22=>array('id'=>22,'name'=>'guyseedy', 'displayname'=>'Guy Seedy'),
            23=>array('id'=>23,'name'=>'agentseed', 'displayname'=>'Agent Seed'),
            24=>array('id'=>24,'name'=>'ellie', 'displayname'=>'Ellie'),
            25=>array('id'=>25,'name'=>'bumble', 'displayname'=>'Bumble'),
            26=>array('id'=>26,'name'=>'juice', 'displayname'=>'Juice'),
            27=>array('id'=>27,'name'=>'fishy', 'displayname'=>'Fishy'),
            28=>array('id'=>28,'name'=>'robin', 'displayname'=>'Robin'),
            29=>array('id'=>29,'name'=>'kimono', 'displayname'=>'Kimono'),
            30=>array('id'=>30,'name'=>'astro', 'displayname'=>'Astro'),
            31=>array('id'=>31,'name'=>'trex', 'displayname'=>'Rex'),
            32=>array('id'=>32,'name'=>'wheelie', 'displayname'=>'Wheelie'),
            33=>array('id'=>33,'name'=>'woo', 'displayname'=>'Woo')
        );

        return array_map(function($flower) {
            $flower['picurl'] = static::get_flower_url($flower,'free');
            return $flower;
        }, $flowers);
    }

    public static function get_flower($flowerid) {
        $flowers = static::fetch_flowers();
        return $flowers[$flowerid];
    }

    public static function get_flower_url($flower,$pictype='polaroid') {
        global $CFG;
        switch($pictype) {
            case 'polaroid':
                return $CFG->wwwroot . '/mod/poodlltime/flowers/' . $flower['id'] . '/p_' . $flower['name'] . '.svg';
            case 'bigpolaroid':
                return $CFG->wwwroot . '/mod/poodlltime/flowers/' . $flower['id'] . '/p_' . $flower['name'] . '_300px.svg';
                //return $CFG->wwwroot . '/mod/poodlltime/flowers/' . $flower['id'] . '/p_' . $flower['name'] . '_300px.png';
            default:
                return $CFG->wwwroot . '/mod/poodlltime/flowers/' . $flower['id'] . '/f_' . $flower['name'] . '.png';
        }
    }
}
