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

use core_ai\aiactions\generate_text as baseclass;

/**
 * Class generate_text
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class generate_text extends baseclass implements inherited_action {
    use common_functions;

    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext
    ) {
        parent::__construct($contextid, $userid, $prompttext);
        $this->generate_prompt();
    }

    public static function get_model_parameters(string $provider): array {
        switch($provider) {
            case 'openai':
                return [
                    'max_tokens' => 800,
                    'temperature' => 0,
                    'top_p' => 1,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                    'response_format' => ['type' => 'json_object'],
                ];
            case 'deepseek':
                return [
                    'max_tokens' => 800,
                    'temperature' => 0,
                    'top_p' => 1,
                    'presence_penalty' => 0,
                    'frequency_penalty' => 0,
                    'response_format' => ['type' => 'json_object'],
                ];
            // Apidoc: https://ai.google.dev/api/generate-content#generationconfig.
            // case 'gemini':
            //     return [
            //         'maxOutputTokens' => 800,
            //         'temperature' => 0,
            //         'topP' => 1,
            //         'presencePenalty' => 0,
            //         'frequencyPenalty' => 0,
            //     ];
            default:
                return [];
        };
    }

}
