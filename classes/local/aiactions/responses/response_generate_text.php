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

namespace mod_minilesson\local\aiactions\responses;

/**
 * Class response_generate_text
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_generate_text extends \core_ai\aiactions\responses\response_generate_text {

    protected $jsondata;

    #[\Override]
    public function set_response_data(array $response): void {
        parent::set_response_data($response);
        $response = (object) $this->get_response_data();
        if (!empty($response->generatedcontent) && preg_match('/\{[\s\S]*?\}/', $response->generatedcontent, $m)) {
            $this->jsondata = $m[0];
        }
    }

    #[\Override]
    public function get_response_data(): array {
        return parent::get_response_data() + [
            'jsondata' => $this->jsondata ?? '{}'
        ];
    }

}
