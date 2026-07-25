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
 * OpenAPI spec generation for the clean aigen REST facade.
 *
 * Emits an OpenAPI 3.1 document describing the mod_minilesson aigen functions as they are
 * exposed by the clean REST facade (aigen_rest.php): one POST operation per function, at
 * /<function-without-mod_minilesson-prefix>, taking the function's arguments as a JSON body
 * and authenticated with an X-API-Key header. Consumable by standard REST clients (ChatGPT
 * custom GPT Actions, MCP servers), unlike Moodle's native server.php shape.
 *
 * The operation set, naming and schemas all come from \mod_minilesson\local\aigen\facade,
 * which is shared with the aigen_rest.php and mcp.php front-ends, so they cannot drift.
 *
 * @package mod_minilesson
 *
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_minilesson\local\aigen\facade;

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);
define('READ_ONLY_SESSION', true);

require(dirname(__FILE__, 3) . '/config.php');

header('Content-Type: application/json; charset=utf-8');

// The clean facade base URL: the dispatcher script, which appends /<function>.
$facadebase = $CFG->wwwroot . '/mod/minilesson/aigen_rest.php';

$openapi = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'Poodll MiniLesson aigen',
        'version' => '1.0.0',
        'description' => 'AI generation and item import/export APIs for mod_minilesson, exposed as a clean REST '
            . 'facade. Authenticate with your Moodle web service token in the X-API-Key header. Each operation is '
            . 'POST /<function> with the function arguments as a JSON body.',
    ],
    'servers' => [
        [
            'url' => $facadebase,
            'description' => 'MiniLesson aigen REST facade',
        ],
    ],
    'paths' => [],
    'components' => [
        'securitySchemes' => [
            'ApiKeyAuth' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'A Moodle web service token for the aigenservice.',
            ],
        ],
        'schemas' => [],
    ],
    'security' => [
        ['ApiKeyAuth' => []],
    ],
];

foreach (facade::functions_info() as $name => $functioninfo) {
    // Clean path: the function name without the component prefix. The dispatcher re-adds it.
    $path = '/' . facade::short_name($name);

    $operation = [
        'summary' => $functioninfo->description,
        'description' => $functioninfo->description,
        'operationId' => $name,
        'responses' => [
            '200' => [
                'description' => 'Successful response.',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => $functioninfo->returns_desc !== null
                                    ? facade::build_schema_from_structure($functioninfo->returns_desc)
                                    : ['description' => 'The function result.'],
                            ],
                        ],
                    ],
                ],
            ],
            'default' => [
                'description' => 'Error response.',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'boolean'],
                                'message' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    // Request body: the function's arguments as a JSON object (only when it takes any).
    if (!empty($functioninfo->parameters_desc->keys)) {
        $bodyschema = facade::build_schema_from_structure($functioninfo->parameters_desc);
        $operation['requestBody'] = [
            'required' => !empty($bodyschema['required']),
            'content' => [
                'application/json' => [
                    'schema' => $bodyschema,
                ],
            ],
        ];
    }

    $openapi['paths'][$path] = ['post' => $operation];
}

