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

use core\di;
use core_ai\aiactions\base;
use core_ai\aiactions\generate_text;
use core_ai\manager;
use core_ai\provider;

/**
 * Class aimanager
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aimanager {

    /** @var int|null */
    protected $contextid;

    /** @var string|null */
    protected $region;

    /** @var string|null */
    protected $ttslanguage;

    /**
     * aimanager constructor.
     * @param int|null $contextid
     * @param string|null $region
     * @param string|null $ttslanguage
     */
    public function __construct($contextid = null, $region = null, $ttslanguage = null) {
        $this->contextid = $contextid;
        $this->region = $region;
        $this->ttslanguage = $ttslanguage;
    }

    /** @var int */
    public const CLOUDPOODLL_OPTION = -1;

    /** @var string */
    public const OPTION_GRADE_STUDENT_SUBMISSION = 'grade_student_submission';

    /** @var string */
    public const FUNC_EVALUATE_PASSAGE = 'evaluate_passage';

    /** @var string */
    public const FUNC_REQUEST_GRAMMAR_CORRECTION = 'request_grammar_correction';

    /** @var string */
    public const FUNC_AUTOGRADE_SPEECH = 'autograde_speech';

    /** @var string */
    public const FUNC_AUTOGRADE_TEXT = 'autograde_text';

    /** @var string */
    public const FUNC_TEXTANALYSE_PASSAGE = 'textanalyse_passage';

    /** @var string */
    public const FUNC_GET_TOPIC_RELEVANCE = 'get_topic_relevance';

    /** @var string */
    public const FUNC_PREDICT_CEFR = 'predict_cefr';

    /** @var string */
    public const FUNC_COUNT_UNIQUE_IDEAS = 'count_unique_ideas';

    /** @var string */

    public const FUNC_GET_EMBEDDING = 'get_embedding';

    /** @var array */
    public const OPTION_MAPPING = [
        self::FUNC_EVALUATE_PASSAGE => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_REQUEST_GRAMMAR_CORRECTION => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_AUTOGRADE_SPEECH => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_AUTOGRADE_TEXT => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_TEXTANALYSE_PASSAGE => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_GET_TOPIC_RELEVANCE => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_PREDICT_CEFR => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_COUNT_UNIQUE_IDEAS => self::OPTION_GRADE_STUDENT_SUBMISSION,
    ];

    public const AIMANAGER_ACTIONS = [
        self::OPTION_GRADE_STUDENT_SUBMISSION => generate_text::class,
    ];

    /**
     * get AI manager options
     * @return array
     */
    public static function get_action_options() {
        $options[self::OPTION_GRADE_STUDENT_SUBMISSION] =
            [
            'name' => get_string('grade_student_submission' , constants::M_COMPONENT),
            'description' => get_string('grade_student_submission_desc' , constants::M_COMPONENT),
            ];
        return $options;
    }

    public static function get_action_settingname($actiontype) {
        return constants::M_COMPONENT . "/aiaction_{$actiontype}";
    }

    public static function get_action_provider_setting($actiontype) {
        [$component, $settingname] = explode('/', static::get_action_settingname($actiontype), 2);
        $setting = get_config($component, $settingname);
        return !empty($setting) ? $setting : static::CLOUDPOODLL_OPTION;
    }

    /**
     *
     *
     */
    public static function evaluate_passage() {

    }

    /**
     *
     *
     */
    public function request_grammar_correction($passage) {
        $actionconst = static::FUNC_REQUEST_GRAMMAR_CORRECTION;
        $aiactionclass = local\aiactions\request_grammar_correction::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'passage' => $passage,
            'language' => $this->ttslanguage,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $passage;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = 'none';
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        }
        return $response;
    }

    /**
     *
     *
     */
    public function autograde_speech($studentresponse, $instructions) {
        $actionconst = static::FUNC_AUTOGRADE_SPEECH;
        $instructionsjson = json_encode($instructions);
        $aiactionclass = local\aiactions\autograde_text::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'submittedtext' => $studentresponse,
            'instructions' => $instructionsjson,
            'language' => $this->ttslanguage,
            'isspeech' => true,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $instructionsjson;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = $studentresponse;
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        }
        return $response;
    }

    /**
     *
     *
     */
    public function autograde_text($studentresponse, $instructions) {
        $actionconst = static::FUNC_AUTOGRADE_TEXT;
        $instructionsjson = json_encode($instructions);
        $aiactionclass = local\aiactions\autograde_text::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'submittedtext' => $studentresponse,
            'instructions' => $instructionsjson,
            'language' => $this->ttslanguage,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $instructionsjson;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = $studentresponse;
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        }
        return $response;
    }

    /**
     *
     *
     */
    public static function textanalyse_passage() {
    }

    public function get_topic_relevance($referencetext, $submittedtext) {
        $actionconst = static::FUNC_GET_TOPIC_RELEVANCE;
        $aiactionclass = local\aiactions\get_topic_relevance::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'referencetext' => $referencetext,
            'submittedtext' => $submittedtext,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $submittedtext;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = $referencetext;
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        } else if (utils::is_json($response->returnMessage)) {
            $jsondata = json_decode($response->returnMessage);
            $response->returnMessage = 0.1 * $jsondata->relevance;
        } else {
            $response->returnMessage = 0.1;
        }
        return $response;
    }

    public function count_unique_ideas($originaltext) {
        $actionconst = static::FUNC_COUNT_UNIQUE_IDEAS;
        $aiactionclass = local\aiactions\count_unique_ideas::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'originaltext' => $originaltext,
            'language' => $this->ttslanguage,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $originaltext;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = 'none';
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        }
        return $response;
    }

    public function predict_cefr($originaltext) {
        $actionconst = static::FUNC_PREDICT_CEFR;
        $aiactionclass = local\aiactions\predict_cefr::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'originaltext' => $originaltext,
            'language' => $this->ttslanguage,
        ]);
        if ($response === null) {
            $params['action'] = $actionconst;
            $params['prompt'] = $originaltext;
            $params['language'] = $this->ttslanguage;
            $params['subject'] = 'none';
            $params['region'] = $this->region;
            $response = self::call_cp_api($params);
        }
        return $response;
    }

    private static function check_cache($action, $prompt, $provider) {
        global $DB;
        $hashkey = md5($action . '|' . $prompt . '|' . $provider);
        if ($record = $DB->get_record('minilesson_ai_cache', ['hashkey' => $hashkey])) {
            return $record->response;
        }
        return false;
    }

    private static function set_cache($action, $prompt, $provider, $response) {
        global $DB;
        $hashkey = md5($action . '|' . $prompt . '|' . $provider);
        $record = new \stdClass();
        $record->hashkey = $hashkey;
        $record->action = $action;
        $record->prompt = $prompt;
        $record->provider = $provider;
        $record->response = $response;
        $record->timecreated = time();
        $DB->insert_record('minilesson_ai_cache', $record);
    }

    public function get_semantic_sim($passage, $targettopic, $cache = false) {
        $actionconst = static::FUNC_GET_SEMANTIC_SIM; // We will need to define this, wait, the prompt says the action is get_semantic_sim
        // Let's just use string literal 'get_semantic_sim' if no constant is added.
        $actionconst = 'get_semantic_sim';
        $provider = 'cloud poodll';

        if ($cache) {
            $cachedresponse = self::check_cache($actionconst, $passage, $provider);
            if ($cachedresponse !== false) {
                return (int)$cachedresponse;
            }
        }

        $params = [];
        $params['action'] = $actionconst;
        $params['prompt'] = $passage;
        $params['subject'] = $targettopic;
        $params['language'] = $this->ttslanguage;
        $params['region'] = $this->region;

        $response = self::call_cp_api($params);

        if (!$response || !isset($response->returnCode) || $response->returnCode > 0) {
            return false;
        } else if ($response->returnCode === 0) {
            $relevance = $response->returnMessage;
            if (is_numeric($relevance)) {
                $relevance = (int)round($relevance * 100, 0);
                if ($cache) {
                    self::set_cache($actionconst, $passage, $provider, (string)$relevance);
                }
            } else {
                $relevance = false;
            }
            return $relevance;
        }
        return false;
    }

    public function generate_structured_content($prompt, $cache = false) {
        $actionconst = 'generate_structured_content';
        $provider = 'cloud poodll';

        if ($cache) {
            $cachedresponse = self::check_cache($actionconst, $prompt, $provider);
            if ($cachedresponse !== false) {
                return json_decode($cachedresponse);
            }
        }

        $params['action'] = $actionconst;
        $params['prompt'] = $prompt;
        $params['language'] = $this->ttslanguage;
        $params['region'] = $this->region;
        $params['subject'] = 'none';

        $response = self::call_cp_api($params);

        $ret = new \stdClass();
        if ($response && isset($response->returnCode)) {
            $ret->success = $response->returnCode == '0' ? true : false;
            $ret->payload = json_decode($response->returnMessage);
            if ($cache && $ret->success && $ret->payload !== null) {
                self::set_cache($actionconst, $prompt, $provider, json_encode($ret));
            }
        } else {
            $ret->success = false;
            $ret->payload = $response ? $response : "unknown problem occurred";
        }
        return $ret;
    }

    public function generate_image($prompt, $cache = false) {
        $actionconst = 'generate_images';
        $provider = 'cloud poodll';

        if ($cache) {
            $cachedresponse = self::check_cache($actionconst, $prompt, $provider);
            if ($cachedresponse !== false) {
                return $cachedresponse; // Returns base64
            }
        }

        $params['action'] = $actionconst;
        $params['prompt'] = $prompt;
        $params['language'] = $this->ttslanguage;
        $params['region'] = $this->region;
        $params['subject'] = '1';

        $response = self::call_cp_api($params);

        $ret = new \stdClass();
        if ($response && isset($response->returnCode)) {
            $ret->success = $response->returnCode == '0' ? true : false;
            $ret->payload = json_decode($response->returnMessage);
        } else {
            $ret->success = false;
            $ret->payload = "unknown problem occurred";
        }

        if ($ret->success && isset($ret->payload[0]->url)) {
            $url = $ret->payload[0]->url;
            $rawdata = file_get_contents($url);
        } else if ($ret->success && isset($ret->payload[0]->b64_json)) {
            $rawbase64data = $ret->payload[0]->b64_json;
            $rawdata = base64_decode($rawbase64data);
        } else {
            return null;
        }

        if (isset($rawdata) && $rawdata !== false) {
            $smallerdata = self::make_image_smaller($rawdata);
            $base64data = base64_encode($smallerdata);
            if ($cache) {
                self::set_cache($actionconst, $prompt, $provider, $base64data);
            }
            return $base64data;
        }

        return null;
    }

    public function generate_images(
        $fileareatemplate,
        $imagepromptdata,
        $overallimagecontext,
        $cache = false
    ) {
        $imageurls = [];
        $imagecnt = 0;

        foreach ($fileareatemplate as $filename => $filecontent) {
            if (!is_array($imagepromptdata)) {
                $prompt = $imagepromptdata;
            } else if (array_key_exists($imagecnt, $imagepromptdata)) {
                $prompt = $imagepromptdata[$imagecnt];
            } else {
                continue;
            }

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

            $base64image = $this->generate_image($prompt, $cache);

            // Second attempt if failed
            if (!$base64image) {
                $base64image = $this->generate_image($prompt, $cache);
            }

            if ($base64image) {
                $imageurls[$filename] = $base64image;
            }

            $imagecnt++;
        }

        return $imageurls;
    }

    public static function make_image_smaller($imagedata) {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        if (empty($imagedata)) {
            return $imagedata;
        }

        $randomid = uniqid();
        $temporiginal = $CFG->tempdir . '/aigen_orig_' . $randomid;
        file_put_contents($temporiginal, $imagedata);

        $resizedimagedata = \resize_image($temporiginal, 500, 500, true);

        if (!$resizedimagedata) {
            $resizedimagedata = $imagedata;
        }

        if (file_exists($temporiginal)) {
            unlink($temporiginal);
        }

        return $resizedimagedata;
    }

    public function get_embedding($passage, $cache = false) {

        $actionconst = static::FUNC_GET_EMBEDDING;
        $provider = 'cloud poodll';

        if ($cache) {
            $cachedresponse = self::check_cache($actionconst, $passage, $provider);
            if ($cachedresponse !== false) {
                return $cachedresponse;
            }
        }

        $params['action'] = $actionconst;
        $params['prompt'] = $passage;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $this->region;

        $response = self::call_cp_api($params);

        // returnCode > 0  indicates an error.
        if (!$response || !isset($response->returnCode) || $response->returnCode > 0) {
            return false;
            // If all good, then process it.
        } else if ($response->returnCode === 0) {
            $returndata = $response->returnMessage;
            // Clean up the correction a little.
            if (!utils::is_json($returndata)) {
                $embedding = false;
            } else {
                $dataobject = json_decode($returndata);
                if (is_array($dataobject) && isset($dataobject[0]->object) && $dataobject[0]->object == 'embedding') {
                    $embedding = json_encode($dataobject[0]->embedding);
                    if ($cache) {
                        self::set_cache($actionconst, $passage, $provider, $embedding);
                    }
                } else {
                    $embedding = false;
                }
            }
            return $embedding;
        } else {
            return false;
        }
    }

    public static function call_ai_provider_action($actionclass, $params) {
        global $USER;
        if (!static::get_ai_manager() || !is_subclass_of($actionclass, base::class)) {
            return null;
        }
        $mapactionclass = static::get_action_parentclass($actionclass);
        if (!in_array($mapactionclass, static::AIMANAGER_ACTIONS)) {
            return null;
        }
        $actiontype = array_flip(static::AIMANAGER_ACTIONS)[$mapactionclass];
        $setting = static::get_action_provider_setting($actiontype);
        if ($setting == static::CLOUDPOODLL_OPTION) {
            return null;
        }
        $providerdata = static::get_provider_and_check_enabled($setting, $actionclass);
        if (empty($providerdata)) {
            return null;
        }

        $manager = $providerdata['manager'];
        $providerinstance = $providerdata['provider'];
        $params['userid'] = $params['userid'] ?? $USER->id;
        $params['prompttext'] = $params['prompttext'] ?? '';
        $action = new $actionclass(...$params);
        $result = static::call_and_store_action($manager, $providerinstance, $action);
        if ($result === false) {
            return null;
        }
        $response = $result->get_response_data();
        $returnmessage = isset($response['jsondata']) ? $response['jsondata'] : $response['generatedcontent'];
        return (object) [
            'returnCode' => 0,
            'returnMessage' => $returnmessage,
        ];
    }

    /**
     * Call AI provider action using reflection
     * @param object $manager The AI manager instance
     * @param object $providerinstance The provider instance
     * @param object $action The action object
     * @return object|false The result object or false on failure
     */
    public static function call_and_store_action($manager, $providerinstance, $action) {
        $reflclass = new \ReflectionClass($manager);
        $reflmethod = $reflclass->getMethod('call_action_provider');
        $result = $reflmethod->invoke($manager, $providerinstance, $action);

        $reflmethod2 = $reflclass->getMethod('store_action_result');
        $reflmethod2->invoke($manager, $providerinstance, $action, $result);

        if (!$result->get_success()) {
            return false;
        }

        return $result;
    }

    public static function call_cp_api($params = []) {
        global $USER;
        $token = $params['wstoken'] ?? static::get_cp_token();
        if (!$token) {
            return null;
        }
        $params['wstoken'] = $params['wstoken'] ?? $token;
        $params['wsfunction'] = $params['wsfunction'] ?? 'local_cpapi_call_ai';
        $params['moodlewsrestformat'] = $params['moodlewsrestformat'] ?? 'json';
        $params['appid'] = $params['appid'] ?? static::get_component_from_classname(static::class);
        $params['owner'] = $params['owner'] ?? hash('md5', $USER->username);
        $serverurl = utils::get_cloud_poodll_server() . '/webservice/rest/server.php';
        $response = utils::curl_fetch($serverurl, $params);
        if (!utils::is_json($response)) {
            return false;
        }
        return json_decode($response);
    }

    public static function get_cp_token() {
        $conf = get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            return utils::fetch_token($conf->apiuser, $conf->apisecret);
        }
        return false;
    }

    public static function get_provider_options($actionclass) {
        global $CFG;
        $options = [static::CLOUDPOODLL_OPTION => get_string('cloudpoodll', constants::M_COMPONENT)];
        $manager = static::get_ai_manager();
        if (!empty($manager)) {
            $allproviders = $manager->get_providers_for_actions([$actionclass], true);
            if (!empty($allproviders[$actionclass])) {
                foreach ($allproviders[$actionclass] as $aiprovider) {
                    if ($CFG->branch < 500) {
                        $options[$aiprovider->get_name()] = $aiprovider->get_name();
                    } else {
                        $aiproviderrecord = $aiprovider->to_record();
                        $options[$aiproviderrecord->id] = $aiproviderrecord->name;
                    }
                }
            }
        }
        return $options;
    }

    public static function get_ai_manager(): ?manager {
        if (!class_exists(manager::class)) {
            return null;
        }
        return di::get(manager::class);
    }

    public static function get_action_parentclass($actionclass): string {
        return is_callable([$actionclass, 'get_parent_actionclass']) ?
            $actionclass::get_parent_actionclass(true) : $actionclass;
    }

    /**
     * Get the provider instance and check if it's enabled for the given action
     * @param string $providerid The provider ID
     * @param string $actionclass The action class name
     * @return array|null Returns array with 'manager', 'provider', and 'enabled' keys, or null if not found
     */
    public static function get_provider_and_check_enabled($providerid, $actionclass) {
        global $CFG;

        $manager = static::get_ai_manager();
        if (empty($manager)) {
            return null;
        }

        $providerenabled = false;
        $providerinstance = null;
        $mapactionclass = static::get_action_parentclass($actionclass);

        if ($CFG->branch < 500) {
            $providerinstances = manager::get_providers_for_actions([$mapactionclass], true);
            if (isset($providerinstances[$mapactionclass])) {
                foreach ($providerinstances[$mapactionclass] as $provider) {
                    if ($provider->get_name() == $providerid) {
                        $providerinstance = $provider;
                        $component = static::get_component_from_classname(get_class($provider));
                        $CFG->forced_plugin_settings[$component]['action_generate_text_systeminstruction'] = $actionclass::get_system_instruction();
                        $providerenabled = manager::is_action_enabled(
                            $providerid,
                            $mapactionclass
                        );
                        break;
                    }
                }
            }
        } else {
            $providerrecords = $manager->get_provider_records(['id' => $providerid]);
            $providerinstances = array_filter(
                // Apply a callback function to each provider record to instantiate the provider.
                array_map(
                    function ($record) use ($actionclass, $mapactionclass): ?provider {
                        // Check if the provider class specified in the record exists.
                        if (class_exists($record->provider)) {
                            $actionconfig = !empty($record->actionconfig) ? json_decode($record->actionconfig, true) : '';
                            if (!empty($actionconfig) && isset($actionconfig[$mapactionclass])) {
                                // Copy parent class settings to action class.
                                $actionconfig[$actionclass] = $actionconfig[$mapactionclass];
                                // For Moodle version 5 or later.
                                $actionconfig[$actionclass]['settings']['systeminstruction'] = $actionclass::get_system_instruction();
                                // Set Modal Config.
                                $plugintypename = str_replace('aiprovider_', '', strstr($record->provider, '\\', true));
                                $actionconfig[$actionclass]['settings']['modelextraparams'] = json_encode(
                                    $actionclass::get_model_parameters($plugintypename)
                                );
                                $record->actionconfig = json_encode($actionconfig);
                            }
                            // Instantiate the provider class with the record's data.
                            return new $record->provider(
                                /* enabled:  */$record->enabled,
                                /* name:  */$record->name,
                                /* config:  */$record->config,
                                /* actionconfig:  */$record->actionconfig,
                                /* id:  */$record->id
                            );
                        }
                        return null;
                    },
                    $providerrecords
                )
            );
            /** @var \core_ai\provider $providerinstance */
            $providerinstance = reset($providerinstances);
            $providerenabled = !empty($providerinstance) &&
                $manager->is_action_enabled(
                    $providerinstance->provider,
                    $mapactionclass,
                    $providerinstance->id
                );
        }

        if (!$providerenabled || empty($providerinstance)) {
            return null;
        }

        return [
            'manager' => $manager,
            'provider' => $providerinstance,
        ];
    }

    public static function get_component_from_classname($classname): string {
        return strstr($classname, '\\', true);
    }
}
