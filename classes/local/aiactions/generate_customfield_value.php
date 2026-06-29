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

namespace mod_minilesson\local\aiactions;

use core_customfield\field_controller;
use mod_minilesson\aigen;

/**
 * Class generate_customfield_value
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_customfield_value extends generate_text {

    /** @var field_controller[] */
    protected array $fields;

    protected array $importjson;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        array $fields,
        array $importjson,
    ) {
        $this->fields = $fields;
        $this->importjson = $importjson;
        generate_text::__construct($contextid, $userid, $prompttext);
    }

    public function generate_prompt(): string {
        $importjson = json_encode($this->importjson, JSON_PRETTY_PRINT);
        $promptfields = $outputfields = [];
        foreach ($this->fields as $fieldshortname => $field) {
            $fieldtype = $field->get('type');
            $fielddesc = clean_param($field->get('description'), PARAM_TEXT);
            $fieldoptions = aigen::get_customfield_options($field);
            if ($fieldtype == 'text') {
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": \"...\"";
            } else if ($fieldtype === 'select') {
                $fieldoptions = join(', ', $fieldoptions);
                $fielddesc = str_replace('{optionslist}', $fieldoptions, $fielddesc);
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": \"...\"";
            } else if ($fieldtype === 'multiselect') {
                $fieldoptions = join(', ', $fieldoptions);
                $fielddesc = str_replace('{optionslist}', $fieldoptions, $fielddesc);
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": [\"...\", \"...\"]";
            }
        }

        $promptfields = join(PHP_EOL, array_map(function ($promptfield) {
            return "* {$promptfield}";
        }, $promptfields));

        $outputfields = join(PHP_EOL, array_map(function ($outputfield) {
            return "    {$outputfield},";
        }, $outputfields));

        $this->prompttext = <<<PROMPT
**Task:** Analyze the provided JSON object and generate the following fields based on its content.

**Input JSON:**

```json
{$importjson}
```

**Output Requirements:**
Please provide the output strictly in valid JSON format using the keys defined below.

{$promptfields}

**Output Format:**

```json
{
{$outputfields}
}
```
PROMPT;

        return $this->prompttext;
    }

    public static function get_system_instruction(): string {
        return 'You are an expert linguistic analyst and content structured-data specialist';
    }
}
