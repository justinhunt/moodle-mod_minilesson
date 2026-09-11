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
use mod_minilesson\utils;

/**
 * Reaches Gemini through Cloud Poodll, for a site with Poodll credentials but no Gemini key.
 *
 * Cloud Poodll checks the site's subscription, applies the account's daily allowance, binds each
 * interaction to the account that created it, and forwards the call to Google with Poodll's key.
 * It passes Google's interaction back as it came, trimmed, so the parsing is the Gemini driver's
 * own - which is why this extends it. Only the transport and the failure mapping differ.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cloudpoodll_driver extends gemini_driver {
    /** @var string The Cloud Poodll web service that makes the model call. */
    const WSFUNCTION = 'local_cpapi_chatagent_create_interaction';

    /**
     * @var int Seconds to wait for Cloud Poodll. Longer than Cloud Poodll waits for Google, so a
     * slow model call comes back as Google's answer rather than as a dropped connection.
     */
    const CLOUD_TIMEOUT = 150;

    /** @var string The envelope's code when the subscription does not cover this site. */
    const CLOUD_LICENCE = 'licence';
    /** @var string The envelope's code when the account's daily allowance is used up. */
    const CLOUD_QUOTA = 'quota';
    /** @var string The envelope's code when the interaction is unknown to this account. */
    const CLOUD_EXPIRED = 'expired';
    /** @var string The envelope's code when Google refused the call. */
    const CLOUD_UPSTREAM = 'upstream';

    /** @var string|null The site's Cloud Poodll API user. */
    protected ?string $apiuser;

    /** @var string|null The site's Cloud Poodll API secret. */
    protected ?string $apisecret;

    /** @var string The site's Poodll region. */
    protected string $region;

    /**
     * Read the provider's configuration once.
     */
    public function __construct() {
        parent::__construct();
        $config = get_config(constants::M_COMPONENT);
        $this->apiuser = !empty($config->apiuser) ? trim($config->apiuser) : null;
        $this->apisecret = !empty($config->apisecret) ? trim($config->apisecret) : null;
        $this->region = !empty($config->awsregion) ? $config->awsregion : 'us-east-1';
    }

    /**
     * Whether the site has Poodll credentials. Deliberately no network call: this is asked on
     * page loads, and whether the subscription covers the agent is Cloud Poodll's to say on the
     * first message.
     *
     * @return bool
     */
    public function is_available(): bool {
        return !empty($this->apiuser) && !empty($this->apisecret);
    }

    /**
     * This provider's name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'cloudpoodll';
    }

    /**
     * Run one model call through Cloud Poodll.
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
        global $CFG, $USER;

        if (!$this->is_available()) {
            return result::fail(
                result::ERROR_AUTH,
                get_string('chatagent_error_cp_nocredentials', constants::M_COMPONENT)
            );
        }

        $params = [
            'appid' => constants::M_COMPONENT,
            'siteurl' => $CFG->wwwroot,
            'region' => $this->region,
            'owner' => hash('md5', $USER->username ?? ''),
            'input' => json_encode($input, JSON_UNESCAPED_UNICODE),
            'tools' => json_encode($tools, JSON_UNESCAPED_UNICODE),
            'system_instruction' => $instruction,
            'previous_interaction_id' => (string) $previous,
        ];

        // A cached token can go stale on the cloud's side before its recorded expiry - a secret
        // reset, a token revoked - so one rejection earns a fresh token and a second try.
        foreach ([false, true] as $forcenewtoken) {
            $token = $this->token($forcenewtoken);
            if (empty($token)) {
                $this->log_failure('could not get a Cloud Poodll token');
                return result::fail(result::ERROR_AUTH, $this->error_message(result::ERROR_AUTH));
            }
            [$httpcode, $body] = $this->send($token, $params);
            if ($httpcode === 0) {
                $this->log_failure('no response from Cloud Poodll: ' . substr($body, 0, 300));
                return result::fail(result::ERROR_CONNECTION, $this->error_message(result::ERROR_CONNECTION));
            }
            $decoded = json_decode($body, true);
            if (!$forcenewtoken && is_array($decoded) && ($decoded['errorcode'] ?? '') === 'invalidtoken') {
                continue;
            }
            break;
        }

        if (!is_array($decoded)) {
            $this->log_failure('unparseable Cloud Poodll response (HTTP ' . $httpcode . '): ' . substr($body, 0, 500));
            return result::fail(result::ERROR_TRANSPORT, $this->error_message(result::ERROR_TRANSPORT));
        }

        // Moodle's web service layer refused the call before it ran.
        if (isset($decoded['exception'])) {
            return $this->map_ws_exception($decoded);
        }

        if (!isset($decoded['returnCode'], $decoded['errorcode'], $decoded['body'])) {
            $this->log_failure('unexpected Cloud Poodll response: ' . substr($body, 0, 500));
            return result::fail(result::ERROR_TRANSPORT, $this->error_message(result::ERROR_TRANSPORT));
        }

        if ((int) $decoded['returnCode'] === 0) {
            $interaction = json_decode((string) $decoded['body'], true);
            if (!is_array($interaction) || !isset($interaction['status'])) {
                $this->log_failure('unparseable interaction from Cloud Poodll: ' . substr((string) $decoded['body'], 0, 500));
                return result::fail(result::ERROR_TRANSPORT, $this->error_message(result::ERROR_TRANSPORT));
            }
            $result = $this->normalise($interaction);
            $remaining = (int) ($decoded['remainingtokens'] ?? -1);
            // Tokens left in the account's daily allowance; null when the account has no limit.
            $result->usage['credits'] = $remaining >= 0 ? $remaining : null;
            return $result;
        }

        return $this->map_cloud_error($decoded, $previous);
    }

    /**
     * Map a failure Cloud Poodll reported in its envelope.
     *
     * @param array $decoded the envelope
     * @param string|null $previous the interaction we asked to continue, if any
     * @return result
     */
    protected function map_cloud_error(array $decoded, ?string $previous): result {
        $errorcode = (string) $decoded['errorcode'];
        $detail = substr((string) $decoded['body'], 0, 300);

        switch ($errorcode) {
            case self::CLOUD_LICENCE:
                $this->log_failure('subscription check failed: ' . $detail);
                return result::fail(
                    result::ERROR_AUTH,
                    get_string('chatagent_error_cp_licence', constants::M_COMPONENT)
                );

            case self::CLOUD_QUOTA:
                return result::fail(result::ERROR_QUOTA, $this->error_message(result::ERROR_QUOTA));

            case self::CLOUD_EXPIRED:
                // Aged out, or not this account's to continue - the same either way, and the
                // controller replays the conversation from its own copy.
                return result::fail(result::ERROR_EXPIRED, '');

            case self::CLOUD_UPSTREAM:
                $httpcode = (int) ($decoded['httpcode'] ?? 0);
                if ($httpcode === 0) {
                    $this->log_failure('Cloud Poodll could not reach Google: ' . $detail);
                    return result::fail(result::ERROR_UNKNOWN, $this->error_message(result::ERROR_UNKNOWN));
                }
                $result = $this->map_http_error($httpcode, json_decode((string) $decoded['body'], true), $previous);
                // Google refusing Poodll's own key is Poodll's problem: this site's admin cannot
                // fix it, so don't send them to check their settings.
                if ($result->errorcode === result::ERROR_AUTH) {
                    return result::fail(result::ERROR_UNKNOWN, $this->error_message(result::ERROR_UNKNOWN));
                }
                // Likewise a rate limit on Poodll's project is not this account's allowance.
                if ($result->errorcode === result::ERROR_QUOTA) {
                    return result::fail(
                        result::ERROR_QUOTA,
                        get_string('chatagent_error_cp_busy', constants::M_COMPONENT)
                    );
                }
                return $result;

            default:
                $this->log_failure('Cloud Poodll refused the call (' . $errorcode . '): ' . $detail);
                return result::fail(result::ERROR_UNKNOWN, $this->error_message(result::ERROR_UNKNOWN));
        }
    }

    /**
     * Map an exception Moodle's web service layer returned on the Cloud Poodll server.
     *
     * @param array $decoded
     * @return result
     */
    protected function map_ws_exception(array $decoded): result {
        $errorcode = (string) ($decoded['errorcode'] ?? '');
        $this->log_failure('Cloud Poodll exception ' . $errorcode . ': ' . substr((string) ($decoded['message'] ?? ''), 0, 300));

        if ($errorcode === 'invalidtoken') {
            return result::fail(result::ERROR_AUTH, $this->error_message(result::ERROR_AUTH));
        }
        // The function is not there, or not in the service this token belongs to: the Cloud
        // Poodll server has not been upgraded to offer the agent yet.
        if ($errorcode === 'invalidrecord' || $errorcode === 'accessexception') {
            return result::fail(
                result::ERROR_UNKNOWN,
                get_string('chatagent_error_cp_notsupported', constants::M_COMPONENT)
            );
        }
        return result::fail(result::ERROR_UNKNOWN, $this->error_message(result::ERROR_UNKNOWN));
    }

    /**
     * A Cloud Poodll token for this site.
     *
     * @param bool $force fetch a new one even if a cached one looks valid
     * @return string|false
     */
    protected function token(bool $force) {
        return utils::fetch_token($this->apiuser, $this->apisecret, $force);
    }

    /**
     * Post the call to Cloud Poodll's web service endpoint.
     *
     * A POST, unlike utils::call_cloudpoodll(), which sends a GET: the input carries any
     * attachments, and a GET could not.
     *
     * @param string $token
     * @param array $params the web service parameters
     * @return array [http code (0 for no response), response body]
     */
    protected function send(string $token, array $params): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $params['wstoken'] = $token;
        $params['wsfunction'] = self::WSFUNCTION;
        $params['moodlewsrestformat'] = 'json';

        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => self::CLOUD_TIMEOUT]);
        $body = $curl->post(utils::get_cloud_poodll_server() . '/webservice/rest/server.php', $params);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        // As with Google: a refused or failed request is not always reported through errno.
        if ($curl->get_errno() || !empty($curl->error)) {
            return [0, (string) $curl->error];
        }
        return [$httpcode, (string) $body];
    }

    /**
     * Wording that sends the admin to their Poodll settings rather than to a Gemini key.
     *
     * @param string $errorcode
     * @return string
     */
    protected function error_message(string $errorcode): string {
        switch ($errorcode) {
            case result::ERROR_AUTH:
                return get_string('chatagent_error_cp_auth', constants::M_COMPONENT);
            case result::ERROR_QUOTA:
                return get_string('chatagent_error_cp_quota', constants::M_COMPONENT);
            case result::ERROR_CONNECTION:
                return get_string('chatagent_error_cp_connection', constants::M_COMPONENT);
            default:
                return parent::error_message($errorcode);
        }
    }

    /**
     * Record a provider failure for the admin, keeping the detail out of the teacher's view.
     *
     * @param string $message
     * @return void
     */
    protected function log_failure(string $message): void {
        debugging('mod_minilesson agent (cloudpoodll): ' . $message, DEBUG_DEVELOPER);
    }
}
