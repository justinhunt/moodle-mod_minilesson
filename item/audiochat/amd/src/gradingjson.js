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
 * Parses the JSON grading object returned by an audiochat AI provider.
 *
 * The feedback we ask for is several paragraphs, and models routinely put the
 * line breaks between those paragraphs into the JSON string as real newlines
 * rather than the \n escape that JSON requires. That is a syntax error, and it
 * used to lose the whole grading result, feedback and score together. So repair
 * unescaped control characters inside string literals before parsing.
 *
 * @module     minilessonitem_audiochat/gradingjson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function (log) {
    "use strict";

    /**
     * Escape control characters that appear inside the string literals of a JSON
     * document. Control characters between literals are untouched: they are the
     * ordinary whitespace that keeps the document readable.
     *
     * @param {String} json the JSON text to repair
     * @returns {String} the same text with control characters in strings escaped
     */
    var escapecontrolchars = function (json) {
        var escapes = {'\b': '\\b', '\f': '\\f', '\n': '\\n', '\r': '\\r', '\t': '\\t'};
        var out = '';
        var instring = false;
        var escaped = false;

        for (var i = 0; i < json.length; i++) {
            var ch = json.charAt(i);

            if (escaped) {
                // Whatever this is, the backslash before it already spoke for it.
                out += ch;
                escaped = false;
                continue;
            }
            if (ch === '\\' && instring) {
                out += ch;
                escaped = true;
                continue;
            }
            if (ch === '"') {
                instring = !instring;
                out += ch;
                continue;
            }
            if (instring && ch < ' ') {
                out += escapes[ch] || '\\u' + ('000' + ch.charCodeAt(0).toString(16)).slice(-4);
                continue;
            }
            out += ch;
        }

        return out;
    };

    return {
        /**
         * Pull the grading object out of whatever text the model sent back.
         *
         * @param {String} text the raw model output, which may wrap the JSON in prose
         * @returns {Object|Boolean} the parsed grading object, or false if there is none
         */
        parse: function (text) {
            if (!text) {
                return false;
            }

            // The grading object is flat, so the first brace to the last is all of it.
            var start = text.indexOf('{');
            var end = text.lastIndexOf('}');
            if (start === -1 || end === -1 || end < start) {
                log.debug('AudioChat grading: no JSON object in the model output');
                return false;
            }
            var json = text.substring(start, end + 1);

            try {
                return JSON.parse(json);
            } catch (err) {
                // Almost always the raw newlines described above, so repair and retry.
                try {
                    var repaired = JSON.parse(escapecontrolchars(json));
                    log.debug('AudioChat grading: parsed after escaping control characters');
                    return repaired;
                } catch (err2) {
                    log.debug('AudioChat grading: unparseable JSON', err2, json);
                    return false;
                }
            }
        }
    };
});
