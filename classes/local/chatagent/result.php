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
 * One model call's outcome, in a shape no provider owns.
 *
 * Every driver normalises to this, so the controller never learns which provider it is
 * talking to. That is what lets a second provider be added without touching the loop.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /** @var string Model finished its turn. */
    const STATUS_COMPLETED = 'completed';

    /** @var string Model wants a tool run before it can continue. */
    const STATUS_REQUIRES_ACTION = 'requires_action';

    /** @var string The call did not produce a usable turn; see $errorcode. */
    const STATUS_FAILED = 'failed';

    /** @var string Credentials missing, rejected, or not permitted. */
    const ERROR_AUTH = 'auth';

    /** @var string Rate limited, out of quota, or out of credits. */
    const ERROR_QUOTA = 'quota';

    /** @var string Could not reach the provider at all. */
    const ERROR_CONNECTION = 'connection';

    /**
     * The conversation the provider was asked to continue is gone.
     *
     * Retention windows are short (about a day on Google's free tier), so this is a routine
     * outcome rather than an exceptional one, and the controller answers it by replaying the
     * stored transcript instead of surfacing an error.
     */
    const ERROR_EXPIRED = 'expired';

    /** @var string A response arrived but could not be understood. */
    const ERROR_TRANSPORT = 'transport';

    /** @var string Anything else. */
    const ERROR_UNKNOWN = 'unknown';

    /** @var string Provider's id for this interaction; '' when the provider kept no state. */
    public string $interactionid = '';

    /** @var string One of the STATUS_* constants. */
    public string $status = self::STATUS_FAILED;

    /**
     * @var array Normalised steps, each one of:
     *  ['type' => 'text',          'text' => string]
     *  ['type' => 'thought',       'text' => string]
     *  ['type' => 'function_call', 'callid' => string, 'name' => string, 'args' => array]
     */
    public array $steps = [];

    /** @var array ['input_tokens' => int, 'output_tokens' => int, 'credits' => int|null] */
    public array $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'credits' => null];

    /** @var string|null Message safe to show a teacher; provider detail is logged, not returned. */
    public ?string $error = null;

    /** @var string|null One of the ERROR_* constants when $status is failed. */
    public ?string $errorcode = null;

    /**
     * Build a failure result.
     *
     * @param string $errorcode one of the ERROR_* constants
     * @param string $message message safe to show a teacher
     * @return self
     */
    public static function fail(string $errorcode, string $message): self {
        $result = new self();
        $result->status = self::STATUS_FAILED;
        $result->errorcode = $errorcode;
        $result->error = $message;
        return $result;
    }

    /**
     * Whether the call failed.
     *
     * @return bool
     */
    public function failed(): bool {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * The first function call the model asked for, or null if it asked for none.
     *
     * The API can return several, but the loop executes one per request so the browser sees
     * progress between each; any others are re-offered on the next turn.
     *
     * @return array|null
     */
    public function first_function_call(): ?array {
        foreach ($this->steps as $step) {
            if ($step['type'] === 'function_call') {
                return $step;
            }
        }
        return null;
    }

    /**
     * The model's visible text for this turn, with the steps joined into one message.
     *
     * @return string
     */
    public function text(): string {
        $parts = [];
        foreach ($this->steps as $step) {
            if ($step['type'] === 'text' && trim($step['text']) !== '') {
                $parts[] = $step['text'];
            }
        }
        return implode("\n\n", $parts);
    }
}
