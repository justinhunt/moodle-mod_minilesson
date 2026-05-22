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
            'properties' => $properties
        ];
    }

    if ($structure instanceof external_multiple_structure) {

        return [
            'type' => 'array',
            'items' => build_schema_from_structure(
                $structure->content,
                $schemas,
                $name . 'Item'
            )
        ];
    }

    return [
        'type' => map_openapi_type($structure->type ?? PARAM_TEXT),
        'description' => $structure->desc ?? ''
    ];
}

$openapi = [
    'openapi' => '3.1.0',

    'info' => [
        'title' => 'Moodle LMS',
        'version' => '1.0.0',
        'description' => 'AI Generation APIs'
    ],

    'servers' => [
        [
            'url' => $CFG->wwwroot . '/webservice/rest/server.php',
            'description' => 'Moodle REST API'
        ]
    ],

    'security' => [
        [
            'api_key' => []
        ]
    ],

    'paths' => [],

    'components' => [
        'securitySchemes' => [
            'api_key' => [
                'type' => 'apiKey',
                'in' => 'query',
                'name' => 'wstoken'
            ]
        ],

        'parameters' => [
            'WSRestFormat' => [
                'name' => 'moodlewsrestformat',
                'in' => 'query',
                'required' => false,
                'schema' => [
                    'type' => 'string',
                    'default' => 'json'
                ]
            ]
        ],

        'schemas' => []
    ]
];

$service = $DB->get_record('external_services', [
    'shortname' => 'aigenservice',
    'component' => constants::M_COMPONENT,
    'enabled' => 1
], '*', MUST_EXIST);

$webservicemanager = new webservice();

$functions = $webservicemanager->get_external_functions([$service->id]);

foreach ($functions as $function) {

    $functioninfo = external_api::external_function_info($function->name);

    $path = '/' . $function->name;

    $method = 'post';
    if ($functioninfo->type == 'read') {
        $method = 'get';
    }

    $parameters = [
        [
            'name' => 'wsfunction',
            'in' => 'query',
            'required' => true,
            'schema' => [
                'type' => 'string',
                'default' => $function->name
            ]
        ],
        [
            '$ref' => '#/components/parameters/WSRestFormat'
        ]
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
                        'description' => $subparam->desc ?? ''
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
                            )
                        ],
                        'description' => $param->desc ?? ''
                    ];

                } else {

                    $requestbodyproperties[$key . '[]'] = [
                        'type' => 'array',
                        'items' => build_schema_from_structure(
                            $param->content,
                            $openapi['components']['schemas'],
                            ucfirst($key)
                        ),
                        'description' => $param->desc ?? ''
                    ];
                }

                $requestbodyencoding[$key. '[]'] = [
                    'style' => "form",
                    'explode' => true
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
                    'description' => $param->desc ?? ''
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
        'parameters' => $parameters,

        'responses' => [
            '200' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/' . $schemaname
                        ]
                    ]
                ]
            ]
        ]
    ];

    if (!empty($requestbodyproperties)) {

        $requestbodyschema = [
            'type' => 'object',
            'properties' => $requestbodyproperties
        ];

        if (!empty($requestbodyrequired)) {
            $requestbodyschema['required'] = $requestbodyrequired;
        }

        $operation['requestBody'] = [
            'required' => true,
            'content' => [
                'application/x-www-form-urlencoded' => [
                    'schema' => $requestbodyschema,
                ]
            ]
        ];

        if (!empty($requestbodyencoding)) {
            $operation['requestBody']['content']['application/x-www-form-urlencoded']['encoding'] = $requestbodyencoding;
        }
    }



    $openapi['paths'][$path] = [
        $method => $operation
    ];
}

$agentinstructions = <<<JSON
{
"x-agent-instructions": {
        "title": "Agent Workflow for Moodle AI Minilesson Generation APIs",
        "authentication": {
            "step_1_obtain_token": "POST to {MOODLE_URL}/login/token.php with username, password, and service name 'aigenservice'",
            "step_2_use_token": "Include wstoken parameter in all subsequent API calls",
            "token_storage": "Store token securely using encrypted storage or environment variables"
        },
        "typical_workflow": [
            {
                "step": 1,
                "name": "List Templates",
                "call": "GET /mod_minilesson_aigen_list_templates",
                "purpose": "Discover available AI generation templates",
                "required_params": ["wstoken"]
            },
            {
                "step": 2,
                "name": "List Minilessons",
                "call": "GET /mod_minilesson_aigen_list_minilessons",
                "purpose": "See existing lessons in course (optional)",
                "required_params": ["wstoken", "courseid"]
            },
            {
                "step": 3,
                "name": "Create Empty Lesson",
                "call": "POST /mod_minilesson_aigen_create_empty_lesson",
                "purpose": "Create empty lesson container",
                "returns": "cmid (course module ID)",
                "required_params": ["wstoken", "courseid", "title"]
            },
            {
                "step": 4,
                "name": "Create and Add Items",
                "call": "POST /mod_minilesson_aigen_create_add_items_to_lesson",
                "purpose": "Generate AI content and add to lesson",
                "returns": "jobid (for tracking)",
                "required_params": ["wstoken", "cmid", "templateid", "contextdata"]
            },
            {
                "step": 5,
                "name": "Check Status",
                "call": "GET /mod_minilesson_aigen_fetch_create_items_status",
                "purpose": "Poll job completion status",
                "required_params": ["wstoken", "jobids[]"],
                "polling": "Repeat every 2-5 seconds until status != 'processing'"
            }
        ]
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
