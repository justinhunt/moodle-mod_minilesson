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
use core_ai\aiactions\generate_text as core_generate_text;
use mod_minilesson\local\aiactions\generate_text;
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
    /** @var int Times to ask for structured content before giving up: one go, then one more. */
    const GENERATION_ATTEMPTS = 2;

    /** @var int Seconds to wait before asking again. */
    const GENERATION_RETRY_DELAY = 1;


    /** @var int|null */
    protected $contextid;

    /** @var string|null */
    protected $region;

    /** @var string|null */
    protected $ttslanguage;

    /** @var string|null */
    protected $errormessage;

    /** @var string|null */
    protected $lastprovider;

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
        $this->errormessage = '';
        $this->lastprovider = '';
    }

    public function get_last_provider() {
        return $this->lastprovider;
    }

    public function get_error_message() {
        return $this->errormessage;
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
    public const FUNC_GET_SEMANTIC_SIM = 'get_semantic_sim';

    /** @var string */
    public const FUNC_COUNT_UNIQUE_IDEAS = 'count_unique_ideas';

    /** @var string */

    public const FUNC_GET_EMBEDDING = 'get_embedding';

    /** @var string */

    public const FUNC_GENERATE_CUSTOMFIELD = 'generate_customfield';

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
        self::FUNC_GENERATE_CUSTOMFIELD => self::OPTION_GRADE_STUDENT_SUBMISSION,
    ];

    public const AIMANAGER_ACTIONS = [
        self::OPTION_GRADE_STUDENT_SUBMISSION => core_generate_text::class,
    ];

    /**
     * get AI manager options
     * @return array
     */
    public static function get_action_options() {
        $options[self::OPTION_GRADE_STUDENT_SUBMISSION] =
            [
                'name' => get_string('grade_student_submission', constants::M_COMPONENT),
                'description' => get_string('grade_student_submission_desc', constants::M_COMPONENT),
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

    public function generate_customfield(array $fields, array $importjson) {
        global $USER;
        $aiactionclass = local\aiactions\generate_customfield_value::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $this->contextid,
            'fields' => $fields,
            'importjson' => $importjson,
        ]);
        if ($response === null) {
            // Cloud Poodll only accepts action/subject/prompt/language/region (plus
            // appid/owner/wstoken added in call_cp_api). Build the full prompt the same
            // way the AI provider action does and send it as a single 'prompt' string.
            $contextid = $this->contextid ?? \context_system::instance()->id;
            $action = new $aiactionclass($contextid, $USER->id, '', $fields, $importjson);
            $params = [];
            $params['action'] = 'generate_structured_content';
            $params['prompt'] = $action->generate_prompt();
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

        // This call asks for JSON, and the answer sometimes comes back as prose or as JSON that
        // does not parse - the model writing something other than what was asked for. That is a
        // different kind of failure from a bad token or an exhausted quota: asking again usually
        // works, which is what a person does by hand when a generation run dies. So it is asked
        // again once, and only for that kind of failure.
        for ($attempt = 1; $attempt <= self::GENERATION_ATTEMPTS; $attempt++) {
            $ret = $this->attempt_structured_content($params);
            if ($ret->success || empty($ret->retryable) || $attempt === self::GENERATION_ATTEMPTS) {
                break;
            }
            self::log_cp_api('unusable reply, asking again', [
                'action' => $actionconst,
                'attempt' => $attempt . ' of ' . self::GENERATION_ATTEMPTS,
            ]);
            sleep(self::GENERATION_RETRY_DELAY);
        }

        unset($ret->retryable);
        if ($cache && $ret->success && $ret->payload !== null) {
            self::set_cache($actionconst, $prompt, $provider, json_encode($ret));
        }
        return $ret;
    }

    /**
     * One attempt at generating structured content.
     *
     * @param array $params the call parameters
     * @return \stdClass success, payload, and whether the failure is worth repeating
     */
    protected function attempt_structured_content(array $params): \stdClass {
        $response = self::call_cp_api($params);

        $ret = new \stdClass();
        $ret->retryable = false;

        if ($response && isset($response->returnCode)) {
            $ret->success = $response->returnCode == '0';
            $ret->payload = json_decode($response->returnMessage);

            // A reply can be accepted by the cloud and still be unusable here: returnCode 0 with a
            // returnMessage that is not the JSON this call asked for. Left as a success it sets
            // nothing and the item is created with its generated fields empty - a blank slide
            // rather than an error, which is the harder kind of failure to notice.
            if ($ret->success && $ret->payload === null && trim((string) $response->returnMessage) !== '') {
                self::log_cp_api('reply was not the JSON this call asked for', [
                    'action' => $params['action'] ?? '(none)',
                    'returnMessage' => substr((string) $response->returnMessage, 0, 1000),
                ]);
                $ret->success = false;
                $ret->payload = 'The AI service returned content that was not in the expected format.';
                $ret->retryable = true;
            }
        } else {
            $ret->success = false;
            $ret->payload = $response ? $response : 'unknown problem occurred';
            // False means the reply could not be parsed at all, which is the same flavour of
            // problem. Null means there was no token, and asking again will not conjure one.
            $ret->retryable = ($response === false);
        }

        return $ret;
    }

    /**
     * Translate a short piece of text from one language to another.
     *
     * Results are cached in minilesson_ai_cache, so repeated translations of the same
     * text (e.g. by different students on the same lesson item) only hit the AI provider once.
     *
     * @param string $text The text to translate.
     * @param string $fromlang The language code of the text, e.g. en-US.
     * @param string $tolang The language code to translate into, e.g. ja-JP.
     * @return string|false The translated text, or false on failure.
     */
    public function translate_text($text, $fromlang, $tolang) {
        // The text goes in as JSON: with a plain text payload the model tends to reply with
        // bare text instead of the requested JSON, which the Poodll API rejects.
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
        $prompt = "Translate the value of the text key in the JSON string that follows, ";
        $prompt .= "from language: $fromlang, into language: $tolang." . PHP_EOL;
        $prompt .= 'Return results in the format: {"translation": "thetranslatedtext"}' . PHP_EOL;
        $prompt .= 'Return only the translation, with no explanations or alternatives.' . PHP_EOL;
        $prompt .= $payload;

        $ret = $this->generate_structured_content($prompt, true);
        if ($ret->success && is_object($ret->payload)
                && isset($ret->payload->translation) && is_string($ret->payload->translation)) {
            return $ret->payload->translation;
        }
        return false;
    }

    /**
     * Generate text using the configured AI provider.
     * @param string $prompt
     * @param int $contextid
     * @return string|null
     */
    public function generate_text($prompt, $contextid = null) {
        $contextid = $contextid ?? $this->contextid;
        $aiactionclass = generate_text::class;
        $response = self::call_ai_provider_action($aiactionclass, [
            'contextid' => $contextid,
            'prompttext' => $prompt,
        ]);
        if ($response !== null) {
            if ($response->returnCode == '0') {
                $this->lastprovider = 'Moodle AI';
                return $response->returnMessage;
            } else {
                $this->errormessage = $response->returnMessage;
                // If Moodle AI failed, we still try CloudPoodll fallback.
            }
        }

        // Fallback to Cloud Poodll if Moodle AI not configured
        $this->lastprovider = 'CloudPoodll';
        $params = [];
        $params['action'] = 'generate_structured_content';
        $generateformat = new \stdClass();
        $generateformat->response = 'string';
        $generateformatjson = json_encode($generateformat);
        $params['prompt'] = $prompt . PHP_EOL . 'Generate the data in this JSON format: ' . $generateformatjson;
        $params['language'] = $this->ttslanguage;
        $params['region'] = $this->region;
        $params['subject'] = 'none';

        $cpresponse = self::call_cp_api($params);
        if ($cpresponse && isset($cpresponse->returnCode) && $cpresponse->returnCode == '0') {
            $data = json_decode($cpresponse->returnMessage);
            if ($data && isset($data->response)) {
                return $data->response;
            }
            // If it's not JSON, maybe it's just the string.
            return $cpresponse->returnMessage;
        } else if ($cpresponse && isset($cpresponse->returnMessage)) {
            $this->errormessage = $cpresponse->returnMessage;
        } else {
            $this->errormessage = 'No response from CloudPoodll or Moodle AI provider.';
        }

        return false;
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

    /**
     * Generate one image per file area entry, firing all the CloudPoodll requests in parallel.
     *
     * @param array $fileareatemplate Map of filename => placeholder content; one image per entry.
     * @param string|array $imagepromptdata A single prompt, or an array of prompts indexed per image.
     * @param string|false $overallimagecontext Optional overall topic context added to each prompt.
     * @param bool $cache Whether to read/write generated images from the AI cache.
     * @return array Map of filename => base64-encoded image data for successfully generated images.
     */
    public function generate_images(
        $fileareatemplate,
        $imagepromptdata,
        $overallimagecontext,
        $cache = false
    ) {
        $imageurls = [];
        $imagecnt = 0;

        $token = static::get_cp_token();
        if (!$token) {
            return [];
        }
        $url = utils::get_cloud_poodll_server() . '/webservice/rest/server.php';

        // Build one CloudPoodll request per image so they can all be fired in parallel below.
        $requests = $filenametrack = $prompttrack = [];
        foreach ($fileareatemplate as $filename => $filecontent) {
            if (!is_array($imagepromptdata)) {
                $prompt = $imagepromptdata;
            } else if (array_key_exists($imagecnt, $imagepromptdata)) {
                $prompt = $imagepromptdata[$imagecnt];
            } else {
                continue;
            }
            $imagecnt++;
            // We make a local copy of this because we may modify it.
            $theoverallimagecontext = $overallimagecontext;
            $stylekeywords = [
                'flat vector illustration',
                'cartoon',
                'action comic',
                'illustration',
                'photorealistic',
                'digital painting',
                'sketch',
                'line drawing',
                'realistic',
                'infographic',
                'black and white photo',
                'black and white movie',
                'hand drawn',
                '3d render',
            ];
            $stylefound = false;
            // Check if the prompt has specified a style.
            foreach ($stylekeywords as $stylekeyword) {
                if (stripos(mb_strtolower($prompt), $stylekeyword) !== false) {
                    $stylefound = true;
                    break;
                }
            }

            // If no prompt is specified, check if the overallimagecontext is specifying a style.
            // If so, add it to the prompt and set overallimagecontext to "--".
            if (!$stylefound && !empty($theoverallimagecontext) && $theoverallimagecontext !== "--") {
                $matchedstyle = '';
                $stylepos = false;
                foreach ($stylekeywords as $stylekeyword) {
                    $pos = stripos($theoverallimagecontext, $stylekeyword);
                    if ($pos !== false) {
                        $stylefound = true;
                        $matchedstyle = $stylekeyword;
                        $stylepos = $pos;
                        break;
                    }
                }
                if ($stylefound && $stylepos === 0) {
                    $prompt = "Give me a {$matchedstyle} image, with no text on it, depicting: " . $prompt;
                    $theoverallimagecontext = "--";
                }
            }

            // If no style is found, use "cute cartoon image".
            if (!$stylefound) {
                $prompt = "Give me a simple cute cartoon image, with no text on it, depicting: " . $prompt;
            }

            // If we have an overall image context, set that.
            if ($theoverallimagecontext && !empty($theoverallimagecontext) && $theoverallimagecontext !== "--") {
                $prompt .= PHP_EOL . " in the context of the following topic: " . $theoverallimagecontext;
            }

            // Serve from cache if we already generated this exact image prompt.
            if ($cache) {
                $cached = self::check_cache('generate_images', $prompt, 'cloud poodll');
                if ($cached !== false) {
                    $imageurls[$filename] = $cached;
                    continue;
                }
            }

            $params = $this->prepare_image_request_params($prompt, $token);
            $requests[] = ['url' => $url, 'postfields' => format_postdata_for_curlcall($params)];
            $idx = utils::array_key_last($requests);
            $filenametrack[$idx] = $filename;
            $prompttrack[$idx] = $prompt;
        }

        if (empty($requests)) {
            return $imageurls;
        }

        // Fire all the image requests at once (curl_multi), then retry any that failed once.
        $curl = new curl();
        $curlopts = ['CURLOPT_TIMEOUT' => 240];
        $retryrequests = $retryidx = [];
        foreach ($curl->multirequest($requests, $curlopts) as $i => $resp) {
            if ($image = self::process_image_response($resp)) {
                $imageurls[$filenametrack[$i]] = $image;
                if ($cache) {
                    self::set_cache('generate_images', $prompttrack[$i], 'cloud poodll', $image);
                }
            } else {
                $retryrequests[] = $requests[$i];
                $retryidx[] = $i;
            }
        }
        if (!empty($retryrequests)) {
            foreach ($curl->multirequest($retryrequests, $curlopts) as $j => $resp) {
                $i = $retryidx[$j];
                if ($image = self::process_image_response($resp)) {
                    $imageurls[$filenametrack[$i]] = $image;
                    if ($cache) {
                        self::set_cache('generate_images', $prompttrack[$i], 'cloud poodll', $image);
                    }
                }
            }
        }

        return $imageurls;
    }

    /**
     * Build the CloudPoodll webservice parameters for a single image-generation request.
     *
     * @param string $prompt The image prompt.
     * @param string $token The CloudPoodll web service token.
     * @return array The request parameters.
     */
    protected function prepare_image_request_params($prompt, $token) {
        global $USER;
        return [
            'wstoken' => $token,
            'wsfunction' => 'local_cpapi_call_ai',
            'moodlewsrestformat' => 'json',
            'appid' => static::get_component_from_classname(static::class),
            'owner' => hash('md5', $USER->username),
            'action' => 'generate_images',
            'subject' => '1',
            'prompt' => $prompt,
            'language' => $this->ttslanguage,
            'region' => $this->region,
        ];
    }

    /**
     * Turn a raw CloudPoodll image-generation response into resized base64 image data.
     *
     * @param string $responsestring The raw web service response body.
     * @return string|null The base64-encoded resized image, or null on failure.
     */
    protected static function process_image_response($responsestring) {
        global $CFG;

        if (!utils::is_json($responsestring)) {
            return null;
        }
        $response = json_decode($responsestring);
        if (!isset($response->returnCode) || $response->returnCode != '0') {
            return null;
        }
        $payload = json_decode($response->returnMessage);
        if (isset($payload[0]->url)) {
            // Fallback branch - CloudPoodll normally returns the image inline as b64_json.
            // But some model may yet return URLs.
            require_once($CFG->libdir . '/filelib.php');
            $rawdata = download_file_content($payload[0]->url, null, null, false, 60, 10);
        } else if (isset($payload[0]->b64_json)) {
            $rawdata = base64_decode($payload[0]->b64_json);
        } else {
            return null;
        }
        if ($rawdata === false) {
            return null;
        }
        return base64_encode(self::make_image_smaller($rawdata));
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
            $this->errormessage = 'Provider not found or not enabled for this action.';
            return null;
        }

        $manager = $providerdata['manager'];
        $providerinstance = $providerdata['provider'];
        $params['userid'] = $params['userid'] ?? $USER->id;
        $params['prompttext'] = $params['prompttext'] ?? '';
        $action = new $actionclass(...$params);
        $result = static::call_and_store_action($manager, $providerinstance, $action);
        $response = $result->get_response_data();
        $returnmessage = isset($response['jsondata']) ? $response['jsondata'] : $response['generatedcontent'];
        return (object) [
            'returnCode' => $result->get_success() ? 0 : 1,
            'returnMessage' => $result->get_success() ? $returnmessage : $result->get_error_message(),
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

        return $result;
    }

    public static function call_cp_api($params = []) {
        global $USER;
        $token = $params['wstoken'] ?? static::get_cp_token();
        if (!$token) {
            // Callers see only a null and report a generic failure, so say which half is missing.
            $conf = get_config(constants::M_COMPONENT);
            self::log_cp_api('no token', [
                'wsfunction' => $params['wsfunction'] ?? 'local_cpapi_call_ai',
                'apiuser' => empty($conf->apiuser) ? 'NOT SET' : 'set',
                'apisecret' => empty($conf->apisecret) ? 'NOT SET' : 'set',
                'hint' => 'Credentials are set but no token came back: the subscription may have '
                    . 'lapsed, or cloud.poodll.com may be unreachable from this server.',
            ]);
            return null;
        }
        $params['wstoken'] = $params['wstoken'] ?? $token;
        $params['wsfunction'] = $params['wsfunction'] ?? 'local_cpapi_call_ai';
        $params['moodlewsrestformat'] = $params['moodlewsrestformat'] ?? 'json';
        $params['appid'] = $params['appid'] ?? static::get_component_from_classname(static::class);
        $params['owner'] = $params['owner'] ?? hash('md5', $USER->username);
        $serverurl = utils::get_cloud_poodll_server() . '/webservice/rest/server.php';
        $response = utils::curl_fetch($serverurl, $params, "post");

        if (!utils::is_json($response)) {
            // The reply is thrown away here and the caller is left with a bare false, which is how
            // an AI generation run ends up reporting "text generation failed" and nothing more.
            // Whatever came back instead of JSON is usually the whole answer: an HTML error page,
            // an empty body, a plain text message from the cloud.
            self::log_cp_api('response was not JSON', [
                'wsfunction' => $params['wsfunction'],
                'action' => $params['action'] ?? '(none)',
                'url' => $serverurl,
                'responsetype' => gettype($response),
                'responselength' => is_string($response) ? strlen($response) : 0,
                'response' => is_string($response) ? substr($response, 0, 1000) : var_export($response, true),
            ]);
            return false;
        }

        $decoded = json_decode($response);

        // Valid JSON is not the same as a usable answer. A web service exception comes back as
        // well-formed JSON with no returnCode at all, which is what a changed or renamed cloud
        // function looks like from here.
        if (!isset($decoded->returnCode)) {
            self::log_cp_api('reply carried no returnCode', [
                'wsfunction' => $params['wsfunction'],
                'action' => $params['action'] ?? '(none)',
                'keys' => is_object($decoded) ? implode(', ', array_keys(get_object_vars($decoded))) : gettype($decoded),
                'response' => substr($response, 0, 1000),
            ]);
        } else if ($decoded->returnCode != '0') {
            self::log_cp_api('returnCode ' . $decoded->returnCode, [
                'wsfunction' => $params['wsfunction'],
                'action' => $params['action'] ?? '(none)',
                'returnMessage' => substr((string) ($decoded->returnMessage ?? ''), 0, 1000),
            ]);
        }

        return $decoded;
    }

    /**
     * Record a Cloud Poodll call that did not go as expected.
     *
     * Developer level, so it costs nothing on a live site, and deliberately never prints the
     * token or the credentials themselves - only whether they are there.
     *
     * @param string $what a short description of the problem
     * @param array $detail context to print alongside it
     * @return void
     */
    protected static function log_cp_api(string $what, array $detail): void {
        $lines = [];
        foreach ($detail as $key => $value) {
            $lines[] = $key . ': ' . str_replace(["\r", "\n"], ' ', (string) $value);
        }
        debugging('mod_minilesson cloud poodll call failed - ' . $what . ' [' . implode(' | ', $lines) . ']',
            DEBUG_DEVELOPER);
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
            $mapactionclass = static::get_action_parentclass($actionclass);
            $allproviders = $manager->get_providers_for_actions([$mapactionclass], true);
            if (!empty($allproviders[$mapactionclass])) {
                foreach ($allproviders[$mapactionclass] as $aiprovider) {
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
                            $actionclass = ltrim($actionclass, '\\');
                            $mapactionclass = ltrim($mapactionclass, '\\');
                            $actionconfig = !empty($record->actionconfig) ? json_decode($record->actionconfig, true) : '';
                            if (!empty($actionconfig) && isset($actionconfig[$mapactionclass])) {
                                // Copy parent class settings to action class.
                                $actionconfig[$actionclass] = $actionconfig[$mapactionclass];
                                // For Moodle version 5 or later.
                                $actionconfig[$actionclass]['settings']['systeminstruction'] = $actionclass::get_system_instruction();
                                // Set Modal Config.
                                $plugintypename = str_replace('aiprovider_', '', ltrim(strstr($record->provider, '\\', true), '\\'));
                                if (empty($plugintypename)) {
                                    $plugintypename = str_replace('aiprovider_', '', $record->provider);
                                }
                                $actionconfig[$actionclass]['settings']['modelextraparams'] = json_encode(
                                    $actionclass::get_model_parameters($plugintypename)
                                );
                                $record->actionconfig = json_encode($actionconfig);
                            }
                            // Instantiate the provider class with the record's data.
                            return new $record->provider(
                                /* enabled:  */
                                $record->enabled,
                                /* name:  */
                                $record->name,
                                /* config:  */
                                $record->config,
                                /* actionconfig:  */
                                $record->actionconfig,
                                /* id:  */
                                $record->id
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
                    ltrim($providerinstance->provider, '\\'),
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

    /**
     * Resolve the frankenstyle component name from a PSR-4 class name.
     *
     * This intentionally does not delegate to \core\component /
     * \core_component::get_component_from_classname(). The namespaced \core\component
     * class only exists on Moodle 5.0+, and core_component::get_component_from_classname()
     * does not exist before Moodle 4.4, so a direct reference fatals on Moodle 4.3.
     * Every caller passes a PSR-4 / frankenstyle class name, so the first namespace
     * segment is the component.
     *
     * @param string $classname
     * @return string The component name, or '' if it could not be determined.
     */
    public static function get_component_from_classname($classname): string {
        return strstr(ltrim($classname, '\\'), '\\', true) ?: '';
    }
}
