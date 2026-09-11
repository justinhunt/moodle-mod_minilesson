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
 * One teacher's conversation about one lesson, as the controller sees it.
 *
 * The controller reads and mutates this object but never persists it - that is the caller's
 * job, which is what lets the same loop run behind the web services and behind the CLI
 * harness that proves it. to_array()/from_array() are the whole persistence contract.
 *
 * Two fields exist for safety rather than for the conversation. `interactionid` is the
 * provider's handle on the history: it is held here, server side, because a browser that
 * could choose it could replay somebody else's conversation. `pendingargs` is the arguments
 * of a call awaiting approval, held for the same reason - if the browser supplied them on
 * approval, the approval would be checking one thing and running another.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversation {
    /** @var string Nothing outstanding; waiting for the teacher to say something. */
    const STATE_IDLE = 'idle';

    /** @var string A tool is approved (or needs no approval) and waiting to run. */
    const STATE_AWAITING_TOOL = 'awaiting_tool';

    /** @var string A tool is waiting for the teacher to approve it. */
    const STATE_AWAITING_APPROVAL = 'awaiting_approval';

    /** @var string The last turn failed; the transcript is intact and the teacher can retry. */
    const STATE_ERROR = 'error';

    /** @var int Row id once persisted, 0 before. */
    public int $id = 0;

    /** @var int The teacher who owns this conversation. */
    public int $userid = 0;

    /** @var int The course module the conversation is about; every write is pinned to it. */
    public int $cmid = 0;

    /** @var int The course that module is in, so cleanup and course deletion can find the row. */
    public int $courseid = 0;

    /** @var string Which provider issued interactionid; a handle does not survive a provider change. */
    public string $provider = '';

    /** @var string The provider's id for the conversation so far, or '' to send the transcript instead. */
    public string $interactionid = '';

    /** @var string Call id of a tool the model has asked for but that has not run yet. */
    public string $pendingcallid = '';

    /** @var string Function name of that pending call. */
    public string $pendingtool = '';

    /** @var array Arguments of that pending call, as the model sent them. */
    public array $pendingargs = [];

    /** @var string One of the STATE_* constants. */
    public string $state = self::STATE_IDLE;

    /** @var int Tools run so far in the current turn; reset when the teacher speaks. */
    public int $toolcalls = 0;

    /** @var int Turns this teacher has taken, for rate limiting. */
    public int $turncount = 0;

    /**
     * @var array The transcript, oldest first. Each entry:
     *  ['role' => 'user'|'assistant'|'tool', 'content' => string,
     *   'toolname' => string|null, 'attachments' => array]
     */
    public array $messages = [];

    /**
     * Add a message to the transcript.
     *
     * @param string $role user, assistant or tool
     * @param string $content
     * @param string|null $toolname set on tool messages
     * @param array $attachments attachment descriptors carried by a user message
     * @return void
     */
    public function add_message(string $role, string $content, ?string $toolname = null, array $attachments = []): void {
        $this->messages[] = [
            'role' => $role,
            'content' => $content,
            'toolname' => $toolname,
            'attachments' => $attachments,
        ];
    }

    /**
     * Forget the pending call and go back to idle.
     *
     * @return void
     */
    public function clear_pending(): void {
        $this->pendingcallid = '';
        $this->pendingtool = '';
        $this->pendingargs = [];
        $this->state = self::STATE_IDLE;
    }

    /**
     * Every attachment sent so far, deduplicated by its identifier.
     *
     * Needed only on a replay: while the provider still holds the conversation, an attachment
     * is sent once and never again.
     *
     * @return array
     */
    public function all_attachments(): array {
        $attachments = [];
        foreach ($this->messages as $message) {
            foreach ($message['attachments'] as $attachment) {
                $attachments[$attachment['id'] ?? count($attachments)] = $attachment;
            }
        }
        return array_values($attachments);
    }

    /**
     * Serialise for storage.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'userid' => $this->userid,
            'cmid' => $this->cmid,
            'courseid' => $this->courseid,
            'provider' => $this->provider,
            'interactionid' => $this->interactionid,
            'pendingcallid' => $this->pendingcallid,
            'pendingtool' => $this->pendingtool,
            'pendingargs' => $this->pendingargs,
            'state' => $this->state,
            'toolcalls' => $this->toolcalls,
            'turncount' => $this->turncount,
            'messages' => $this->messages,
        ];
    }

    /**
     * Rebuild from storage.
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data): self {
        $conversation = new self();
        foreach (['id', 'userid', 'cmid', 'courseid', 'toolcalls', 'turncount'] as $key) {
            if (isset($data[$key])) {
                $conversation->$key = (int) $data[$key];
            }
        }
        foreach (['provider', 'interactionid', 'pendingcallid', 'pendingtool', 'state'] as $key) {
            if (isset($data[$key])) {
                $conversation->$key = (string) $data[$key];
            }
        }
        foreach (['pendingargs', 'messages'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                $conversation->$key = $data[$key];
            }
        }
        return $conversation;
    }
}
