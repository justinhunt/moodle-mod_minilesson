<?php

namespace mod_minilesson\output;

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


defined('MOODLE_INTERNAL') || die();

use \mod_minilesson\constants;

/**
 * A custom renderer class that extends the plugin_renderer_base.
 *
 * @package mod_minilesson
 * @copyright COPYRIGHTNOTICE
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rsquestion_renderer extends \plugin_renderer_base {

 /**
 * Return HTML to display add first page links
 * @param \context $context
  * @param int $tableid
 * @return string
 */
 public function add_edit_page_links($context, $tableid) {
		global $CFG;
        $itemid = 0;
        $config = get_config(constants::M_COMPONENT);

        $output = $this->output->heading(get_string("whatdonow", "minilesson"), 3);
        $links = array();

        $qtypes = [constants::TYPE_PAGE,constants::TYPE_MULTICHOICE, constants::TYPE_DICTATIONCHAT,
                constants::TYPE_DICTATION,constants::TYPE_SPEECHCARDS, constants::TYPE_LISTENREPEAT];
        $qtypes[]= constants::TYPE_MULTIAUDIO;
        $qtypes[]=constants::TYPE_SHORTANSWER;
         $qtypes[]=constants::TYPE_LGAPFILL;
         $qtypes[]=constants::TYPE_TGAPFILL;
         $qtypes[]=constants::TYPE_SGAPFILL;
        if(isset($CFG->minilesson_experimental) &&$CFG->minilesson_experimental){
           $qtypes[]=constants::TYPE_SMARTFRAME;
            $qtypes[]=constants::TYPE_COMPQUIZ;
            $qtypes[]=constants::TYPE_BUTTONQUIZ;
        }
        //If modaleditform is true adding and editing item types is done in a popup modal. Thats good ...
        // but when there is a lot to be edited , a standalone page is better. The modaleditform flag is acted on on additemlink template and rsquestionmanager js
        $modaleditform=$config->modaleditform=="1";
        foreach($qtypes as $qtype){
            $data=['wwwroot' => $CFG->wwwroot, 'type'=>$qtype,'itemid'=>$itemid,'cmid'=>$this->page->cm->id,
                    'label'=>get_string('add' . $qtype . 'item', constants::M_COMPONENT),'modaleditform'=>$modaleditform];
            $links[]= $this->render_from_template('mod_minilesson/additemlink', $data);
        }

         $props=array('contextid'=>$context->id, 'tableid'=>$tableid,'modaleditform'=>$modaleditform,'wwwroot' => $CFG->wwwroot,'cmid'=>$this->page->cm->id,);
         $this->page->requires->js_call_amd(constants::M_COMPONENT . '/rsquestionmanager', 'init', array($props));

        return $this->output->box($output.implode("",$links), 'generalbox firstpageoptions mod_minilesson_link_box_container');

    }

    function setup_datatables($tableid){
        global $USER;

        $tableprops = array();
        $columns = array();
        //for cols .. .'itemname', 'itemtype', 'itemtags','timemodified', 'action'
        $columns[0]=array('orderable'=>false);
        $columns[1]=array('orderable'=>false);
        $columns[2]=array('orderable'=>false);
        $columns[3]=array('orderable'=>false);
        $columns[4]=array('orderable'=>false);
        $columns[5]=array('orderable'=>false);
        $tableprops['columns']=$columns;
        $tableprops['dom'] = 'lBfrtip';


        //default ordering
        $order = array();
        $order[0] =array(1, "asc");
        $tableprops['order']=$order;

        //here we set up any info we need to pass into javascript
        $opts =Array();
        $opts['tableid']=$tableid;
        $opts['tableprops']=$tableprops;
        $this->page->requires->js_call_amd(constants::M_COMPONENT . "/datatables", 'init', array($opts));
        $this->page->requires->css( new \moodle_url('https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css'));
        $this->page->requires->css( new \moodle_url('https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css'));
        $this->page->requires->strings_for_js(['bulkdelete', 'bulkdeletequestion'], constants::M_COMPONENT);
    }


    function show_noitems_message($itemsvisible){
        $message = $this->output->heading(get_string('noitems',constants::M_COMPONENT), 3, 'main');
        $displayvalue = $itemsvisible ? 'none' : 'block';
        $ret = \html_writer::div($message ,constants::M_NOITEMS_CONT,array('id'=>constants::M_NOITEMS_CONT,'style'=>'display: '.$displayvalue));
        return $ret;
    }

	/**
	 * Return the html table of items
	 * @param array homework objects
	 * @param integer $courseid
	 * @return string html of table
	 */
	function show_items_list($items,$minilesson,$cm, $visible){

		//new code
        $data = [];
        $data['tableid']=constants::M_ITEMS_TABLE;
        $data['display'] = $visible ? 'block' : 'none';
        $items_array = [];
        foreach(array_values($items) as $i=>$item){
            $arrayitem = (Array)$item;
            $arrayitem['index']=($i+1);
            $arrayitem['typelabel']=get_string($arrayitem['type'],constants::M_COMPONENT);
            $items_array[]= $arrayitem;
        }
        $data['items']=$items_array;

        $up_pix = new \pix_icon('t/up', get_string('up'));
        $down_pix = new \pix_icon('t/down', get_string('down'));
        $data['up'] = $up_pix->export_for_pix();
        $data['down']=$down_pix->export_for_pix();

        return $this->render_from_template('mod_minilesson/itemlist', $data);

	}
}