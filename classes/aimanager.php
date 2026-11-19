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
use core_ai\aiactions\generate_text as core_generate_text;
use core_ai\aiactions\responses\response_base;
use core_ai\manager;
use core_ai\provider;
use mod_minilesson\local\aiprompts;

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

    /** @var int The setting value that means "use Cloud Poodll", the default for every kind of request. */
    public const CLOUDPOODLL_OPTION = -1;

    /** @var string Grading, correcting and analysing what a student wrote or said. */
    public const OPTION_GRADE_STUDENT_SUBMISSION = 'grade_student_submission';

    /** @var string Code (HTML, Yarn, WebVTT) written by the code editor's AI helper. */
    public const OPTION_GENERATE_CODE = 'generate_code';

    /** @var string The lesson bank's custom field values for an AI generated lesson. */
    public const OPTION_LESSONBANK_DETAILS = 'lessonbank_details';

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

    /** @var string */
    public const FUNC_GRADE_CHAT = 'grade_chat';

    /**
     * Which setting decides where each function's requests go.
     *
     * Functions not listed here (structured content, images, translation, embeddings, semantic
     * similarity) always use Cloud Poodll: core Moodle AI has no provider action that can take them yet.
     *
     * @var array
     */
    public const OPTION_MAPPING = [
        self::FUNC_REQUEST_GRAMMAR_CORRECTION => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_AUTOGRADE_SPEECH => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_AUTOGRADE_TEXT => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_GET_TOPIC_RELEVANCE => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_PREDICT_CEFR => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_COUNT_UNIQUE_IDEAS => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_GRADE_CHAT => self::OPTION_GRADE_STUDENT_SUBMISSION,
        self::FUNC_GENERATE_CUSTOMFIELD => self::OPTION_LESSONBANK_DETAILS,
    ];

    /**
     * The core AI action each kind of request is sent as. All of them are plain text generation:
     * the prompt carries everything, so any provider that can generate text can take them.
     *
     * @var array
     */
    public const AIMANAGER_ACTIONS = [
        self::OPTION_GRADE_STUDENT_SUBMISSION => core_generate_text::class,
        self::OPTION_GENERATE_CODE => core_generate_text::class,
        self::OPTION_LESSONBANK_DETAILS => core_generate_text::class,
    ];

    /**
     * The kinds of request an admin can send to a Moodle AI provider, for the settings page.
     *
     * @return array
     */
    public static function get_action_options() {
        $options = [];
        foreach (array_keys(static::AIMANAGER_ACTIONS) as $actiontype) {
            // Lesson bank details are only generated on a lesson bank site, so the setting is only shown there.
            if ($actiontype === self::OPTION_LESSONBANK_DETAILS && !static::has_lessonbank()) {
                continue;
            }
            $options[$actiontype] = [
                'name' => get_string($actiontype, constants::M_COMPONENT),
                'description' => get_string($actiontype . '_desc', constants::M_COMPONENT),
            ];
        }
        return $options;
    }

    /**
     * Whether this site is a lesson bank site (local_lessonbank is installed).
     *
     * @return bool
     */
    public static function has_lessonbank(): bool {
        return \core_component::get_component_directory('local_lessonbank') !== null;
    }

    /**
     * The full setting name (component/name) for a kind of request.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return string
     */
    public static function get_action_settingname($actiontype) {
        return constants::M_COMPONENT . "/aiaction_{$actiontype}";
    }

    /**
     * The provider chosen for a kind of request: a provider id (a name on Moodle 4.5), or CLOUDPOODLL_OPTION.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return int|string
     */
    public static function get_action_provider_setting($actiontype) {
        [$component, $settingname] = explode('/', static::get_action_settingname($actiontype), 2);
        $setting = get_config($component, $settingname);
        return !empty($setting) ? $setting : static::CLOUDPOODLL_OPTION;
    }

    /**
     * Whether the admin has sent this kind of request to a Moodle AI provider instead of Cloud Poodll.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return bool
     */
    public static function uses_moodle_ai(string $actiontype): bool {
        return isset(static::AIMANAGER_ACTIONS[$actiontype])
            && static::get_action_provider_setting($actiontype) != static::CLOUDPOODLL_OPTION
            && static::get_ai_manager() !== null;
    }

    /**
     * Not implemented.
     */
    public static function evaluate_passage() {
    }

    /**
     * Correct the grammar and spelling of a passage.
     *
     * @param string $passage
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being the corrected text
     */
    public function request_grammar_correction($passage) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->call_moodle_ai_json(
                self::OPTION_GRADE_STUDENT_SUBMISSION,
                aiprompts::grammar_correction($passage, $this->ttslanguage),
                ['correctedtext'],
                fn($data) => (string) $data->correctedtext
            );
        }
        $params['action'] = static::FUNC_REQUEST_GRAMMAR_CORRECTION;
        $params['prompt'] = $passage;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Grade a speech transcript.
     *
     * @param string $studentresponse
     * @param array|object $instructions feedbackscheme, feedbacklanguage, markscheme, maxmarks
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being the grade JSON
     */
    public function autograde_speech($studentresponse, $instructions) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->moodle_ai_autograde($studentresponse, $instructions, true);
        }
        $params['action'] = static::FUNC_AUTOGRADE_SPEECH;
        $params['prompt'] = json_encode($instructions);
        $params['language'] = $this->ttslanguage;
        $params['subject'] = $studentresponse;
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Grade a written response.
     *
     * @param string $studentresponse
     * @param array|object $instructions feedbackscheme, feedbacklanguage, markscheme, maxmarks
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being the grade JSON
     */
    public function autograde_text($studentresponse, $instructions) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->moodle_ai_autograde($studentresponse, $instructions, false);
        }
        $params['action'] = static::FUNC_AUTOGRADE_TEXT;
        $params['prompt'] = json_encode($instructions);
        $params['language'] = $this->ttslanguage;
        $params['subject'] = $studentresponse;
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Grade a response with a Moodle AI provider, in the same reply shape Cloud Poodll gives.
     *
     * A speech transcript with no punctuation at all is punctuated first, as Cloud Poodll does, because
     * the grading is much better on punctuated text. If that step fails the transcript is graded as it is.
     *
     * @param string $studentresponse
     * @param array|object $instructions
     * @param bool $isspeech
     * @return \stdClass returnCode/returnMessage
     */
    protected function moodle_ai_autograde($studentresponse, $instructions, bool $isspeech): \stdClass {
        $actiontype = self::OPTION_GRADE_STUDENT_SUBMISSION;
        $submittedtext = (string) $studentresponse;

        $unpunctuated = !preg_match('/[.,!?;:()\[\]{}"«»„“”‹›¡¿،؛؟。、「」『』【】《》]/u', $submittedtext);
        if ($isspeech && $unpunctuated && trim($submittedtext) !== '') {
            $punctuated = $this->call_moodle_ai_json(
                $actiontype,
                aiprompts::punctuation($submittedtext, $this->ttslanguage),
                ['passage']
            );
            $passage = $punctuated->returnCode === 0 ? $punctuated->data->passage : null;
            if (is_string($passage) && trim($passage) !== '') {
                $submittedtext = $passage;
            }
        }

        $instructionsobj = json_decode(json_encode($instructions));
        return $this->call_moodle_ai_json(
            $actiontype,
            aiprompts::autograde($submittedtext, $instructionsobj, $this->ttslanguage, $isspeech),
            ['correctedtext', 'marks'],
            function ($data) use ($submittedtext) {
                if (!isset($data->submittedtext) || !is_string($data->submittedtext)) {
                    $data->submittedtext = $submittedtext;
                }
                return json_encode($data);
            }
        );
    }

    /**
     * Grade a finished audio chat from its transcript.
     *
     * Goes to the Moodle AI provider chosen in "Grade student submission", or to the Cloud Poodll
     * grade_chat action by default. The Moodle AI path asks for the same reply as Cloud Poodll gives and
     * already tries a bad reply twice, so a caller that retries should only retry the Cloud Poodll path
     * (see uses_moodle_ai()).
     *
     * @param string $transcript one turn per line, each starting "AI:" or "Student:"
     * @param array $instructions criteria (the teacher's grading instructions), feedbacklanguage and
     *        targetlanguage (language codes)
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being JSON
     *         {"score", "gradeexplanation", "feedback"}
     */
    public function grade_chat(string $transcript, array $instructions) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            $targetlanguage = $instructions['targetlanguage'] ?? $this->ttslanguage;
            return $this->call_moodle_ai_json(
                self::OPTION_GRADE_STUDENT_SUBMISSION,
                aiprompts::grade_chat(
                    $transcript,
                    (string) ($instructions['criteria'] ?? ''),
                    $targetlanguage,
                    $instructions['feedbacklanguage'] ?? $targetlanguage
                ),
                ['score', 'feedback'],
                function ($data) {
                    // A score with no number in it, or no feedback, is a reply to try again.
                    if (!is_scalar($data->score) || !preg_match('/\d/', (string) $data->score)) {
                        return null;
                    }
                    if (!is_string($data->feedback) || trim($data->feedback) === '') {
                        return null;
                    }
                    return json_encode($data);
                }
            );
        }
        $params['action'] = static::FUNC_GRADE_CHAT;
        $params['subject'] = $transcript;
        $params['prompt'] = json_encode($instructions);
        $params['language'] = $this->ttslanguage;
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Not implemented.
     */
    public static function textanalyse_passage() {
    }

    /**
     * How relevant a student's response is to the topic.
     *
     * @param string $referencetext the topic
     * @param string $submittedtext the student's response
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being 0.1 to 1
     */
    public function get_topic_relevance($referencetext, $submittedtext) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->call_moodle_ai_json(
                self::OPTION_GRADE_STUDENT_SUBMISSION,
                aiprompts::topic_relevance((string) $referencetext, (string) $submittedtext),
                ['relevance'],
                function ($data) {
                    if (!is_numeric($data->relevance)) {
                        return null;
                    }
                    // The prompt asks for 1 to 10; Cloud Poodll answers 0.1 to 1.
                    return 0.1 * max(1, min(10, (float) $data->relevance));
                }
            );
        }
        $params['action'] = static::FUNC_GET_TOPIC_RELEVANCE;
        $params['prompt'] = $submittedtext;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = $referencetext;
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Count the different ideas in a passage.
     *
     * @param string $originaltext
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being the count
     */
    public function count_unique_ideas($originaltext) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->call_moodle_ai_json(
                self::OPTION_GRADE_STUDENT_SUBMISSION,
                aiprompts::count_unique_ideas((string) $originaltext, $this->ttslanguage),
                ['count'],
                fn($data) => is_numeric($data->count) ? (int) $data->count : null
            );
        }
        $params['action'] = static::FUNC_COUNT_UNIQUE_IDEAS;
        $params['prompt'] = $originaltext;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Estimate the CEFR level of a passage.
     *
     * @param string $originaltext
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being A1 to C2
     */
    public function predict_cefr($originaltext) {
        if (static::uses_moodle_ai(self::OPTION_GRADE_STUDENT_SUBMISSION)) {
            return $this->call_moodle_ai_json(
                self::OPTION_GRADE_STUDENT_SUBMISSION,
                aiprompts::predict_cefr((string) $originaltext, $this->ttslanguage),
                ['cefr'],
                function ($data) {
                    $cefr = strtoupper(trim((string) $data->cefr));
                    return in_array($cefr, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']) ? $cefr : null;
                }
            );
        }
        $params['action'] = static::FUNC_PREDICT_CEFR;
        $params['prompt'] = $originaltext;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        return self::call_cp_api($params);
    }

    /**
     * Generate lesson bank custom field values from a lesson's items.
     *
     * @param \core_customfield\field_controller[] $fields keyed by short name
     * @param array $importjson the lesson's items
     * @return \stdClass|false|null returnCode/returnMessage, returnMessage being a JSON object
     */
    public function generate_customfield(array $fields, array $importjson) {
        $prompt = aiprompts::customfield_values($fields, $importjson);
        if (static::uses_moodle_ai(self::OPTION_LESSONBANK_DETAILS)) {
            return $this->call_moodle_ai_json(self::OPTION_LESSONBANK_DETAILS, $prompt, []);
        }
        // Cloud Poodll only accepts action/subject/prompt/language/region (plus
        // appid/owner/wstoken added in call_cp_api), so the whole prompt goes as 'prompt'.
        $params = [];
        $params['action'] = 'generate_structured_content';
        $params['prompt'] = $prompt;
        $params['language'] = $this->ttslanguage;
        $params['subject'] = 'none';
        $params['region'] = $this->region;
        return self::call_cp_api($params);
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
     * @param bool $placeholders The text holds [[n]] placeholders that must come back unchanged.
     * @return string|false The translated text, or false on failure.
     */
    public function translate_text($text, $fromlang, $tolang, $placeholders = false) {
        // The text goes in as JSON: with a plain text payload the model tends to reply with
        // bare text instead of the requested JSON, which the Poodll API rejects.
        $payload = json_encode(['text' => $text], JSON_UNESCAPED_UNICODE);
        $prompt = "Translate the value of the text key in the JSON string that follows, ";
        $prompt .= "from language: $fromlang, into language: $tolang." . PHP_EOL;
        if ($placeholders) {
            $prompt .= "The text contains placeholders like [[1]]. Copy every placeholder into the translation ";
            $prompt .= "unchanged, where it belongs in the translated sentence." . PHP_EOL;
        }
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
     * Generate code (HTML, Yarn, WebVTT) for the AI helper above the code editor in an item's form.
     *
     * Goes to the Moodle AI provider the admin chose in "Generate code", or to Cloud Poodll by default.
     * Once an admin has chosen a provider, a failure there is reported and not sent on to Cloud Poodll.
     *
     * @param string $prompt
     * @param int|null $contextid
     * @return string|false the text, or false (see get_error_message())
     */
    public function generate_code($prompt, $contextid = null) {
        if (static::uses_moodle_ai(self::OPTION_GENERATE_CODE)) {
            $response = $this->call_moodle_ai(self::OPTION_GENERATE_CODE, $prompt, $contextid);
            if ($response->returnCode === 0 && trim($response->returnMessage) !== '') {
                return $response->returnMessage;
            }
            $this->errormessage = $response->returnMessage;
            return false;
        }

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
            $this->errormessage = 'No response from CloudPoodll.';
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
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

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

    /**
     * The Moodle AI provider chosen for a kind of request, if it is still there and usable.
     *
     * Only enabled, configured providers with text generation turned on are returned, so a provider that
     * was disabled or deleted after it was chosen gives null.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @return provider|null
     */
    public static function get_moodle_provider(string $actiontype): ?provider {
        global $CFG;
        $manager = static::get_ai_manager();
        $actionclass = static::AIMANAGER_ACTIONS[$actiontype] ?? null;
        $setting = static::get_action_provider_setting($actiontype);
        if (!$manager || !$actionclass || $setting == static::CLOUDPOODLL_OPTION) {
            return null;
        }
        // Static on Moodle 4.5, an instance method later: calling it through the instance works on both.
        $providers = $manager->get_providers_for_actions([$actionclass], true);
        foreach ($providers[$actionclass] ?? [] as $provider) {
            // Moodle 4.5 has one provider per plugin, chosen by name; later versions have instances, chosen by id.
            $id = $CFG->branch < 500 ? $provider->get_name() : $provider->id;
            if ((string) $id === (string) $setting) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * Send a prompt to the Moodle AI provider chosen for a kind of request, and return its text.
     *
     * The prompt goes as the stock core generate_text action, through the provider's own processor, so
     * the provider runs exactly as the admin set it up (model, system instruction, extra parameters,
     * rate limits) and nothing here depends on which provider it is. The call is logged in the core AI
     * action register like any other Moodle AI request.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @param string $prompt
     * @param int|null $contextid the context of the request, default the one given to the constructor
     * @return \stdClass returnCode (0 = success) and returnMessage (the text, or the error)
     */
    public function call_moodle_ai(string $actiontype, string $prompt, $contextid = null): \stdClass {
        global $CFG, $USER;

        $provider = static::get_moodle_provider($actiontype);
        if (!$provider) {
            $this->lastprovider = 'Moodle AI';
            return $this->moodle_ai_failure(get_string('aiprovider_unavailable', constants::M_COMPONENT), [
                'actiontype' => $actiontype,
                'setting' => static::get_action_provider_setting($actiontype),
            ]);
        }
        // The name the admin gave the provider; Moodle 4.5 has only the plugin's name.
        $this->lastprovider = $CFG->branch < 500 ? $provider->get_name() : $provider->name;

        $contextid = $contextid ?: ($this->contextid ?: \context_system::instance()->id);
        $action = new core_generate_text(
            contextid: (int) $contextid,
            userid: (int) $USER->id,
            prompttext: $prompt,
        );

        // This is what core_ai\manager::call_action_provider() does. It is protected, and the public
        // process_action() chooses the provider itself instead of using the one the admin chose here.
        $processclass = static::get_component_from_classname(get_class($provider))
            . '\\process_' . $action->get_basename();
        if (!class_exists($processclass)) {
            return $this->moodle_ai_failure(get_string('aiprovider_unavailable', constants::M_COMPONENT), [
                'provider' => $provider->get_name(),
                'missing class' => $processclass,
            ]);
        }

        try {
            $processor = new $processclass($provider, $action);
            $response = $processor->process();
        } catch (\Throwable $e) {
            return $this->moodle_ai_failure($e->getMessage(), [
                'provider' => $provider->get_name(),
                'exception' => get_class($e),
            ]);
        }

        static::log_moodle_ai_action($provider, $action, $response);

        if (!$response->get_success()) {
            $message = $response->get_errormessage();
            return $this->moodle_ai_failure(
                !empty($message) ? $message : get_string('aiprovider_badreply', constants::M_COMPONENT),
                ['provider' => $provider->get_name(), 'errorcode' => $response->get_errorcode()]
            );
        }

        $data = $response->get_response_data();
        return (object) [
            'returnCode' => 0,
            'returnMessage' => (string) ($data['generatedcontent'] ?? ''),
        ];
    }

    /**
     * Send a prompt that asks for a JSON object to a Moodle AI provider, and check the reply.
     *
     * Providers do not share a reliable "reply in JSON" switch, so the prompt asks for JSON and the reply
     * is read leniently (code fences and text around the object are ignored). It must decode to an object
     * holding every required key, and $transform must accept it. If not, the prompt is sent once more,
     * because a model that wrote prose instead of JSON usually gets it right the second time. A provider
     * error (rate limit, bad key, network) is not repeated.
     *
     * @param string $actiontype one of the OPTION_ constants
     * @param string $prompt
     * @param string[] $requiredkeys keys the JSON object must have
     * @param callable|null $transform turns the decoded object into the returnMessage, or returns null to
     *        reject it; by default the object is returned as JSON
     * @return \stdClass returnCode, returnMessage and, on success, data (the decoded object)
     */
    public function call_moodle_ai_json(
        string $actiontype,
        string $prompt,
        array $requiredkeys,
        ?callable $transform = null
    ): \stdClass {
        for ($attempt = 1; $attempt <= self::GENERATION_ATTEMPTS; $attempt++) {
            $response = $this->call_moodle_ai($actiontype, $prompt);
            if ($response->returnCode !== 0) {
                return $response;
            }

            $data = static::extract_json_object($response->returnMessage);
            $missing = $data ? array_diff($requiredkeys, array_keys(get_object_vars($data))) : $requiredkeys;
            if ($data && empty($missing)) {
                $value = $transform ? $transform($data) : json_encode($data);
                if ($value !== null) {
                    return (object) [
                        'returnCode' => 0,
                        'returnMessage' => $value,
                        'data' => $data,
                    ];
                }
            }

            debugging('mod_minilesson Moodle AI reply was not the JSON asked for [' . implode(' | ', [
                'provider: ' . $this->lastprovider,
                'attempt: ' . $attempt . ' of ' . self::GENERATION_ATTEMPTS,
                'missing keys: ' . ($data ? implode(', ', $missing) : '(not a JSON object)'),
                'reply: ' . str_replace(["\r", "\n"], ' ', substr($response->returnMessage, 0, 1000)),
            ]) . ']', DEBUG_DEVELOPER);
            if ($attempt < self::GENERATION_ATTEMPTS) {
                sleep(self::GENERATION_RETRY_DELAY);
            }
        }

        return $this->moodle_ai_failure(get_string('aiprovider_badreply', constants::M_COMPONENT), [
            'provider' => $this->lastprovider,
        ]);
    }

    /**
     * Find the JSON object in a model's reply.
     *
     * Accepts a bare object, an object in a markdown code fence, or an object with text before or after
     * it (taken from the first "{" to the last "}"). Models often write paragraph breaks inside a string
     * as real newlines instead of \\n, which is not valid JSON, so those are repaired. Anything that is
     * not a JSON object gives null.
     *
     * @param string $text
     * @return \stdClass|null
     */
    public static function extract_json_object(string $text): ?\stdClass {
        $text = trim($text);
        $candidates = [$text];
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $matches)) {
            $candidates[] = trim($matches[1]);
        }
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $candidates[] = substr($text, $start, $end - $start + 1);
        }
        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate);
            if (!$decoded instanceof \stdClass) {
                $decoded = json_decode(static::escape_json_control_chars($candidate));
            }
            if ($decoded instanceof \stdClass) {
                return $decoded;
            }
        }
        return null;
    }

    /**
     * Escape control characters (such as newlines) inside the string literals of a JSON document.
     *
     * Control characters between literals are left alone: they are ordinary whitespace. Working byte by
     * byte is safe for UTF-8, because every byte of a multi-byte character is 0x80 or above.
     *
     * @param string $json
     * @return string
     */
    protected static function escape_json_control_chars(string $json): string {
        $out = '';
        $instring = false;
        $escaped = false;
        $length = strlen($json);
        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if (!$instring) {
                $instring = $char === '"';
                $out .= $char;
                continue;
            }
            if ($escaped) {
                $escaped = false;
                $out .= $char;
                continue;
            }
            if ($char === '\\') {
                $escaped = true;
                $out .= $char;
            } else if ($char === '"') {
                $instring = false;
                $out .= $char;
            } else if (ord($char) < 0x20) {
                $out .= ["\n" => '\n', "\r" => '\r', "\t" => '\t'][$char] ?? sprintf('\u%04x', ord($char));
            } else {
                $out .= $char;
            }
        }
        return $out;
    }

    /**
     * Record a failed Moodle AI request and build the reply for it.
     *
     * @param string $message what went wrong, also kept for get_error_message()
     * @param array $detail context for the developer debugging message
     * @return \stdClass returnCode 1 and returnMessage
     */
    protected function moodle_ai_failure(string $message, array $detail = []): \stdClass {
        $this->errormessage = $message;
        $lines = [];
        foreach ($detail as $key => $value) {
            $lines[] = $key . ': ' . str_replace(["\r", "\n"], ' ', (string) $value);
        }
        debugging(
            'mod_minilesson Moodle AI request failed - ' . $message . ' [' . implode(' | ', $lines) . ']',
            DEBUG_DEVELOPER
        );
        return (object) [
            'returnCode' => 1,
            'returnMessage' => $message,
        ];
    }

    /**
     * Log a Moodle AI request in the core AI action register, as core_ai\manager does for its own.
     *
     * The register feeds the AI usage report and the core AI privacy provider. Core's own method for this
     * is private, so this writes the same record. A logging failure is reported to developers but does
     * not stop the request.
     *
     * @param provider $provider
     * @param core_generate_text $action
     * @param response_base $response
     * @return void
     */
    protected static function log_moodle_ai_action(
        provider $provider,
        core_generate_text $action,
        response_base $response
    ): void {
        global $DB;
        $record = [
            'actionname' => $action->get_basename(),
            'success' => $response->get_success(),
            'userid' => $action->get_configuration('userid'),
            'contextid' => $action->get_configuration('contextid'),
            'provider' => $provider->get_name(),
            'errorcode' => $response->get_errorcode(),
            'errormessage' => $response->get_errormessage(),
            'timecreated' => $action->get_configuration('timecreated'),
            'timecompleted' => $response->get_timecreated(),
        ];
        // Columns added after Moodle 4.5.
        $columns = $DB->get_columns('ai_action_register');
        if (isset($columns['error']) && method_exists($response, 'get_error')) {
            $record['error'] = $response->get_error();
        }
        if (isset($columns['model']) && method_exists($response, 'get_model_used')) {
            $record['model'] = $response->get_model_used();
        }
        try {
            $record['actionid'] = $action->store($response);
            $DB->insert_record('ai_action_register', (object) $record);
        } catch (\Throwable $e) {
            debugging('mod_minilesson could not log a Moodle AI request: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
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

    /**
     * The choices for a kind of request on the settings page: Cloud Poodll, then every enabled
     * Moodle AI provider that can generate text.
     *
     * @param string $actionclass the core AI action class
     * @return array setting value => name
     */
    public static function get_provider_options($actionclass) {
        global $CFG;
        $options = [static::CLOUDPOODLL_OPTION => get_string('cloudpoodll', constants::M_COMPONENT)];
        $manager = static::get_ai_manager();
        if (!empty($manager)) {
            $allproviders = $manager->get_providers_for_actions([$actionclass], true);
            foreach ($allproviders[$actionclass] ?? [] as $aiprovider) {
                if ($CFG->branch < 500) {
                    $options[$aiprovider->get_name()] = $aiprovider->get_name();
                } else {
                    $aiproviderrecord = $aiprovider->to_record();
                    $options[$aiproviderrecord->id] = $aiproviderrecord->name;
                }
            }
        }
        return $options;
    }

    /**
     * The core AI manager, or null before Moodle 4.5.
     *
     * @return manager|null
     */
    public static function get_ai_manager(): ?manager {
        if (!class_exists(manager::class)) {
            return null;
        }
        return di::get(manager::class);
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
