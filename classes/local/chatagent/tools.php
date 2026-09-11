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

use mod_minilesson\local\aigen\facade;

/**
 * The tool surface the agent advertises to the model, and the rules for calling it.
 *
 * The aigen web service functions are the tools. Their names, descriptions and argument
 * schemas all come from the facade, which is the same source mcp.php and openapi.php use,
 * so the in-Moodle agent and the external MCP clients cannot be offered different tools.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tools {
    /**
     * Tools whose effects a teacher must approve before they run.
     *
     * The instructions tell the model to present a plan and wait, but a model can ignore
     * instructions, so the controller gates these server side instead of trusting it to.
     */
    const NEEDS_APPROVAL = [
        'mod_minilesson_aigen_create_empty_lesson',
        'mod_minilesson_aigen_create_add_items_to_lesson',
        'mod_minilesson_aigen_import_items_json',
    ];

    /** @var string The tool that queues an adhoc task rather than doing the work inline. */
    const ASYNC_TOOL = 'mod_minilesson_aigen_create_add_items_to_lesson';

    /**
     * @var int Serialised tool results longer than this are trimmed before the model sees them.
     *
     * Sized to fit the whole template catalogue, because the instructions ask the model to
     * compare every template's variants and control level before choosing one - which it cannot
     * do from a partial list. At roughly four bytes per token this is a small fraction of the
     * model's context, and cheap next to the cost of it composing an item by hand because the
     * template it needed was cut off.
     */
    const MAX_RESULT_BYTES = 250000;

    /**
     * @var int A string longer than this that looks like base64 is dropped rather than sent.
     *
     * Long enough not to catch a passage of prose, short enough to catch an encoded file.
     */
    const BINARY_THRESHOLD = 2000;

    /**
     * Function declarations for the model, in Gemini Interactions "tools" shape.
     *
     * @return array
     */
    public static function declarations(): array {
        $tools = [];
        foreach (facade::functions_info() as $name => $info) {
            $tools[] = [
                'type' => 'function',
                'name' => $name,
                'description' => $info->description,
                'parameters' => facade::input_schema($info),
            ];
        }
        return $tools;
    }

    /**
     * Whether a name the model produced is one of the tools we actually offer.
     *
     * The brokered function set is the allowlist: a name that is not in it is not callable,
     * whatever the model asks for.
     *
     * @param string $functionname
     * @return bool
     */
    public static function is_allowed(string $functionname): bool {
        return in_array($functionname, facade::function_names(), true);
    }

    /**
     * Whether this tool needs the teacher's explicit approval before it runs.
     *
     * @param string $functionname
     * @return bool
     */
    public static function needs_approval(string $functionname): bool {
        return in_array($functionname, self::NEEDS_APPROVAL, true);
    }

    /**
     * Whether this tool changes anything, as its own web service declaration says it does.
     *
     * Used to decide what the session's lesson pin applies to. Reading another lesson is a
     * legitimate part of the work - reusing an existing lesson as the model for a new one is
     * one of the three request kinds - and the read is gated by that function's own capability
     * check. Writing somewhere other than the lesson the teacher opened is never legitimate.
     *
     * @param string $functionname
     * @return bool
     */
    public static function is_write(string $functionname): bool {
        static $types = null;
        if ($types === null) {
            $types = [];
            foreach (facade::functions_info() as $name => $info) {
                $types[$name] = $info->type ?? 'write';
            }
        }
        // An unknown function never reaches here (the allowlist runs first), but if one did,
        // treating it as a write is the safe way to be wrong.
        return ($types[$functionname] ?? 'write') === 'write';
    }

    /**
     * A short, human-readable line describing what a pending call would do, for the approval card.
     *
     * Deliberately generic: it reads the arguments the model actually sent rather than
     * special casing each tool, so a new aigen function gets a usable summary for free.
     *
     * @param string $functionname
     * @param array $args
     * @return string
     */
    public static function summarise(string $functionname, array $args): string {
        $short = facade::short_name($functionname);
        $parts = [];
        foreach (['cmid', 'templateid', 'courseid', 'name', 'title'] as $key) {
            if (isset($args[$key]) && is_scalar($args[$key])) {
                $parts[] = $key . ' ' . $args[$key];
            }
        }
        if (isset($args['items']) && is_array($args['items'])) {
            $parts[] = count($args['items']) . ' items';
        }
        return $parts ? $short . ' (' . implode(', ', $parts) . ')' : $short;
    }

    /**
     * Serialise a tool result for the model, without the parts it cannot use.
     *
     * Two things happen here, and both were found by measuring rather than by guessing. Media is
     * dropped: an exported lesson is over seven megabytes, of which the items are six kilobytes
     * and the rest is base64 the model can neither read nor act on. And when what is left is
     * still too long, whole entries are dropped from the end rather than the string being cut
     * mid-character, so what arrives is still valid JSON and the model is told plainly how many
     * entries it is not seeing.
     *
     * @param mixed $data the web service return value
     * @return string
     */
    public static function encode_result($data): string {
        $data = self::prune($data);

        $text = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($text === false) {
            return '{"error":"result could not be encoded"}';
        }
        if (strlen($text) <= self::MAX_RESULT_BYTES) {
            return $text;
        }

        // A list can be shortened honestly: keep whole entries, and say how many were left out.
        if (is_array($data) && array_is_list($data)) {
            $kept = [];
            $length = 2;
            foreach ($data as $entry) {
                $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($length + strlen($encoded) + 1 > self::MAX_RESULT_BYTES) {
                    break;
                }
                $kept[] = $entry;
                $length += strlen($encoded) + 1;
            }
            $omitted = count($data) - count($kept);
            return json_encode([
                'results' => $kept,
                'omitted' => $omitted,
                'note' => 'This list was too long to return in full. ' . $omitted . ' of ' . count($data)
                    . ' entries are missing. Narrow the request rather than assuming the rest do not exist.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return substr($text, 0, self::MAX_RESULT_BYTES)
            . ' ...[TRUNCATED: the result was too large to return in full. Ask for a single item'
            . ' type, lesson or page rather than the whole set.]';
    }

    /**
     * Strip out what the model cannot use, wherever it is buried.
     *
     * File contents travel through these functions as base64, sometimes nested inside a JSON
     * string that is itself a field of the result. Sending them costs real money, fills the
     * context window, and tells the model nothing.
     *
     * Public because an uploaded lesson export has exactly the same problem as a tool result
     * that returns one - it is the same payload, arriving by a different door.
     *
     * @param mixed $value
     * @param int $depth guards against a pathological structure
     * @return mixed
     */
    public static function prune($value, int $depth = 0) {
        if ($depth > 12) {
            return $value;
        }

        if (is_string($value)) {
            if (strlen($value) > self::BINARY_THRESHOLD && preg_match('~^[A-Za-z0-9+/\r\n]+={0,2}$~', $value)) {
                return '[file contents omitted: ' . strlen($value) . ' bytes]';
            }
            // Some returns carry a whole JSON document as a string field; the media hides in there.
            if (strlen($value) > self::BINARY_THRESHOLD && ($decoded = json_decode($value, true)) !== null) {
                return json_encode(self::prune($decoded, $depth + 1), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::prune($item, $depth + 1);
            }
            return $value;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->$key = self::prune($item, $depth + 1);
            }
            return $value;
        }

        return $value;
    }
}
