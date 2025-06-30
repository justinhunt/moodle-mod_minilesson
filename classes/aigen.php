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

namespace mod_minilesson;

/**
 * Class aigen
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen
{


    private $moduleinstance = null;
    private $course = null;
    private $cm = null;
    private $context = null;
    private $conf = null;
    
    /**
     * aigen constructor.
     *
     * @param \stdClass|null $moduleinstance The module instance object, if available.
     * @param \stdClass|null $course The course object, if available.
     * @param \stdClass|null $cm The course module object, if available.
     */
    public function __construct($cm)
    {
        global $PAGE, $OUTPUT;

         global $DB;
        $this->cm = $cm;
        $this->moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $this->conf = get_config(constants::M_COMPONENT);
    }
 
    public function make_import_data($aigenconfig, $aigentemplate, $topic, $level, $text) {
        $contextdata = ['user_topic' => $topic, 'user_level' => $level,
        'user_text' => $text, 'system_language' => $this->moduleinstance->ttslanguage];
        $importitems = [];
        $importfiles = [];
        foreach ($aigenconfig->items as $configitem) {
            $importitem = $aigentemplate->items[$configitem->itemnumber];
            switch ($configitem->generatemethod) {
                case 'generate':
                case 'extract':
                    // Prepare the prompt with context data
                    $useprompt = $configitem->prompt;
                    foreach ($configitem->promptfields as $promptfield) {
                        if (isset($contextdata[$promptfield->mapping])) {
                            $useprompt = str_replace('{' . $promptfield->name . '}', $contextdata[$promptfield->mapping],$useprompt);
                        }
                    };
                    // Prepare the response format (JSON)
                    $generateformat = new \stdClass();
                    foreach ($configitem->generatefields as $generatefield) {
                        if (isset($importitem->{$generatefield->name})) {
                            $generateformat->{$generatefield->name} = $generatefield->name . '_data';
                        }
                    };
                    $generateformatjson = json_encode($generateformat);

                    // Complete the prompt
                    $useprompt = $useprompt . PHP_EOL . 'Generate the data in this JSON format: ' . $generateformatjson;

                    //Generate the data and update the importitem
                    $genresult = $this->generate_data($useprompt);
                    if ($genresult && $genresult->success) {
                        $genpayload = $genresult->payload;
                        // Now map the generated data to the importitem
                        foreach ($configitem->generatefields as $generatefield) {
                            if (isset($genpayload->{$generatefield->name})) {
                                $importitem->{$generatefield->name} = $genpayload->{$generatefield->name};
                            }
                        }
                    }
                    break;

                case 'reuse':
                    foreach ($configitem->generatefields as $generatefield) {
                        if (isset($importitem->{$generatefield->name}) && !empty($generatefield->mapping && isset($contextdata[$generatefield->mapping]))) {
                            $importitem->{$generatefield->name} = $contextdata[$generatefield->mapping];
                        }
                    };
            }
            // Update the context data
            foreach ($configitem->generatefields as $generatefield) {
                if ($generatefield->generate == 1) {
                    $contextdata["item" . $configitem->itemnumber . "_" . $generatefield->name]
                             = $importitem->{$generatefield->name};
                }
            }
            // Update the import items.
            $importitems[] = $importitem;
        }
        $thereturn = new \stdClass();
        $thereturn->items = $importitems;
        $thereturn->files = $aigentemplate->files;
        return $thereturn;
    }


    /**
     * Generates structured data using the CloudPoodll service.
     *
     * @param string $prompt The prompt to generate data for.
     * @return \stdClass|false Returns an object with success status and payload, or false on failure.
     */
    public function generate_data($prompt) {
        global $USER;
 
        if (!empty($this->conf->apiuser) && !empty($this->conf->apisecret)) {
            $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);


            if (empty($token)) {
                return false;
            }
            $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_call_ai';
            $params["moodlewsrestformat"] = 'json';
            $params['appid'] = 'mod_minilesson';
            $params['action'] = 'generate_structured_content';
            $params["prompt"] = $prompt;
            $params["language"] = $this->moduleinstance->ttslanguage;
            $params["region"] = $this->moduleinstance->region;
             $params['owner'] = hash('md5', $USER->username);
            $params["subject"] = 'none';

            $resp = utils::curl_fetch($url, $params);
            $respobj = json_decode($resp);
            $ret = new \stdClass();
            if (isset($respobj->returnCode)) {
                $ret->success = $respobj->returnCode == '0' ? true : false;
                $ret->payload = json_decode($respobj->returnMessage);
            } else {
                $ret->success = false;
                $ret->payload = "unknown problem occurred";
            }
            return $ret;
        } else {
            return false;
        }
    }

    /**
     * Fetches the lesson templates from the lesson templates directory.
     *
     * @return array An associative array of lesson templates, where the key is the template name and the value is an array containing 'config' and 'template' objects.
     */
    public static function fetch_lesson_templates()
    {
        global $CFG, $PAGE;
        // Init return array
        $ret = [];
        $iterator = null;


        //we search the Generico "presets" and the themes "generico" folders for presets
        $templatesdir = $CFG->dirroot . '/mod/minilesson/lessontemplates';
        if (file_exists($templatesdir)) {
            $iterator = new \DirectoryIterator($templatesdir);
        }

        if ($iterator) {
            foreach ($iterator as $fileinfo) {
                if (!$fileinfo->isDot() && !$fileinfo->isDir()) {
                    $thetemplate = self::parse_lesson_template($fileinfo);
                    if ($thetemplate) {
                        if (!isset($thetemplate['name']) || !$thetemplate['theobject']) {
                            continue;
                        } else {
                            $name = $thetemplate['name'];
                            $theobject = $thetemplate['theobject'];
                        }
                        // If the name has _config in it, remove that to get the display name.
                        if (strpos($name, '_config') !== false) {
                            $keyname = str_replace('_config', '', $name);
                            $templatepart = ['config' => $theobject];
                        } else if (strpos($name, '_template') !== false) { 
                            // If the name has _template in it, remove that to get the display name.
                            $keyname = str_replace('_template', '', $name);
                            $templatepart = ['template' => $theobject];
                        } else {
                            // If the name does not have _config or _template -  its wrong
                            $keyname = false;
                            continue;
                        }
                        if (array_key_exists($keyname, $ret)){
                            $ret[$keyname] = array_merge($ret[$keyname], $templatepart);
                        } else {
                            $ret[$keyname] = $templatepart;
                        }
                    }
                }
            }
        }
        return $ret;
    }//end of fetch presets function

    /**
     * Parses a lesson template file and returns its content.
     *
     * @param \SplFileInfo $fileinfo The file information object for the lesson template file.
     * @return array|false An associative array containing the template name and the decoded JSON object, or false if parsing fails.
     */
    protected static function parse_lesson_template(\SplFileInfo $fileinfo)
    {
        $file = $fileinfo->openFile("r");
        $filename = $fileinfo->getFilename();
        $content = "";
        while (!$file->eof()) {
            $content .= $file->fgets();
        }
        $templateobject = json_decode($content);
        if ($templateobject && is_object($templateobject)) {
            return ['name' => $filename, 'theobject' => $templateobject];
        } else {
            return false;
        }
    }//end of parse lesson template
}
