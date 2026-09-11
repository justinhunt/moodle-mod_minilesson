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

namespace mod_minilesson\local\chatagent;

use mod_minilesson\constants;

/**
 * Talks to the Gemini Interactions API with the site's own API key.
 *
 * Every Interactions-shaped detail lives in this class - the request body, the steps array,
 * the usage block, the error mapping - so when the API changes shape (it replaced `outputs`
 * with `steps` in May 2026) there is one file to fix rather than a loop to untangle.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini_driver implements provider_driver {
    /** @var string The interactions endpoint. */
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    /** @var string Model used when the site has not set one. */
    const DEFAULT_MODEL = 'gemini-3.8-flash';

    /**
     * @var int Seconds to wait for one model call. A turn that drives several tools can think
     * for a while, so this is generous; the browser is not blocked on it because each request
     * makes only one call.
     */
    const TIMEOUT = 120;

    /** @var string|null The site's Gemini API key. */
    protected ?string $apikey;

    /** @var string The model to call. */
    protected string $model;

    /**
     * Read the provider's configuration once.
     */
    public function __construct() {
        $config = get_config(constants::M_COMPONENT);
        $this->apikey = !empty($config->geminiapikey) ? trim($config->geminiapikey) : null;
        $this->model = !empty($config->chatagentmodel) ? trim($config->chatagentmodel) : self::DEFAULT_MODEL;
    }

    /**
     * The URL to post to. A method rather than the constant directly, so the diagnostic
     * subclass used by the CLI harness can send a request somewhere unreachable on purpose.
     *
     * @return string
     */
    protected function endpoint(): string {
        return static::ENDPOINT;
    }

    /**
     * Whether the site has supplied a key for this provider.
     *
     * @return bool
     */
    public function is_available(): bool {
        return !empty($this->apikey);
    }

    /**
     * This provider's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'ownkey';
    }

    /**
     * Run one model call.
     *
     * @param array $input
     * @param array $tools
     * @param string $instruction
     * @param string|null $previous
     * @return result
     */
    public function create_interaction(
        array $input,
        array $tools,
        string $instruction,
        ?string $previous
    ): result {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (!$this->is_available()) {
            return result::fail(
                result::ERROR_AUTH,
                get_string('chatagent_error_nokey', constants::M_COMPONENT)
            );
        }

        $payload = [
            'model' => $this->model,
            'input' => $input,
            'tools' => $tools,
            'system_instruction' => $instruction,
            // Server-side history is what previous_interaction_id reads, so it has to be stored.
            'store' => true,
        ];
        if (!empty($previous)) {
            $payload['previous_interaction_id'] = $previous;
        }

        $curl = new \curl();
        $curl->setHeader(['x-goog-api-key: ' . $this->apikey, 'content-type: application/json']);
        $curl->setopt(['CURLOPT_TIMEOUT' => self::TIMEOUT]);
        $body = $curl->post($this->endpoint(), json_encode($payload, JSON_UNESCAPED_UNICODE));

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);

        // Moodle's curl wrapper does not always report a failure through errno: a request its
        // security helper refuses comes back with errno 0, no status code and an error string
        // in place of a body. Anything that produced no HTTP response at all is a connection
        // problem, however it was reported.
        if ($curl->get_errno() || !empty($curl->error) || $httpcode === 0) {
            $this->log_failure('no response (errno ' . $curl->get_errno() . '): ' . $curl->error);
            return result::fail(
                result::ERROR_CONNECTION,
                get_string('chatagent_error_connection', constants::M_COMPONENT)
            );
        }

        $decoded = json_decode($body, true);

        if ($httpcode < 200 || $httpcode >= 300) {
            return $this->map_http_error($httpcode, $decoded, $previous);
        }
        if (!is_array($decoded) || !isset($decoded['status'])) {
            $this->log_failure('unparseable response: ' . substr((string) $body, 0, 500));
            return result::fail(
                result::ERROR_TRANSPORT,
                get_string('chatagent_error_transport', constants::M_COMPONENT)
            );
        }

        return $this->normalise($decoded);
    }

    /**
     * Turn a successful response into the provider-neutral result.
     *
     * @param array $decoded
     * @return result
     */
    protected function normalise(array $decoded): result {
        $result = new result();
        $result->interactionid = (string) ($decoded['id'] ?? '');
        $result->status = $decoded['status'] === result::STATUS_REQUIRES_ACTION
            ? result::STATUS_REQUIRES_ACTION
            : result::STATUS_COMPLETED;

        foreach (($decoded['steps'] ?? []) as $step) {
            $normalised = $this->normalise_step($step);
            if ($normalised !== null) {
                $result->steps[] = $normalised;
            }
        }

        $usage = $decoded['usage'] ?? [];
        $result->usage = [
            'input_tokens' => (int) ($usage['total_input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['total_output_tokens'] ?? 0),
            // Only a brokered provider meters credits; with the site's own key there are none.
            'credits' => null,
        ];

        return $result;
    }

    /**
     * Normalise one step, or return null for a step type the agent has no use for.
     *
     * @param array $step
     * @return array|null
     */
    protected function normalise_step($step): ?array {
        if (!is_array($step) || !isset($step['type'])) {
            return null;
        }
        switch ($step['type']) {
            case 'function_call':
                // Arguments arrive as an object, but a JSON string is tolerated so a change
                // of shape degrades into a parse rather than a fatal.
                $args = $step['arguments'] ?? [];
                if (is_string($args)) {
                    $args = json_decode($args, true) ?: [];
                }
                return [
                    'type' => 'function_call',
                    'callid' => (string) ($step['id'] ?? ''),
                    'name' => (string) ($step['name'] ?? ''),
                    'args' => is_array($args) ? $args : [],
                ];

            case 'model_output':
                $text = '';
                foreach (($step['content'] ?? []) as $content) {
                    if (isset($content['type'], $content['text']) && $content['type'] === 'text') {
                        $text .= $content['text'];
                    }
                }
                return $text === '' ? null : ['type' => 'text', 'text' => $text];

            case 'thought':
                // Kept for the progress display; never shown as the assistant's answer.
                $text = (string) ($step['text'] ?? ($step['summary'] ?? ''));
                return $text === '' ? null : ['type' => 'thought', 'text' => $text];

            default:
                return null;
        }
    }

    /**
     * Map an HTTP failure onto a provider-neutral error code.
     *
     * Google's canonical `status` and the `reason` in the error details are what this reads,
     * not the HTTP code alone and not the prose message: an invalid API key comes back as a
     * 400, so the status code by itself would call it a bad request. The prose is worse still -
     * it is localised, and matching on it would break in another language.
     *
     * The provider's own message is logged rather than returned: it can echo fragments of the
     * API key, and a teacher can do nothing with it either way.
     *
     * @param int $httpcode
     * @param mixed $decoded decoded response body, if it was JSON
     * @param string|null $previous the interaction we asked to continue, if any
     * @return result
     */
    protected function map_http_error(int $httpcode, $decoded, ?string $previous): result {
        $error = $this->extract_error($decoded);
        $status = (string) ($error['status'] ?? '');
        $reasons = [];
        foreach (($error['details'] ?? []) as $detail) {
            if (!empty($detail['reason'])) {
                $reasons[] = $detail['reason'];
            }
        }

        $this->log_failure('HTTP ' . $httpcode . ' ' . $status
            . ' [' . implode(',', $reasons) . ']: ' . substr((string) ($error['message'] ?? ''), 0, 300));

        if (
            $status === 'UNAUTHENTICATED' || $status === 'PERMISSION_DENIED'
                || in_array('API_KEY_INVALID', $reasons, true)
                || in_array('ACCESS_TOKEN_EXPIRED', $reasons, true)
                || $httpcode === 401 || $httpcode === 403
        ) {
            return result::fail(
                result::ERROR_AUTH,
                get_string('chatagent_error_auth', constants::M_COMPONENT)
            );
        }

        if ($status === 'RESOURCE_EXHAUSTED' || $httpcode === 429) {
            return result::fail(
                result::ERROR_QUOTA,
                get_string('chatagent_error_quota', constants::M_COMPONENT)
            );
        }

        // The conversation we asked to continue has aged out of the provider's retention window.
        // Routine rather than exceptional, so it gets its own code and the controller replays.
        // Only ever considered when we actually sent a handle, and only for a status that means
        // the thing named could not be found - never on the mere presence of the word.
        if (!empty($previous) && ($status === 'NOT_FOUND' || $httpcode === 404)) {
            return result::fail(result::ERROR_EXPIRED, '');
        }

        return result::fail(
            result::ERROR_UNKNOWN,
            get_string('chatagent_error_unknown', constants::M_COMPONENT)
        );
    }

    /**
     * Dig the error object out of a failure body.
     *
     * The API returns its errors wrapped in a single-element array - `[{"error": {...}}]` - so
     * the obvious lookup finds nothing. Both shapes are accepted, since one of them is only a
     * convention and conventions change.
     *
     * @param mixed $decoded
     * @return array
     */
    protected function extract_error($decoded): array {
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded[0]) && is_array($decoded[0])) {
            $decoded = $decoded[0];
        }
        $error = $decoded['error'] ?? [];
        return is_array($error) ? $error : [];
    }

    /**
     * Record a provider failure for the admin, keeping the detail out of the teacher's view.
     *
     * @param string $message
     * @return void
     */
    protected function log_failure(string $message): void {
        debugging('mod_minilesson agent (gemini): ' . $message, DEBUG_DEVELOPER);
    }
}
