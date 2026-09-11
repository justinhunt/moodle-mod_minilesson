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

/**
 * What the agent needs from a model provider, and nothing more.
 *
 * The first implementation calls Google directly with the site's own key. A second one,
 * brokering through Cloud Poodll for sites that have no key, implements this same interface;
 * because both return an result, the controller does not change.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider_driver {
    /**
     * Whether this provider is configured well enough to be used on this site.
     *
     * Cheap enough to call on every page load: it decides whether the agent's tab and
     * entry-point links are rendered at all.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * The provider's name, for logging and for the session's provider column.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Run one model call.
     *
     * @param array $input input blocks: text, document, image, or function_result
     * @param array $tools function declarations; interaction-scoped, so passed every call
     * @param string $instruction system instruction; interaction-scoped, so passed every call
     * @param string|null $previous id of the interaction to continue, or null to start fresh
     *        (which is also how a replay of the stored transcript is sent)
     * @return result
     */
    public function create_interaction(
        array $input,
        array $tools,
        string $instruction,
        ?string $previous
    ): result;
}
