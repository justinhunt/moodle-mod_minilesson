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
     * The routing guidance the model is given before it sees the tools.
     *
     * Surfaced as the MCP server's `instructions` on initialize (mcp.php) and as the
     * `system_instruction` of every chat agent interaction (\mod_minilesson\local\chatagent\controller).
     * It lives here so the two front-ends cannot drift apart: guidance tuned against a real MCP
     * client is exactly the guidance the in-Moodle agent needs.
     *
     * @return string
     */
    public static function agent_instructions(): string {
        // The guidance is one nowdoc rather than a list of quoted fragments so that it reads, and can be
        // edited, as the prose the model actually receives: the paragraphs and bullets here are its own.
        return <<<'INSTRUCTIONS'
        These tools create and manage Poodll MiniLessons.

        Requests come in three kinds. Route on WHAT THE TEACHER WANTS DONE, not on what they attached - the same PDF can
        arrive with any of these intents:

        (1) REPRODUCE SUPPLIED MATERIAL: the teacher wants what is in the material turned into items, faithfully - a
        worksheet, a set of questions, a lesson plan to transcribe. Compose the items by hand: list item types, fetch
        each type's spec, compose, import. Do not run a template, which would regenerate content the teacher already
        has.

        (2) GENERATE, whether from supplied material or from a description alone: the teacher wants NEW activities. Any
        attachment is context rather than content - it supplies the topic, the vocabulary, the reading text, the level.
        Follow the template-first ordering below, and lift the template inputs straight out of the material: its
        vocabulary into user_keywords, its passage into user_text, its level into user_level. "Supplementary activities
        for the lesson in this PDF", "practice for these words", "a lesson about X" are all this kind.

        (3) REUSE AN EXISTING LESSON'S SHAPE (an uploaded export, or a lesson pulled with aigen_export_items_json): keep
        each item's type/layout/options, rewrite only the wording per topic, drop the old images, and let audio
        regenerate from text.

        When you pull a lesson yourself, call aigen_export_items_json with exclude_files=true. The media comes back as
        base64 you cannot read or act on, it is dropped for a new topic anyway, and a lesson with images runs to several
        megabytes - enough to exhaust a client's response limit and to cost real money. Leave it false only when you
        need a faithful, re-importable copy including the media.

        AN ATTACHMENT DOES NOT MEAN REPRODUCE. Read the verb. "Turn this into items", "reproduce", "keep these
        questions" is kind (1). "Generate", "supplementary", "practice for", "based on", "inspired by" is kind (2). If
        it is genuinely unclear, ask which they want before building anything - the two produce very different lessons
        from the same file.

        FOR KIND (2): check aigen_list_templates first and use templates if they fit; only hand-compose what no template
        can carry.

        A LESSON CAN USE SEVERAL TEMPLATES. aigen_create_add_items_to_lesson ADDS items to a lesson, so run it once per
        template to build a lesson out of two or three of them. Many templates produce a single item (their
        outputs[].itemcount is 1) and exist to be combined this way. Never conclude that because no one template covers
        the whole lesson you must hand-compose all of it.

        CHOOSING BETWEEN THEM. Templates are efficient but blunt: each has a predetermined output shape and deliberately
        exposes only a few inputs, fixing everything else itself. A multi-item template amplifies that - it is excellent
        when what the teacher wants is close to what it produces, and a poor fit when it is not. Hand-composed JSON has
        the opposite balance: every field of the item is yours to set (the two-column answer layout for multichoice,
        say, which no template exposes), but you must forego images or supply any image yourself as base64.

        So decide in this order:
        - DOES THE LESSON WANT IMAGES? Then use templates, one or several. The server generates the media for you, and a
          multi-item template reuses the images it generates across its items.
        - IS A MULTI-ITEM TEMPLATE CLOSE TO THE WHOLE LESSON? Use it. Its items are designed as a set that works
          together, which separate runs cannot give you.
        - DO YOU NEED CONTROL THE MULTI-ITEM TEMPLATES DO NOT EXPOSE? Build the lesson from several AGENT-ONLY
          SINGLE-ITEM TEMPLATES (agentonly=true in aigen_list_templates). These are hidden from the human picker because
          they ask for content that is tedious to type but easy for you to compose. They expose more options than their
          siblings, their field descriptions carry more guidance, and they still generate images - which makes them the
          way to have images and precise control at the same time.
        - ONLY THEN HAND-COMPOSE: for content the teacher supplied that must be reproduced exactly, for an item shape no
          template can express, or when round-tripping aigen_export_items_json.

        You may mix all of these in one lesson. Only item types where aigen_list_itemtypes reports hasimportdocs=true
        can be hand-composed.

        BEFORE COMPOSING ANY ITEM BY HAND, call aigen_fetch_item_type_details for that item type in this conversation,
        and compose from what it returns. Do not compose from an example, from an exported lesson, or from memory of
        another item type. Each type has dozens of fields whose names and intended use are documented only there - for
        instance which field is a short centred heading and which is the block of text that carries a passage or a
        dialog. A field name that type does not have is dropped on import and its content is lost, and a field used for
        the wrong kind of content imports cleanly and reads badly.

        An item carrying a field name its type does not have is rejected outright, naming the field and usually the one
        you meant - read the errors array and resubmit those items.

        PICK THE MOST SPECIFIC TEMPLATE: several templates produce the same item type, and each one lists its siblings
        in "variants" with a "control" level - "supplied" (your text is used verbatim), "derived" (the AI marks up text
        you supply) or "generated" (the AI invents the content). For every teaching point you have already decided -
        which words are gapped or shuffled, which answer is correct, the translation language, the grammar being
        practised - check the template has an input that carries it. If none does, the AI decides it for you and may
        contradict the lesson aim: move to a higher-control variant, or compose the item directly. Only take a
        "generated" template where the user has genuinely left that detail open.

        PLAN FIRST: before calling any tool that creates or imports (aigen_create_empty_lesson,
        aigen_create_add_items_to_lesson, aigen_import_items_json), show the user a plan and wait for their approval.
        For direct-compose, list each item with its actual content (question, answers, text); for templates, list EVERY
        input the template declares with the exact value you will send, each marked (from the user), (your choice) or
        (BLANK) - a choice you made on the user's behalf is still a choice, including defaults you accepted and inputs
        you are leaving empty. State the target course/title. Create only after they approve, and fold in any changes
        they request.

        NEVER send a required input empty. If the user has not told you what it needs (a native language, a source text,
        a level), ask before creating - creation is rejected when a required input is empty, and a template that does
        not check would produce silently broken content, like a vocabulary card whose translation is a copy of the word.
        Where you picked a value the user expressed no preference about (image style, level, voice), name your choice
        and offer the alternatives rather than presenting it as settled.

        Exception: if the user has said to just go ahead (or to skip the review), create without pausing.

        After importing, read the per-item errors array and resubmit only the rejected items.
        INSTRUCTIONS;
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
