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

// The OAuth2 scheme is only advertised when the shared local_oauthmcp authorization server
// plugin is installed - see mod_minilesson_mcp_oauth_resources() in lib.php.
if (class_exists('\local_oauthmcp\api')) {
    $openapi['components']['securitySchemes']['OAuth2'] = [
        'type' => 'oauth2',
        'flows' => [
            'authorizationCode' => [
                'authorizationUrl' => $CFG->wwwroot . '/local/oauthmcp/oauth_authorize.php',
                'tokenUrl' => $CFG->wwwroot . '/local/oauthmcp/oauth_token.php',
                'refreshUrl' => $CFG->wwwroot . '/local/oauthmcp/oauth_token.php',
                'scopes' => ['aigen' => 'Full access to the aigen web service functions'],
            ],
        ],
    ];
    $openapi['security'][] = ['OAuth2' => ['aigen']];
}

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
            "summary": "User requests arrive as one of three kinds. Route on what the user wants DONE, not on what they attached - the same PDF can arrive with any of these intents. Identify which, then follow the referenced workflow.",
            "reproduce_supplied_material": "The user wants what is in the material turned into items, faithfully - a worksheet, a set of questions, a lesson plan to transcribe. Follow direct_compose_workflow (OCR images/PDFs yourself first). Do not run a template: it would regenerate content the user already has.",
            "generate_new_activities": "The user wants NEW activities, whether they described the lesson or attached something to base it on. Any attachment is context rather than content: it supplies the topic, the vocabulary, the reading text, the level. 'Supplementary activities for the lesson in this PDF', 'practice for these words' and 'a lesson about X' are all this kind. Read choosing_your_approach to decide, item by item, between composing the items yourself (direct_compose_workflow) and running a template (typical_workflow).",
            "reuse_an_existing_lesson": "The user uploads an exported MiniLesson (itemsjson), or asks to base new lessons on an existing one, to reuse its shape for other topics. Follow base_lesson_replication. To reuse a lesson already on the site, pull it first with aigen_export_items_json and set exclude_files=true (the original images are dropped for a new topic anyway, and it keeps the response small).",
            "an_attachment_does_not_mean_reproduce": "Read the verb. 'Turn this into items', 'reproduce', 'keep these questions' is reproduce_supplied_material. 'Generate', 'supplementary', 'practice for', 'based on', 'inspired by' is generate_new_activities. If it is genuinely unclear, ask which they want before building anything - the two produce very different lessons from the same file."
        },
        "review_before_creating": {
            "rule": "Before calling any tool that creates or imports (aigen_create_empty_lesson, aigen_create_add_items_to_lesson, aigen_import_items_json), present a plan and wait for the user's approval. For direct-compose, list each item with its actual content (question, answers, text, image prompts). For templates, list EVERY input the template declares with the exact value you will send, each marked (from the user), (your choice) or (BLANK). State the target course and lesson title. Create only after the user approves, and incorporate any changes they request.",
            "declare_your_choices": "A decision you made on the user's behalf is still a decision and belongs in the plan - including defaults you accepted and inputs you are leaving empty. Where you picked a value the user expressed no preference about (image style, language level, voice, number of items), name your choice and offer the alternatives rather than presenting it as settled.",
            "never_send_a_required_input_empty": "Inputs come back from aigen_list_templates with required:true when the template actually consumes them. An empty required input has no default to fall back on: it produces plausible looking but broken content - a vocabulary card whose translation repeats the word, a gap-fill with nothing gapped, a sentence whose hint is missing. If the user has not told you what the input needs (a native language, a source text, a level), ask them before creating. aigen_create_add_items_to_lesson rejects the call and names the empty inputs.",
            "exception": "If the user has said to just go ahead, or to skip the review, create without pausing. This waives the review, not the required inputs: still ask for a required value you do not have."
        },
        "choosing_a_template": {
            "rule": "Several templates usually produce the same item type, and they differ in how much you decide. Each template reports a 'control' level - 'supplied' (your text is used verbatim), 'derived' (the AI rewrites or marks up text you supply), 'generated' (the AI invents the content from a topic, keywords or level) - and lists its siblings in 'variants', strongest control first. Read the variants before settling on a template.",
            "test": "For every teaching point you have already decided - which words are shuffled or gapped, which answer is correct, the translation language, the grammar point being practised, the item sequence - check the template has an input that carries it. If it has none, the AI will decide it for you and may contradict the lesson aim. Move to the higher-control variant, or compose the item directly with the direct_compose_workflow. Lift the inputs straight out of any material - its vocabulary into user_keywords, its passage into user_text, its level into user_level.",
            "example": "Asked for a word shuffle that drills a specific grammar point, a 'generated' word shuffle template that takes only sentences and an image style will shuffle whichever words it likes, which will usually not be the grammar point. The 'supplied' variant takes sentences already marked up with the words to shuffle, so the grammar point survives.",
            "tradeoff": "Higher-control templates ask more of you (marked-up sentences, plus clean sentences for image generation). That is the trade, not a reason to avoid them: take a 'generated' variant only where the user has genuinely left that detail open.",
            "agentonly": "Templates flagged agentonly:true are hidden from the human picker and are usually the high-control member of their family - they ask for content that is tedious to type by hand but easy for you to compose."
        },
        "authentication": {
            "how": "Send your Moodle web service token in the 'X-API-Key' request header on every call. Configure it once in your client.",
            "getting_a_token": "Create a token in Moodle (Site administration > Server > Web services > Manage tokens) for the 'aigenservice', or POST username/password and service='aigenservice' to {MOODLE_URL}/login/token.php.",
            "calls": "Every operation is POST /<function> (the function name without the 'mod_minilesson_' prefix), with the arguments as a JSON body. Read responses as {\\"data\\": ...} on success or {\\"error\\": true, \\"message\\": ...} on failure."
        },
        "choosing_your_approach": {
            "summary": "There are two ways to create items in a lesson: (A) the direct-compose workflow (direct_compose_workflow), where you author the item JSON yourself and import it; and (B) the template workflow (typical_workflow), where you pick a template and a separate AI on the server generates the content. Neither is all-or-nothing: one lesson can mix composed items with the output of one or several templates.",
            "default": "Composing the items yourself is the stronger default. You have read the user's request and their material, so you can design each item to fit it - which words are gapped, the distractors, the order of the activities, the layout, how hard each step is - and every field of the item is yours to set. A template's AI sees only the few inputs the template exposes and fixes everything else itself.",
            "the_more_the_request_says_the_less_a_template_fits": "A teaching aim, a grammar point, a word list, a passage or lesson plan to build on, a sequence of activities, particular item types, notes about the learners: each is a decision a template's inputs may not carry, and whatever they do not carry the template's AI decides for itself, often against the lesson aim. When the request is detailed or comes with material, compose the items yourself. Use a template for part of a lesson only where it does that part as well as you would.",
            "images_and_audio": "Media is no reason to prefer a template. To get a generated picture in a composed item, put an image prompt in the import payload's fileprompts ({'fileprompts': {'<filesid>': {'<filearea>': {'<filename>': '<image prompt>'}}}}) and the server generates it; the files convention in aigen_fetch_item_type_details explains the details and the per-import limit. Spoken audio is generated from the item's TTS fields.",
            "prefer_templates_when": [
                "The request is short and open, and close to what a template produces - 'a vocabulary lesson about food for A2', with nothing more said. A tested template gives a well-formed lesson quickly, and a multi-item template's items are designed to work as a set",
                "The item type has no import docs (list_itemtypes reports hasimportdocs=false), so it cannot be composed"
            ],
            "prefer_direct_compose_when": [
                "The request specifies teaching points, a sequence, item types or learner needs that a template's inputs do not carry",
                "The user supplied material: exact content to reproduce, or a passage, word list or lesson plan to build new activities on",
                "You need an item shape or setting no template exposes (the two-column multichoice answer layout, say)",
                "You are converting an existing lesson plan, or round-tripping items from aigen_export_items_json (export, edit, re-import)",
                "You want per-item error feedback to iterate: import_items_json returns an errors array you can act on and resubmit, whereas a template job is fire-and-forget plus status polling"
            ],
            "choosing_item_types": "Choose item types for what the lesson needs, from the usage each one reports in aigen_list_itemtypes.",
            "constraints": [
                "Direct compose is only available for item types where list_itemtypes reports hasimportdocs=true; use a template for any other type",
                "When you do use a template, read choosing_a_template and pick the variant whose inputs carry the decisions you have already made"
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
            "purpose": "Compose the lesson item JSON yourself and import it. This is the usual way to build a lesson for a detailed request or one that comes with material (see choosing_your_approach). Only item types with hasimportdocs=true can be composed this way.",
            "steps": [
                {"step": 1, "name": "List item types", "call": "POST /aigen_list_itemtypes", "purpose": "Choose item types matching the design; check hasimportdocs.", "body": {}},
                {"step": 2, "name": "Fetch item type details", "call": "POST /aigen_fetch_item_type_details", "purpose": "For each chosen type, fetch its import field spec (fields, allowed values, file areas, examplejson). Compose items strictly from the documented fields. If the response carries an authoringguide, read it in full and follow it before writing that item's content.", "body": {"itemtype": "<type>"}},
                {"step": 3, "name": "Create empty lesson", "call": "POST /aigen_create_empty_lesson", "purpose": "Create the lesson container.", "returns": "cmid", "body": {"courseid": "<id>", "title": "<title>"}},
                {"step": 4, "name": "Import items", "call": "POST /aigen_import_items_json", "purpose": "Submit the composed items as one payload {\\"items\\": [...], \\"files\\": {...}} as the itemsjson argument. Inspect the per-item errors array, fix rejected items and resubmit only those (do not resubmit imported items).", "body": {"cmid": "<cmid>", "itemsjson": "<json string>"}}
            ]
        },
        "hybrid_pattern": {
            "purpose": "Every way of adding items works against the same lesson (by cmid), so one lesson can mix as many of them as it needs - composed items, several templates, or both.",
            "how": [
                "Create the lesson once with aigen_create_empty_lesson to get a cmid",
                "Call aigen_import_items_json with that cmid for the items you compose",
                "Run the template workflow against the same cmid (aigen_create_add_items_to_lesson, then poll status) for any part of the lesson a template does as well as you would. Run it once per template: many templates produce a single item (outputs[].itemcount is 1) so they can be combined",
                "Each call appends after the existing items, so order your calls to get the item order you want"
            ]
        },
        "base_lesson_replication": {
            "when": "Only when the user gives you an exported lesson (itemsjson from aigen_export_items_json) and asks for more lessons like it on other topics.",
            "how": [
                "If you pull the source lesson yourself, call aigen_export_items_json with exclude_files=true - you drop the original images for a new topic anyway, and it avoids an oversized response.",
                "Treat the exported itemsjson as a known-good example. Keep each item's type, layout, options and settings, and rewrite only the wording for the new topic.",
                "Do not supply audio files: spoken audio is regenerated from the text on import.",
                "Uploaded images will not match the new topic: drop them, and where the new topic wants pictures give new image prompts in the payload's fileprompts.",
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
