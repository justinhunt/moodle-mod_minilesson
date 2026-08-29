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

namespace mod_minilesson\local\aigen;

use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use mod_minilesson\constants;

/**
 * Shared core for the aigen integration surface.
 *
 * The aigen web service functions are exposed to external clients through three thin
 * front-ends that all sit on this class: openapi.php (the OpenAPI spec), aigen_rest.php
 * (a clean REST facade) and mcp.php (a Model Context Protocol endpoint). This class holds
 * everything they share - the brokered function set, token authentication, dispatch and
 * JSON-schema generation - so the front-ends stay thin and cannot drift from each other.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class facade {
    /** @var string Web service function name prefix for this component. */
    const PREFIX = 'mod_minilesson_';

    /** @var string The external service shortname whose functions the facade brokers. */
    const SERVICE_SHORTNAME = 'aigenservice';

    /**
     * The enabled aigenservice external service record, or null if unavailable.
     *
     * @return \stdClass|null
     */
    public static function get_service() {
        global $DB;
        $service = $DB->get_record('external_services', [
            'shortname' => self::SERVICE_SHORTNAME,
            'component' => constants::M_COMPONENT,
            'enabled' => 1,
        ]);
        return $service ?: null;
    }

    /**
     * The web service function names the facade brokers (the aigenservice functions).
     *
     * @return string[]
     */
    public static function function_names(): array {
        global $DB;
        $service = self::get_service();
        if (!$service) {
            return [];
        }
        return $DB->get_fieldset_select(
            'external_services_functions',
            'functionname',
            'externalserviceid = ?',
            [$service->id]
        );
    }

    /**
     * Strip the component prefix from a function name to get its facade short name.
     *
     * @param string $functionname
     * @return string
     */
    public static function short_name(string $functionname): string {
        if (strpos($functionname, self::PREFIX) === 0) {
            return substr($functionname, strlen(self::PREFIX));
        }
        return $functionname;
    }

    /**
     * Re-add the component prefix to a facade short name to get the full function name.
     *
     * @param string $shortname
     * @return string
     */
    public static function full_name(string $shortname): string {
        return self::PREFIX . $shortname;
    }

    /**
     * Authenticate a web service token using core's routine (validates the token, sets up
     * the user session, enforces the service's access rules). Throws on failure.
     *
     * @param string $token
     * @return array core webservice authinfo: ['user' => ..., 'token' => ..., 'service' => ...]
     */
    public static function authenticate(string $token): array {
        global $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');
        $webservice = new \webservice();
        return $webservice->authenticate_user($token);
    }

    /**
     * Get (or mint) a real aigenservice web service token for a user, for the OAuth
     * authorization server to hand out as its access token.
     *
     * Deliberately does not use external_generate_token_for_current_user(): that helper
     * excludes site admins (via !is_siteadmin()) and additionally requires the
     * moodle/webservice:createtoken capability - both wrong extra gates here, since the
     * one and only gate this flow should enforce is mod/minilesson:usemcp, which
     * external_generate_token() already re-checks internally (it throws
     * nocapabilitytousethisservice if the user lacks it).
     *
     * @param int $userid
     * @return string the token string
     * @throws \moodle_exception if the aigenservice cannot be found, or the user lacks
     *         mod/minilesson:usemcp (bubbles up as nocapabilitytousethisservice)
     */
    public static function mint_or_reuse_token(int $userid): string {
        global $DB, $CFG;

        $service = self::get_service();
        if (!$service) {
            throw new \moodle_exception('cannotfindwebservice', 'webservice', '', self::SERVICE_SHORTNAME);
        }

        $existing = $DB->get_records(
            'external_tokens',
            ['userid' => $userid, 'externalserviceid' => $service->id, 'tokentype' => EXTERNAL_TOKEN_PERMANENT, 'sid' => null],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $token = reset($existing);
        if ($token && (empty($token->validuntil) || $token->validuntil > time())) {
            return $token->token;
        }

        require_once($CFG->libdir . '/externallib.php');
        $validuntil = empty($CFG->tokenduration) ? 0 : (time() + $CFG->tokenduration);
        return external_generate_token(
            EXTERNAL_TOKEN_PERMANENT,
            $service,
            $userid,
            \context_system::instance(),
            $validuntil
        );
    }

    /**
     * revokecallback for local_oauthmcp: drop the aigenservice tokens mint_or_reuse_token()
     * hands out, so a revoked OAuth grant (refresh-token reuse/theft, capability withdrawn,
     * client deleted, privacy delete) does not leave a usable web service token behind until
     * it expires on its own. Best-effort and idempotent - local_oauthmcp only calls this
     * once the user has no other live grant for this resource.
     *
     * @param int $userid
     * @return void
     */
    public static function revoke_tokens(int $userid): void {
        global $DB;

        $service = self::get_service();
        if (!$service) {
            return;
        }
        $DB->delete_records('external_tokens', [
            'userid' => $userid,
            'externalserviceid' => $service->id,
            'tokentype' => EXTERNAL_TOKEN_PERMANENT,
            'sid' => null,
        ]);
    }

    /**
     * Whether the authenticated token's own service grants a given function.
     *
     * @param array $authinfo authinfo returned by authenticate()
     * @param string $functionname
     * @return bool
     */
    public static function token_allows(array $authinfo, string $functionname): bool {
        global $DB;
        return $DB->record_exists(
            'external_services_functions',
            ['externalserviceid' => $authinfo['service']->id, 'functionname' => $functionname]
        );
    }

    /**
     * Dispatch a call through core, which validates params, runs the function's own
     * capability checks and cleans the return value.
     *
     * @param string $functionname
     * @param array $args
     * @return array core call_external_function result: ['error' => bool, 'data'|'exception' => ...]
     */
    public static function call(string $functionname, array $args): array {
        return external_api::call_external_function($functionname, $args);
    }

    /**
     * Metadata for every brokered function, keyed by full function name.
     *
     * @return array functionname => external_function_info
     */
    public static function functions_info(): array {
        $infos = [];
        foreach (self::function_names() as $name) {
            $infos[$name] = external_api::external_function_info($name);
        }
        return $infos;
    }

    /**
     * Extract the token from the request: X-API-Key header (recommended - passes cleanly
     * through Apache), Authorization: Bearer, then a ?token= query param (testing only).
     *
     * @return string the token, or '' if none supplied
     */
    public static function request_token(): string {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim($_SERVER['HTTP_X_API_KEY']);
        }
        $header = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } else if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if ($header !== '' && preg_match('/^\s*Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return isset($_GET['token']) ? trim($_GET['token']) : '';
    }

    /**
     * Map a Moodle PARAM_* type to a JSON-schema type.
     *
     * @param mixed $paramtype
     * @return string
     */
    public static function map_openapi_type($paramtype): string {
        switch ($paramtype) {
            case PARAM_INT:
                return 'integer';
            case PARAM_BOOL:
                return 'boolean';
            case PARAM_FLOAT:
                return 'number';
            default:
                return 'string';
        }
    }

    /**
     * Build a JSON schema (nested object/array) from a Moodle external description structure.
     * The facade takes a plain JSON body/args, so parameters map to natural nested JSON.
     *
     * @param mixed $structure external_single_structure|external_multiple_structure|external_value
     * @return array
     */
    public static function build_schema_from_structure($structure): array {
        if ($structure instanceof external_single_structure) {
            $properties = [];
            $required = [];
            foreach ($structure->keys as $key => $child) {
                $properties[$key] = self::build_schema_from_structure($child);
                if (isset($child->required) && !in_array($child->required, [VALUE_OPTIONAL, VALUE_DEFAULT])) {
                    $required[] = $key;
                }
            }
            $schema = ['type' => 'object', 'properties' => $properties];
            if (!empty($required)) {
                $schema['required'] = $required;
            }
            return $schema;
        }

        if ($structure instanceof external_multiple_structure) {
            return [
                'type' => 'array',
                'items' => self::build_schema_from_structure($structure->content),
            ];
        }

        $schema = ['type' => self::map_openapi_type($structure->type ?? PARAM_TEXT)];
        if (!empty($structure->desc)) {
            $schema['description'] = $structure->desc;
        }
        return $schema;
    }

    /**
     * The JSON schema for a function's arguments, always an object (empty properties when
     * the function takes none), suitable for an OpenAPI requestBody or an MCP tool inputSchema.
     *
     * @param \stdClass $functioninfo external_function_info result
     * @return array
     */
    public static function input_schema($functioninfo): array {
        if (!empty($functioninfo->parameters_desc->keys)) {
            return self::build_schema_from_structure($functioninfo->parameters_desc);
        }
        return ['type' => 'object', 'properties' => new \stdClass()];
    }
}