// Agent guidance. Standard OpenAPI x- extension, ignored by strict validators but read by agents.
// phpcs:disable moodle.Files.LineLength -- Embedded agent-guidance JSON is kept as readable long lines.
$agentinstructions = <<<JSON
{
    "x-agent-instructions": {
        "title": "Agent workflow for the Poodll MiniLesson aigen APIs",
        "entry_points": {
            "summary": "User requests arrive as one of three kinds. Identify which, then follow the referenced workflow.",
            "source_material": "The user uploads a PDF/doc/image or pastes a lesson plan and wants a lesson made from it. Reproduce it faithfully via direct_compose_workflow (OCR images/PDFs yourself first).",
            "exported_lesson_as_template": "The user uploads an exported MiniLesson (itemsjson), or asks to base new lessons on an existing one, to reuse as a template for other topics. Follow base_lesson_replication. To reuse a lesson already on the site, pull it first with aigen_export_items_json and set exclude_files=true (the original images are dropped for a new topic anyway, and it keeps the response small).",
            "described_lesson": "The user only describes the lesson they want (topic, skills, theme). Prefer typical_workflow: check list_templates first and use a template if one fits (templates can generate media server-side); fall back to direct_compose_workflow only if no template fits."
        },
        "review_before_creating": {
            "rule": "Before calling any tool that creates or imports (aigen_create_empty_lesson, aigen_create_add_items_to_lesson, aigen_import_items_json), present a plan and wait for the user's approval. For direct-compose, list each item with its actual content (question, answers, text). For templates, show the chosen template and the inputs (topic, level, theme, keywords). State the target course and lesson title. Create only after the user approves, and incorporate any changes they request.",
            "exception": "If the user has said to just go ahead, or to skip the review, create without pausing."
        },
        "authentication": {
            "how": "Send your Moodle web service token in the 'X-API-Key' request header on every call. Configure it once in your client.",
            "getting_a_token": "Create a token in Moodle (Site administration > Server > Web services > Manage tokens) for the 'aigenservice', or POST username/password and service='aigenservice' to {MOODLE_URL}/login/token.php.",
            "calls": "Every operation is POST /<function> (the function name without the 'mod_minilesson_' prefix), with the arguments as a JSON body. Read responses as {\\"data\\": ...} on success or {\\"error\\": true, \\"message\\": ...} on failure."
        },
        "choosing_your_approach": {
            "summary": "There are two ways to create items in a lesson: (A) the template workflow (typical_workflow), where you pick a template and the server generates the content with AI; and (B) the direct-compose workflow (direct_compose_workflow), where you author the item JSON yourself and import it. Decide per lesson. You may combine both in one lesson - see hybrid_pattern.",
            "prefer_templates_when": [
                "A template returned by list_templates matches the request - check its description, skills and outputs before deciding no template fits",
                "You want the content (text, questions, and any images) generated for you rather than authoring it yourself",
                "The lesson needs generated images or audio: templates can produce media server-side, whereas direct compose requires you to supply every media file yourself as base64",
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
            {"step": 1, "name": "List item types", "call": "POST /aigen_list_itemtypes", "purpose": "Discover the available item types and what each can produce. Also read choosing_your_approach.", "body": {}},
            {"step": 2, "name": "List templates", "call": "POST /aigen_list_templates", "purpose": "Discover available AI generation templates and their inputs.", "body": {}},
            {"step": 3, "name": "List courses", "call": "POST /list_courses", "purpose": "Find the course to create the lesson in.", "body": {}},
            {"step": 4, "name": "List minilessons", "call": "POST /aigen_list_minilessons", "purpose": "See existing lessons in a course (optional).", "body": {"courseid": "<id>"}},
            {"step": 5, "name": "Create empty lesson", "call": "POST /aigen_create_empty_lesson", "purpose": "Create an empty lesson container.", "returns": "cmid", "body": {"courseid": "<id>", "title": "<title>"}},
            {"step": 6, "name": "Create and add items", "call": "POST /aigen_create_add_items_to_lesson", "purpose": "Generate AI content and add it to the lesson.", "returns": "jobid", "body": {"cmid": "<cmid>", "templateid": "<id>", "contextdata": "<...>"}},
            {"step": 7, "name": "Check status", "call": "POST /aigen_fetch_create_items_status", "purpose": "Poll job completion.", "polling": "Repeat every 2-5 seconds until status != 'Progress'.", "body": {"jobids": ["<jobid>"]}}
        ],
        "direct_compose_workflow": {
            "purpose": "Compose the lesson item JSON yourself and import it. Use when you are designing the content (e.g. converting a workbook page or lesson plan), or when the user should review items before they are created. Only item types with hasimportdocs=true can be composed this way.",
            "steps": [
                {"step": 1, "name": "List item types", "call": "POST /aigen_list_itemtypes", "purpose": "Choose item types matching the design; check hasimportdocs.", "body": {}},
                {"step": 2, "name": "Fetch item type details", "call": "POST /aigen_fetch_item_type_details", "purpose": "For each chosen type, fetch its import field spec (fields, allowed values, file areas, examplejson). Compose items strictly from the documented fields.", "body": {"itemtype": "<type>"}},
                {"step": 3, "name": "Create empty lesson", "call": "POST /aigen_create_empty_lesson", "purpose": "Create the lesson container.", "returns": "cmid", "body": {"courseid": "<id>", "title": "<title>"}},
                {"step": 4, "name": "Import items", "call": "POST /aigen_import_items_json", "purpose": "Submit the composed items as one payload {\\"items\\": [...], \\"files\\": {...}} as the itemsjson argument. Inspect the per-item errors array, fix rejected items and resubmit only those (do not resubmit imported items).", "body": {"cmid": "<cmid>", "itemsjson": "<json string>"}}
            ]
        },
        "hybrid_pattern": {
            "purpose": "Both workflows add items to the same lesson (by cmid), so one lesson can mix both.",
            "how": [
                "Create the lesson once with aigen_create_empty_lesson to get a cmid",
                "Run the template workflow against that cmid (aigen_create_add_items_to_lesson, then poll status)",
                "Then call aigen_import_items_json with the same cmid to append directly-composed items",
                "Each call appends after the existing items, so order your calls to get the item order you want"
            ]
        },
        "base_lesson_replication": {
            "when": "Only when the user gives you an exported lesson (itemsjson from aigen_export_items_json) and asks for more lessons like it on other topics.",
            "how": [
                "If you pull the source lesson yourself, call aigen_export_items_json with exclude_files=true - you drop the original images for a new topic anyway, and it avoids an oversized response.",
                "Treat the exported itemsjson as a known-good example. Keep each item's type, layout, options and settings, and rewrite only the wording for the new topic.",
                "Do not supply audio files: spoken audio is regenerated from the text on import.",
                "Uploaded images will not match the new topic: drop them, keep only if still suitable, or ask the user for new ones.",
                "For each topic, call aigen_create_empty_lesson for a new cmid, then aigen_import_items_json. Read the errors array and fix any rejected item before moving on."
            ]
        }
    }
}
JSON;
// phpcs:enable moodle.Files.LineLength

$openapi = array_merge($openapi, json_decode($agentinstructions, true));

// An empty PHP array encodes as a JSON array ([]); components.schemas must be a JSON
// object ({}) or the OpenAI custom GPT schema validator warns. Force an object when empty.
if (empty($openapi['components']['schemas'])) {
    $openapi['components']['schemas'] = new \stdClass();
}

echo json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
