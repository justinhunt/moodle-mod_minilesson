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

/**
 * OpenAPI Specs generation
 *
 * @package mod_minilesson
 *
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_minilesson\constants;
use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);
define('READ_ONLY_SESSION', true);

require(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->dirroot . '/webservice/lib.php');

header('Content-Type: application/json; charset=utf-8');

function map_openapi_type($paramtype): string {
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

function build_schema_from_structure($structure, &$schemas, $name = '') {

    if ($structure instanceof external_single_structure) {

        $properties = [];

        foreach ($structure->keys as $key => $child) {
            $properties[$key] = build_schema_from_structure(
                $child,
                $schemas,
                ucfirst($key)
            );
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    if ($structure instanceof external_multiple_structure) {

        return [
            'type' => 'array',
            'items' => build_schema_from_structure(
                $structure->content,
                $schemas,
                $name . 'Item',
            ),
        ];
    }

    return [
        'type' => map_openapi_type($structure->type ?? PARAM_TEXT),
        'description' => $structure->desc ?? '',
    ];
}

function get_login_token_details() {
    return [
        'post' => [
            'summary' => 'Get Moodle web service token',
            'description' => 'Get Moodle web service token',
            'operationId' => 'get_moodle_web_service_token',
            'security' => [],
            'requestBody' => [
                'required' => true,
                'content' => [
                    'application/x-www-form-urlencoded' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'username' => [
                                    'type' => 'string',
                                    'description' => 'Moodle username',
                                ],
                                'password' => [
                                    'type' => 'string',
                                    'description' => 'Moodle password',
                                ],
                                'service' => [
                                    'type' => 'string',
                                    'default' => 'aigenservice',
                                    'description' => 'The Moodle web service name',
                                ],
                            ],
                            'required' => ['username', 'password', 'service'],
                        ],
                    ],
                ],
            ],
            'responses' => [
                '200' => [
                    'description' => 'Successful response (returns token or error details)',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'token' => [
                                        'type' => 'string',
                                        'description' => 'The web service token to pass as wstoken on subsequent calls',
                                    ],
                                    'privatetoken' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                    'error' => [
                                        'type' => 'string',
                                    ],
                                    'errorcode' => [
                                        'type' => 'string',
                                    ],
                                    'stacktrace' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                    'debuginfo' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                    'reproductionlink' => [
                                        'type' => 'string',
                                        'nullable' => true,
                                    ],
                                ],
                                'additionalProperties' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}


// This is the procedural part where the code actually commences running
$openapi = [
    'openapi' => '3.1.0',

    'info' => [
        'title' => 'Moodle LMS',
        'version' => '1.0.0',
        'description' => 'AI Generation APIs',
    ],

    'servers' => [
        [
            'url' => $CFG->wwwroot . '/webservice/rest/server.php',
            'description' => 'Moodle REST API',
        ],
    ],

    'paths' => [],

    'components' => [
        'securitySchemes' => [
            'api_key' => [
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'wstoken',
            ],
        ],

        'parameters' => [
            'WSRestFormat' => [
                'name' => 'moodlewsrestformat',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'default' => 'json',
                ],
            ],
        ],

        'schemas' => [],
    ],
];

$service = $DB->get_record('external_services', [
    'shortname' => 'aigenservice',
    'component' => constants::M_COMPONENT,
    'enabled' => 1,
], '*', MUST_EXIST);

$webservicemanager = new webservice();

$functions = $webservicemanager->get_external_functions([$service->id]);

foreach ($functions as $function) {

    $functioninfo = external_api::external_function_info($function->name);

    // The path below is a bit of a hack and is not strictly valid, but claude and agents get it
    // gpt will try to add it to the server url and fail though. so gpt wont work here
    $path = '/' . $function->name;

    $method = 'post';
    if ($functioninfo->type == 'read'
        && $function->name !== 'mod_minilesson_aigen_fetch_create_items_status') {
        $method = 'get';
    }

    $parameters = [
        [
            'name' => 'wsfunction',
            'in' => 'query',
            'required' => true,
            'schema' => [
                'type' => 'string',
                'default' => $function->name,
            ],
        ],
        [
            'name' => 'wstoken',
            'in' => 'query',
            'required' => true,
            'schema' => [
                'type' => 'string',
            ],
            'description' => 'Moodle web service token to auth requests',
        ],
        [
            'name' => 'moodlewsrestformat',
            'in' => 'query',
            'required' => false,
            'schema' => [
                'type' => 'string',
                'default' => 'json',
            ],
        ],
    ];

    $requestbodyproperties = [];
    $requestbodyrequired = [];

    if (!empty($functioninfo->parameters_desc->keys)) {

        foreach ($functioninfo->parameters_desc->keys as $key => $param) {

            $required = !in_array(
                $param->required,
                [VALUE_OPTIONAL, VALUE_DEFAULT]
            );

            $schema = build_schema_from_structure(
                $param,
                $openapi['components']['schemas'],
                ucfirst($key)
            );

            if ($param instanceof external_single_structure) {

                foreach ($param->keys as $subkey => $subparam) {

                    $subrequired = !in_array(
                        $subparam->required,
                        [VALUE_OPTIONAL, VALUE_DEFAULT]
                    );

                    $requestbodyproperties[$key . '[' . $subkey . ']'] = [
                        'type' => map_openapi_type(
                            $subparam->type ?? PARAM_TEXT
                        ),
                        'description' => $subparam->desc ?? '',
                    ];

                    if ($subrequired) {
                        $requestbodyrequired[] =
                            $key . '[' . $subkey . ']';
                    }
                }
            } else if ($param instanceof external_multiple_structure) {

                if ($param->content instanceof external_value) {
                    $requestbodyproperties[$key . '[]'] = [
                        'type' => 'array',
                        'items' => [
                            'type' => map_openapi_type(
                                $param->content->type ?? PARAM_TEXT
                            ),
                        ],
                        'description' => $param->desc ?? '',
                    ];

                } else {

                    $requestbodyproperties[$key . '[]'] = [
                        'type' => 'array',
                        'items' => build_schema_from_structure(
                            $param->content,
                            $openapi['components']['schemas'],
                            ucfirst($key)
                        ),
                        'description' => $param->desc ?? '',
                    ];
                }

                $requestbodyencoding[$key. '[]'] = [
                    'style' => 'form',
                    'explode' => true,
                ];

                if ($required) {
                    $requestbodyrequired[] = $key . '[]';
                }
            } else {

                $parameters[] = [
                    'name' => $key,
                    'in' => 'query',
                    'required' => $required,
                    'schema' => $schema,
                    'description' => $param->desc ?? '',
                ];
            }
        }
    }

    $schemaname = ucfirst(
        str_replace('mod_minilesson_', '', $function->name)
    ) . 'Response';

    $responseschema = build_schema_from_structure(
        $functioninfo->returns_desc,
        $openapi['components']['schemas'],
        $schemaname
    );

    $openapi['components']['schemas'][$schemaname] = $responseschema;

    $operation = [
        'summary' => $functioninfo->description,
        'description' => $functioninfo->description,
        'operationId' => $functioninfo->name,
        'parameters' => $parameters,
        'security' => [
            ['api_key' => []],
        ],
        'responses' => [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaname,
                        ],
                    ],
                ],
            ],
        ],
    ];

    if (!empty($requestbodyproperties)) {

        $requestbodyschema = [
            'type' => 'object',
            'properties' => $requestbodyproperties,
        ];

        if (!empty($requestbodyrequired)) {
            $requestbodyschema['required'] = $requestbodyrequired;
        }

        $operation['requestBody'] = [
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => [
                    'schema' => $requestbodyschema,
                ],
            ],
        ];

        if (!empty($requestbodyencoding)) {
            $operation['requestBody']['content']['application/x-www-form-urlencoded']['encoding'] = $requestbodyencoding;
        }
    }

    $openapi['paths'][$path] = [
        $method => $operation,
    ];
}

// The login token path is a bit special (its not strictly speaking a web service but it is documented here)
$openapi['paths']['/login/token.php'] = get_login_token_details();

$agentinstructions = <<<JSON
{
    "x-agent-instructions": {
        "title": "Agent Workflow for Moodle AI Minilesson Generation APIs",
        "authentication": {
            "step_1_obtain_token": "POST to {MOODLE_URL}/login/token.php with username, password, and service name 'aigenservice'",
            "step_2_use_token": "Include wstoken parameter in all subsequent API calls",
            "token_storage": "Store token securely using encrypted storage or environment variables",
            "token_endpoint_response": {
                "success": {
                    "token": "string - the web service token to pass as wstoken on subsequent calls",
                    "privatetoken": "string or null - secondary token used by some Moodle flows; usually ignored by API clients"
                },
                "error": {
                    "error": "string - human-readable error message",
                    "errorcode": "string - machine-readable code, e.g. 'invalidlogin', 'enablewsdescription'",
                    "stacktrace": "string or null",
                    "debuginfo": "string or null",
                    "reproductionlink": "string or null"
                },
                "note": "HTTP status is 200 in both cases; distinguish success from failure by presence of 'token' vs 'errorcode'."
            }
        },
        "choosing_your_approach": {
            "summary": "There are two ways to create items in a lesson: (A) the template workflow (typical_workflow), where you pick a template and the server generates the content with AI; and (B) the direct-compose workflow (direct_compose_workflow), where you author the item JSON yourself and import it. Decide per lesson, and read this section before starting either workflow. You may also combine both in one lesson - see hybrid_pattern.",
            "prefer_templates_when": [
                "A template returned by list_templates matches the request - check its description, skills and outputs before deciding no template fits",
                "You want the content (text, questions, and any images) generated for you rather than authoring it yourself",
                "The lesson needs generated images or audio: templates can produce media server-side (see each template's outputs.imagecount), whereas direct compose requires you to supply every media file yourself as base64",
                "You just need a standard, well-formed lesson quickly and do not need precise control over each item"
            ],
            "prefer_direct_compose_when": [
                "The user supplied exact content (a specific reading passage, vocabulary list, or dialog) that you must reproduce faithfully rather than regenerate",
                "You need a specific sequence or mix of item types that no single template produces",
                "You are converting an existing lesson plan, or round-tripping items from aigen_export_items_json (export, edit, re-import)",
                "The user should review or adjust the proposed items before they are created",
                "You want per-item error feedback to iterate: import_items_json returns an errors array you can act on and resubmit, whereas a template job is fire-and-forget plus status polling"
            ],
            "constraints": [
                "Direct compose is only available for item types where list_itemtypes reports hasimportdocs=true; use a template for any other type",
                "When both a template and direct compose would work, prefer the template unless the request needs the fidelity or item-by-item control that direct compose gives"
            ]
        },
        "typical_workflow": [
            {
                "step": 1,
                "name": "List Item Types",
                "call": "GET /mod_minilesson_aigen_list_itemtypes",
                "purpose": "Discover the available item types and their descriptions to understand what each template can produce and to choose the right template for the user's request. Also read choosing_your_approach to decide between the template and direct-compose workflows",
                "required_params": ["wstoken"]
            },
            {
                "step": 2,
                "name": "List Templates",
                "call": "GET /mod_minilesson_aigen_list_templates",
                "purpose": "Discover available AI generation templates",
                "required_params": ["wstoken"]
            },
            {
                "step": 3,
                "name": "List Minilessons",
                "call": "GET /mod_minilesson_aigen_list_minilessons",
                "purpose": "See existing lessons in course (optional)",
                "required_params": ["wstoken", "courseid"]
            },
            {
                "step": 4,
                "name": "Create Empty Lesson",
                "call": "POST /mod_minilesson_aigen_create_empty_lesson",
                "purpose": "Create empty lesson container",
                "returns": "cmid (course module ID)",
                "required_params": ["wstoken", "courseid", "title"]
            },
            {
                "step": 5,
                "name": "Create and Add Items",
                "call": "POST /mod_minilesson_aigen_create_add_items_to_lesson",
                "purpose": "Generate AI content and add to lesson",
                "returns": "jobid (for tracking)",
                "required_params": ["wstoken", "cmid", "templateid", "contextdata"]
            },
            {
                "step": 6,
                "name": "Check Status",
                "call": "POST /mod_minilesson_aigen_fetch_create_items_status",
                "purpose": "Poll job completion status",
                "required_params": ["wstoken", "jobids[]"],
                "polling": "Repeat every 2-5 seconds until status != 'Progress'"
            }
        ],
        "direct_compose_workflow": {
            "purpose": "Alternative to the template based typical_workflow: compose the lesson item JSON yourself and import it. Use this when you (the agent) are designing the lesson content - e.g. converting an existing lesson plan, or when the user should review/adjust the proposed items before they are created. Only item types with hasimportdocs=true can be composed this way; use templates for the rest.",
            "steps": [
                {
                    "step": 1,
                    "name": "List Item Types",
                    "call": "GET /mod_minilesson_aigen_list_itemtypes",
                    "purpose": "Choose item types matching the lesson design: read each type's description, usage and skills, and check hasimportdocs",
                    "required_params": ["wstoken"]
                },
                {
                    "step": 2,
                    "name": "Fetch Item Type Details",
                    "call": "GET /mod_minilesson_aigen_fetch_item_type_details",
                    "purpose": "For each chosen item type, fetch the import field spec: field names, types, allowed values and their meanings, file areas, and a complete example payload (examplejson - parse it). Compose your items strictly from the documented fields",
                    "required_params": ["wstoken", "itemtype"]
                },
                {
                    "step": 3,
                    "name": "Create Empty Lesson",
                    "call": "POST /mod_minilesson_aigen_create_empty_lesson",
                    "purpose": "Create the lesson container",
                    "returns": "cmid (course module ID)",
                    "required_params": ["wstoken", "courseid", "title"]
                },
                {
                    "step": 4,
                    "name": "Import Items",
                    "call": "POST /mod_minilesson_aigen_import_items_json",
                    "purpose": "Submit the composed items as one payload: {\\"items\\": [...], \\"files\\": {...}}. Inspect the per-item errors array in the response, fix the rejected items and resubmit only those (imported items must not be resubmitted)",
                    "required_params": ["wstoken", "cmid", "itemsjson"]
                }
            ]
        },
        "hybrid_pattern": {
            "purpose": "Both workflows add items to the same lesson, identified by its cmid, so one lesson can mix both. Generate the bulk of the lesson with one or more templates, then append bespoke items you compose yourself (or vice versa).",
            "how": [
                "Create the lesson once with create_empty_lesson to get a cmid",
                "Run the template workflow against that cmid (create_add_items_to_lesson, then poll status until done)",
                "Then call import_items_json with the same cmid to append your directly-composed items",
                "Each call appends its items after the items already in the lesson, so order your calls to get the item order you want"
            ]
        },
        "base_lesson_replication": {
            "when": "Only when the user gives you an exported lesson (the itemsjson from aigen_export_items_json) and asks for more lessons like it on other topics. Ignore this section otherwise.",
            "purpose": "Reuse the exported payload as a ready-made template and produce one new lesson per topic by changing only the content.",
            "how": [
                "Treat the exported itemsjson as a known-good example. Keep each item's type, layout, options and other settings exactly as they are, and rewrite only the wording (text, tts, questions, answers, sentences) for the new topic.",
                "Do not supply audio files: spoken audio is regenerated from the text on import, so new wording produces new audio automatically.",
                "Uploaded images will not match the new topic: any base64 files in the export are the original topic's images and cannot be regenerated. Drop them, keep them only if still suitable, or ask the user to provide new images.",
                "For each topic, call create_empty_lesson to get a new cmid, then import_items_json with that cmid. Read the errors array and fix any rejected item (for example a correct-answer index or a gap marker that no longer matches the rewritten content) before moving on."
            ]
        }
    }
}
JSON;

$openapi = array_merge(
    $openapi,
    json_decode($agentinstructions, true)
);

echo json_encode(
    $openapi,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
