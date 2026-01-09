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

use mod_minilesson\local\exception\textgenerationfailed;

/**
 * Class aigen
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen {
    /** @var array */
    public const DEFAULTTEMPLATES = [
        '6880824450555' => 'audiostory',
        '6874e6af39202' => 'passagereading',
        '6874e6ca1c71a' => 'wordpractice',
        '6874e6a65a0ff' => 'ayoutubelesson',
        '6874e6axxx999' => 'youtubefinalelesson',
        '688244029b789' => 'keywordstogapfillsfluency',
        '6899607f96fb7' => 'reading_aic_passagegen',
        '68996c17d07f5' => 'reading_aic_passageupload',
        '69076f35b4c3b' => 'set_of_slides',
        '69095a6426664' => 'set_of_slides_nopics',
        '68a036971fe4b' => 'keywords_to_ws_sc',
        '68a0422070f05' => 'keywords_to_ws_sc_sg',
        '690b33f83d02f' => 'dialog_multichoice',
        '690b5e695b235' => 'image_slides',
        '690eb6a91c972' => 'choose_best_reply',
        '691ad9a15f203' => 'wordpractice2',
        '6960baa71fa35' => 'fiction_withpics',
        '696105abf2b2a' => 'fiction_nopics',
    ];
    /** @var \stdClass|null */
    private $moduleinstance = null;
    /** @var \stdClass|null */
    private $course = null;
    /** @var \stdClass|null */
    private $cm = null;
    /** @var \stdClass|null */
    private $context = null;
    /** @var \stdClass|null */
    private $conf = null;

    /** @var \core\progress\db_updater */
    private $progressbar = null;

    /**
     * aigen constructor.
     *
     * @param \stdClass|null $moduleinstance The module instance object, if available.
     * @param \stdClass|null $course The course object, if available.
     * @param \stdClass|null $cm The course module object, if available.
     */
    public function __construct($cm, $progressbar = null) {
        global $PAGE, $OUTPUT;

        global $DB;
        $this->cm = $cm;
        $this->moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $this->conf = get_config(constants::M_COMPONENT);
        $this->progressbar = $progressbar;
    }

    /**
     * Makes import data for the minilesson based on the AI generation configuration and template.
     *
     * @param \stdClass $aigenconfig The AI generation configuration object.
     * @param \stdClass $aigentemplate The AI generation template object.
     * @param array $contextdata The context data for prompt generation.
     * @return \stdClass An object containing the generated import items and lesson files.
     * @throws textgenerationfailed If text generation fails for any item.
     */
    public function make_import_data($aigenconfig, $aigentemplate, $contextdata) {
        $contextfileareas = [];
        $importitems = [];
        $importlessonfiles = new \stdClass();
        $currentitemcount = 0;

        // Get all the voices and get just the nice ones (neural/whisper/azure).
        $langvoices = utils::get_tts_voices($this->moduleinstance->ttslanguage, false, $this->moduleinstance->region);
        $nicevoices = utils::get_nice_voices($this->moduleinstance->ttslanguage, $this->moduleinstance->region);

        foreach ($aigenconfig->items as $configitem) {
            $currentitemcount++;
            $importitem = $aigentemplate->items[$configitem->itemnumber];
            $importitemfileareas = (isset($importitem->filesid) && isset($aigentemplate->files->{$importitem->filesid})) ?
                $aigentemplate->files->{$importitem->filesid} :
                false;
            // This holds data not in the import item that we generate or use for generation.
            $dataitem = new \stdClass();

            switch ($configitem->generatemethod) {
                case 'generate':
                case 'extract':
                    // Prepare the prompt with context data.
                    $useprompt = $configitem->prompt;
                    foreach ($configitem->promptfields as $promptfield) {
                        if (isset($contextdata[$promptfield->mapping])) {
                            $useprompt = str_replace(
                                '{' . $promptfield->name . '}',
                                $contextdata[$promptfield->mapping],
                                $useprompt
                            );
                        }
                    }

                    // Prepare the response format (JSON).
                    $generateformat = new \stdClass();
                    foreach ($configitem->generatefields as $generatefield) {
                        if (isset($generatefield->generate) && $generatefield->generate == 1) {
                            $generateformat->{$generatefield->name} = $generatefield->name . '_data';
                        }
                    }
                    $generateformatjson = json_encode($generateformat);

                    // Complete the prompt.
                    $useprompt = $useprompt . PHP_EOL . 'Generate the data in this JSON format: ' . $generateformatjson;

                    if ($this->progressbar) {
                        $this->progressbar->start_progress(
                            get_string('generatingtextdata', constants::M_COMPONENT, $importitem->name)
                        );
                    }

                    // Generate the data and update the importitem.
                    $genresult = $this->generate_data($useprompt);
                    if ($genresult && $genresult->success) {
                        $genpayload = $genresult->payload;
                        // Now map the generated data to the importitem.
                        foreach ($configitem->generatefields as $generatefield) {
                            if (isset($genpayload->{$generatefield->name})) {
                                // Overwrite the field in the import template with the generated data (if it exists).
                                // It might not exist if its a data field we generated for use elsewhere in the process.
                                if (isset($importitem->{$generatefield->name})) {
                                    $importitem->{$generatefield->name} = $genpayload->{$generatefield->name};
                                } else {
                                    // If the field does not exist in the import item, we can add it to the dataitem.
                                    $dataitem->{$generatefield->name} = $genpayload->{$generatefield->name};
                                }
                            }
                        }
                    } else {
                        throw new textgenerationfailed($currentitemcount, $importitem->type, $useprompt);
                    }

                    // Generate the file areas if needed
                    // First collect overall image context which is just used to encourage AI to make consistent images.
                    $overallimagecontext = false;
                    if (
                        isset($configitem->overallimagecontext) && $configitem->overallimagecontext !== "--"
                        && isset($contextdata[$configitem->overallimagecontext])
                        && !empty($contextdata[$configitem->overallimagecontext])
                    ) {
                        $overallimagecontext = $contextdata[$configitem->overallimagecontext];
                    }
                    // If the filearea is in the template, and the mapping data (topic/sentences etc) is set, generate images.
                    foreach ($configitem->generatefileareas as $generatefilearea) {
                        if (
                            $importitemfileareas &&
                            isset($importitemfileareas->{$generatefilearea->name}) &&
                            isset($generatefilearea->mapping) &&
                            (
                                isset($importitem->{$generatefilearea->mapping}) ||
                                isset($contextdata[$generatefilearea->mapping]) ||
                                isset($dataitem->{$generatefilearea->mapping})
                            )
                        ) {
                            // Update the user.
                            if ($this->progressbar) {
                                $this->progressbar->start_progress(
                                    get_string('generatingimagedata', constants::M_COMPONENT, $importitem->name)
                                );
                            }
                            // Image prompt data - usually mapped from other items (created)
                            // but possibly also from context data or dataitem.
                            $imagepromptdata = false;
                            if (isset($importitem->{$generatefilearea->mapping})) {
                                $imagepromptdata = $importitem->{$generatefilearea->mapping};
                            } else if (isset($dataitem->{$generatefilearea->mapping})) {
                                $imagepromptdata = $dataitem->{$generatefilearea->mapping};
                            } else if (
                                isset($contextdata[$generatefilearea->mapping]) &&
                                !empty($contextdata[$generatefilearea->mapping])
                            ) {
                                $imagepromptdata = $contextdata[$generatefilearea->mapping];
                            }

                            $importitemfileareas->{$generatefilearea->name} =
                                $this->generate_images(
                                    $importitemfileareas->{$generatefilearea->name},
                                    $imagepromptdata,
                                    $overallimagecontext
                                );
                            if ($this->progressbar) {
                                $this->progressbar->end_progress();
                            }
                        }
                    }

                    if ($this->progressbar) {
                        $this->progressbar->end_progress();
                    }

                    break;

                case 'reuse':
                    foreach ($configitem->generatefields as $generatefield) {
                        if (
                            isset($importitem->{$generatefield->name}) &&
                            !empty($generatefield->mapping && isset($contextdata[$generatefield->mapping]))
                        ) {
                            $importitem->{$generatefield->name} = $contextdata[$generatefield->mapping];
                        }
                    }

                    foreach ($configitem->generatefileareas as $generatefilearea) {
                        if (
                            $importitemfileareas &&
                            isset($importitemfileareas->{$generatefilearea->name}) &&
                            !empty($generatefilearea->mapping && isset($contextfileareas[$generatefilearea->mapping]))
                        ) {
                            $importitemfileareas->{$generatefilearea->name} = $contextfileareas[$generatefilearea->mapping];
                        }
                    }
            }

            // Update the context data with import item data or dataitem data.
            foreach ($configitem->generatefields as $generatefield) {
                if ($generatefield->generate == 1) {
                    if (isset($importitem->{$generatefield->name})) {
                        $contextdata["item" . $configitem->itemnumber . "_" . $generatefield->name]
                            = $importitem->{$generatefield->name};
                    } else if (isset($dataitem->{$generatefield->name})) {
                        // If the field does not exist in the import item, we can add it to the dataitem.
                        $contextdata["item" . $configitem->itemnumber . "_" . $generatefield->name]
                            = $dataitem->{$generatefield->name};
                    }
                }
            }

            // Update the filearea data.
            foreach ($configitem->generatefileareas as $generatefilearea) {
                if ($importitemfileareas && $generatefilearea->generate == 1) {
                    $contextfileareas["item" . $configitem->itemnumber . "_" . $generatefilearea->name]
                        = $importitemfileareas->{$generatefilearea->name};
                }
            }

            // Voices - these are a special case and we may ultimately do this in a different way.
            // If the itemtemplate has set a voice field, and the template language is different from the module language
            // We need a voice in the module language.
            $itemtype = $importitem->type;
            $itemclass = '\\mod_minilesson\\local\\itemtype\\item_' . $itemtype;

            // Now check the item for voice fields and update them if necessary.
            if (class_exists($itemclass)) {
                $allfields = $itemclass::get_keycolumns();
                foreach ($allfields as $key => $field) {
                    if (
                        isset($importitem->{$field['jsonname']}) &&
                        $field['type'] == 'voice' &&
                        !in_array($importitem->{$field['jsonname']}, $langvoices)
                    ) {
                        // If the voice is not in the module language we randomly select a voice in the right language.
                        $importitem->{$field['jsonname']} = array_rand(array_flip($nicevoices));
                    }
                }
            }

            // Update the import items.
            $importitems[] = $importitem;
            if (isset($importitem->filesid)) {
                $importlessonfiles->{$importitem->filesid} = $importitemfileareas;
            }

            if ($this->progressbar) {
                $this->progressbar->progress($currentitemcount);
            }
        }

        $thereturn = new \stdClass();
        $thereturn->items = $importitems;
        $thereturn->files = $importlessonfiles;
        return $thereturn;
    }

    /**
     * Generates images using the CloudPoodll service.
     *
     * @param array $fileareatemplate An associative array where keys are filenames and values are file contents.
     * @param string|array $imagepromptdata The prompt or array of prompts to generate images for.
     * @param string|false $overallimagecontext Overall context to guide image generation.
     * @return array An associative array where keys are filenames and values are base64 encoded image data.
     */
    public function generate_images($fileareatemplate, $imagepromptdata, $overallimagecontext) {
        $requests = $filenametrack = $imageurls = [];
        $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
        $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);

        if (empty($token)) {
            return [];
        }

        $imagecnt = 0;
        foreach ($fileareatemplate as $filename => $filecontent) {
            if (!is_array($imagepromptdata)) {
                $prompt = $imagepromptdata;
            } else if (array_key_exists($imagecnt, $imagepromptdata)) {
                $prompt = $imagepromptdata[$imagecnt];
            } else {
                // This is a problem, we have no context data for this image.
                continue;
            }

            // Add the style and create context
            // If the style of the prompt is specified in the prompt then use it as is, if not add a little style instruction
            // Does the prompt contain style keywords? - cartoon, illustration, photo, painting, sketch, drawing, realistic.
            $stylekeywords = [
                'flat vector illustration',
                'cartoon',
                'illustration',
                'photorealistic',
                'digital painting',
                'sketch',
                'line drawing',
                'realistic',
                'infographic',
                '3d render',
            ];
            $stylefound = false;
            foreach ($stylekeywords as $stylekeyword) {
                if (stripos(mb_strtolower($prompt), $stylekeyword) !== false) {
                    $stylefound = true;
                    break;
                }
            }
            if (!$stylefound) {
                $prompt = "Give me a simple cute cartoon image, with no text on it, depicting: " . $prompt;
            }

            if ($overallimagecontext && !empty($overallimagecontext) && $overallimagecontext !== "--") {
                $prompt .= PHP_EOL . " in the context of the following topic: " . $overallimagecontext;
            }

            // Do the image generation.
            $payload = $this->prepare_generate_image_payload($prompt, $token);
            if (is_array($payload)) {
                $requests[] = [
                    'url' => $url,
                    'postfields' => format_postdata_for_curlcall($payload),
                ];
                $filenametrack[utils::array_key_last($requests)] = $filename;
            }

            // Increment file counter.
            $imagecnt++;
        }
        if (empty($requests)) {
            return [];
        }

        $curl = new curl();
        $curlopts = [];
        $curlopts['CURLOPT_TIMEOUT'] = 120; // This might be unnecessary or even counter productive.

        // Update the progress bar.
        if ($this->progressbar) {
            $this->progressbar->start_progress("Generate images: {" . count($requests) . "} ");
        }

        $responses = $curl->multirequest($requests, $curlopts);
        $secondattemptrequests = [];
        $secondattemptimagenumbers = [];
        foreach ($responses as $i => $resp) {
            $processedimage = $this->process_generate_image_response($resp);
            if ($processedimage) {
                $imageurls[$filenametrack[$i]] = $processedimage;
            } else {
                $secondattemptrequests[] = $requests[$i];
                $secondattemptimagenumbers[] = $i;
            }
        }

        // Second attempt responses.
        if (count($secondattemptrequests) > 0) {
            $responses = $curl->multirequest($secondattemptrequests);
            foreach ($responses as $i => $resp) {
                $imagenumber = $secondattemptimagenumbers[$i];
                $processedimage = $this->process_generate_image_response($resp);
                if ($processedimage) {
                    $imageurls[$filenametrack[$imagenumber]] = $processedimage;
                }
            }
        }

        // Update the progress bar.
        if ($this->progressbar) {
            $this->progressbar->end_progress();
        }
        return $imageurls;
    }
    /**
     * Resizes image data to smaller dimensions.
     *
     * @param string $imagedata The raw image data.
     * @return string The resized image data.
     */
    public function make_image_smaller($imagedata) {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        if (empty($imagedata)) {
            return $imagedata;
        }

        // Create temporary files for resizing.
        $randomid = uniqid();
        $temporiginal = $CFG->tempdir . '/aigen_orig_' . $randomid;
        file_put_contents($temporiginal, $imagedata);

        // Resize to reasonable dimensions.
        $resizedimagedata = \resize_image($temporiginal, 500, 500, true);

        if (!$resizedimagedata) {
            // If resizing fails, use the original image data.
            $resizedimagedata = $imagedata;
        }

        // Clean up temporary file.
        if (file_exists($temporiginal)) {
            unlink($temporiginal);
        }

        return $resizedimagedata;
    }

    /**
     * Generates structured data using the CloudPoodll service.
     *
     * @param string $prompt The prompt to generate data for.
     * @return string|false Returns an object with success status and payload, or false on failure.
     */
    public function generate_image($prompt) {
        $params = $this->prepare_generate_image_payload(($prompt));
        if ($params) {
            $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
            $resp = utils::curl_fetch($url, $params);
            return $this->process_generate_image_response($resp);
        } else {
            return false;
        }
    }

    /**
     * Prepares the payload for the image generation API.
     *
     * @param string $prompt The prompt to generate an image for.
     * @param string|null $token The authentication token, if available.
     * @return array|false Returns the payload array or false on failure.
     */
    public function prepare_generate_image_payload($prompt, $token = null) {
        global $USER;

        if (!empty($this->conf->apiuser) && !empty($this->conf->apisecret)) {
            if (is_null($token)) {
                $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);
            }
            if (empty($token)) {
                return false;
            }

            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_call_ai';
            $params["moodlewsrestformat"] = 'json';
            $params['appid'] = 'mod_minilesson';
            $params['action'] = 'generate_images';
            $params["subject"] = '1';
            $params["prompt"] = $prompt;
            $params["language"] = $this->moduleinstance->ttslanguage;
            $params["region"] = $this->moduleinstance->region;
            $params['owner'] = hash('md5', $USER->username);

            return $params;
        } else {
            return false;
        }
    }

    /**
     * Processes the response from the image generation API.
     *
     * @param string $resp The response from the API.
     * @return string|null Returns the base64 encoded image data or null on failure.
     */
    public function process_generate_image_response($resp) {
        $respobj = json_decode($resp);
        $ret = new \stdClass();
        if (isset($respobj->returnCode)) {
            $ret->success = $respobj->returnCode == '0' ? true : false;
            $ret->payload = json_decode($respobj->returnMessage);
        } else {
            $ret->success = false;
            $ret->payload = "unknown problem occurred";
        }
        if ($ret && $ret->success) {
            if (isset($ret->payload[0]->url)) {
                $url = $ret->payload[0]->url;
                $rawdata = file_get_contents($url);
                if ($rawdata !== false) {
                    $smallerdata = $this->make_image_smaller($rawdata);
                    $base64data = base64_encode($smallerdata);
                    return $base64data;
                }
            } else if (isset($ret->payload[0]->b64_json)) {
                // If the payload has a base64 encoded image, use that.
                $rawbase64data = $ret->payload[0]->b64_json;
                $rawdata = base64_decode($rawbase64data);
                $smallerdata = $this->make_image_smaller($rawdata);
                $base64data = base64_encode($smallerdata);
                return $base64data;
            }
        }
        return null;
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

            $resp = utils::curl_fetch($url, $params, 'post');
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
     * @return array An associative array of lesson templates,
     *  where the key is the template name and the value is an array containing 'config' and 'template' objects.
     */
    public static function fetch_lesson_templates($filtertags = []) {
        global $DB;

        $fields = 't.*';
        $from = '{minilesson_templates} t';
        $where = '1 = 1';
        $groupby = 't.id';
        $orderby = 't.id';
        $params = [];

        $predefinedtags = template_tag_manager::get_predefined_tags();
        $singleormultitags = template_tag_manager::get_singleormulti_tags();
        $itemtypetags = template_tag_manager::get_itemtype_tags();
        $tags = array_merge($predefinedtags, $singleormultitags, $itemtypetags);
        $filtertags = array_intersect($filtertags, $tags);
        if ($filtertags) {
            [$in, $inparams] = $DB->get_in_or_equal($filtertags, SQL_PARAMS_NAMED);
            $from .= ' JOIN {' . template_tag_manager::DBTABLE . '} tt ON tt.templateid = t.id ';
            $where .= " AND tt.tagname {$in}";
            $params += $inparams;
        }

        $sql = "SELECT {$fields} FROM {$from} WHERE {$where} GROUP BY {$groupby} ORDER BY {$orderby}";
        $templates = $DB->get_records_sql($sql, $params);
        foreach ($templates as $i => $template) {
            $template->config = json_decode($template->config);
            $template->template = json_decode($template->template);
            $templates[$i] = (array) $template;
        }
        return $templates;
    }

    /**
     * Creates default templates for the AI generation.
     *
     * This function reads predefined template files from the specified directory,
     * creates a new template object, and uploads it using the aigen_uploadform class.
     * It handles exceptions if the files cannot be read.
     */
    public static function create_default_templates() {
        global $CFG, $DB;

        foreach (self::DEFAULTTEMPLATES as $uniqueid => $templateshortname) {
            $t = new \stdClass();
            $t->name = get_string("aigentemplatename:" . $templateshortname, constants::M_COMPONENT);
            $t->description = get_string("aigentemplatedescription:" . $templateshortname, constants::M_COMPONENT);
            // Load the configuration and template files for the aigen template.
            // These files should be located in the specified directory.
            // Ensure that the paths are correct and the files exist.
            // The configuration file should contain the lesson configuration in JSON format.
            // The template file should contain the lesson template in MiniLesson export/import JSON format.
            try {
                $t->config = file_get_contents(
                    $CFG->dirroot . "/mod/minilesson/lessontemplates/" . $templateshortname . "_config.json"
                );
                $t->template = file_get_contents(
                    $CFG->dirroot . "/mod/minilesson/lessontemplates/" . $templateshortname . "_template.json"
                );
                aigen_uploadform::upsert_template($t);
            } catch (\Exception $e) {
                // Handle the exception if the file cannot be read.
                debugging('Error reading $template config file: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}
