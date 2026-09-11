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

use mod_minilesson\constants;
use mod_minilesson\local\aigen\facade;

/**
 * The agent loop: one model call and at most one tool execution per public call.
 *
 * The browser drives the loop rather than the server running it to completion, so that a turn
 * needing several tools cannot outlive a PHP request, and so the teacher sees each step as it
 * happens instead of watching a spinner. Every method returns the same envelope, and the
 * caller keeps calling step() while the status says there is more to do.
 *
 * The controller mutates the session it is given but never saves it. Persistence belongs to
 * the caller, which is what lets the web services and the CLI harness share this code.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controller {
    /** @var string The turn is over; the assistant has answered. */
    const STATUS_COMPLETE = 'complete';

    /** @var string A tool is ready to run; call step() again. */
    const STATUS_REQUIRES_TOOL = 'requires_tool';

    /** @var string A tool needs the teacher's approval; call approve() then step(). */
    const STATUS_REQUIRES_APPROVAL = 'requires_approval';

    /** @var string The turn failed; the transcript is intact. */
    const STATUS_ERROR = 'error';

    /** @var int Tools one turn may run before the loop is declared stuck. */
    const DEFAULT_MAX_TOOL_CALLS = 8;

    /** @var provider_driver The model provider. */
    protected provider_driver $driver;

    /** @var int Cap on tool executions per turn. */
    protected int $maxtoolcalls;

    /** @var string|null The lesson description, built once per request. */
    protected ?string $lessoncontext = null;

    /** @var string|null The tool executed just before the next model call, for the metrics row. */
    protected ?string $lasttool = null;

    /** @var string|null Why that tool was refused, if it was. */
    protected ?string $lastrejection = null;

    /** @var bool Whether a tool changed this lesson's items during this request. */
    protected bool $lessonchanged = false;

    /**
     * Build a controller bound to one provider.
     *
     * @param provider_driver $driver
     */
    public function __construct(provider_driver $driver) {
        $this->driver = $driver;
        $config = get_config(constants::M_COMPONENT);
        $this->maxtoolcalls = !empty($config->chatagentmaxtoolcalls)
            ? (int) $config->chatagentmaxtoolcalls
            : self::DEFAULT_MAX_TOOL_CALLS;
    }

    /**
     * The teacher has said something. Start a new turn.
     *
     * @param conversation $conversation
     * @param string $text what the teacher typed
     * @param array $attachments descriptors: ['id' => string, 'mimetype' => string, 'data' => base64]
     * @return array envelope
     */
    public function send_message(conversation $conversation, string $text, array $attachments = []): array {
        $conversation->add_message('user', $text, null, $attachments);
        $conversation->toolcalls = 0;
        $conversation->turncount++;
        $conversation->clear_pending();

        // While the provider still holds the history, this turn is just the new message; the
        // attachments went up with the message that carried them and are not sent again.
        $input = [['type' => 'text', 'text' => $text]];
        foreach ($attachments as $attachment) {
            $input[] = $this->attachment_block($attachment);
        }

        return $this->call_model($conversation, $input);
    }

    /**
     * Run the pending tool, if it is allowed to run, and give the result back to the model.
     *
     * @param conversation $conversation
     * @return array envelope
     */
    public function step(conversation $conversation): array {
        if ($conversation->pendingcallid === '') {
            return $this->envelope($conversation, self::STATUS_COMPLETE);
        }
        if ($conversation->state === conversation::STATE_AWAITING_APPROVAL) {
            // Nothing to do until the teacher decides; re-offer the same card.
            return $this->envelope($conversation, self::STATUS_REQUIRES_APPROVAL);
        }
        if ($conversation->toolcalls >= $this->maxtoolcalls) {
            // The model is going round in circles. Stop spending on it and say so plainly,
            // rather than letting a turn run up an unbounded bill.
            $conversation->clear_pending();
            $conversation->state = conversation::STATE_ERROR;
            return $this->envelope(
                $conversation,
                self::STATUS_ERROR,
                ['error' => get_string('chatagent_error_stuck', constants::M_COMPONENT)]
            );
        }

        $functionname = $conversation->pendingtool;
        $args = $conversation->pendingargs;
        $callid = $conversation->pendingcallid;
        $conversation->toolcalls++;
        $conversation->clear_pending();

        $outcome = $this->execute($conversation, $functionname, $args);
        $conversation->add_message('tool', $outcome['text'], $functionname);

        $input = [[
            'type' => 'function_result',
            'name' => $functionname,
            'call_id' => $callid,
            'result' => [['type' => 'text', 'text' => $outcome['text']]],
        ]];

        $envelope = $this->call_model($conversation, $input);
        if (!empty($outcome['job'])) {
            $envelope['job'] = $outcome['job'];
        }
        return $envelope;
    }

    /**
     * Record the teacher's decision on a pending call.
     *
     * The call id must match the one held in the session: an approval that named a different
     * call would be approving one thing and running another.
     *
     * @param conversation $conversation
     * @param string $callid
     * @param bool $approved
     * @return array envelope
     */
    public function approve(conversation $conversation, string $callid, bool $approved): array {
        if ($conversation->state !== conversation::STATE_AWAITING_APPROVAL || $conversation->pendingcallid !== $callid) {
            return $this->envelope(
                $conversation,
                self::STATUS_ERROR,
                ['error' => get_string('chatagent_error_nopending', constants::M_COMPONENT)]
            );
        }

        if ($approved) {
            $conversation->state = conversation::STATE_AWAITING_TOOL;
            return $this->envelope($conversation, self::STATUS_REQUIRES_TOOL);
        }

        // A refusal is part of the conversation, not an error: tell the model so it can offer
        // something else rather than repeating the same call.
        $functionname = $conversation->pendingtool;
        $callid = $conversation->pendingcallid;
        $conversation->clear_pending();
        $refusal = get_string('chatagent_declined', constants::M_COMPONENT);
        $conversation->add_message('tool', $refusal, $functionname);

        return $this->call_model($conversation, [[
            'type' => 'function_result',
            'name' => $functionname,
            'call_id' => $callid,
            'result' => [['type' => 'text', 'text' => $refusal]],
        ]]);
    }

    /**
     * Make one model call, replaying the transcript if the provider has forgotten the
     * conversation, and turn the result into an envelope.
     *
     * @param conversation $conversation
     * @param array $input
     * @return array envelope
     */
    protected function call_model(conversation $conversation, array $input): array {
        $tools = tools::declarations();
        $instruction = $this->instruction($conversation);
        $previous = $conversation->interactionid ?: null;

        // No handle, but a conversation already under way: the provider is not holding this
        // history, so send the history itself. That happens when the retention window has
        // expired, when the site has changed provider, and after any turn that failed before
        // an interaction was stored. Sending only the new message in those cases looks like it
        // works - the model answers - but it has quietly lost the whole conversation.
        $replayed = false;
        if ($previous === null && count($conversation->messages) > 1) {
            $input = $this->replay_input($conversation);
            $replayed = true;
        }

        $result = $this->driver->create_interaction(
            $input,
            $tools,
            $instruction,
            $previous
        );
        $this->measure($conversation, $input, $result, $replayed);

        // Retention windows are short - about a day on Google's free tier - so a conversation
        // going missing is ordinary. Send the stored transcript instead of the handle and
        // carry on, rather than showing the teacher an error for something we can recover.
        if ($result->failed() && $result->errorcode === result::ERROR_EXPIRED) {
            $conversation->interactionid = '';
            $replayinput = $this->replay_input($conversation);
            $result = $this->driver->create_interaction($replayinput, $tools, $instruction, null);
            $this->measure($conversation, $replayinput, $result, true);
        }

        if ($result->failed()) {
            $conversation->state = conversation::STATE_ERROR;
            return $this->envelope($conversation, self::STATUS_ERROR, [
                'error' => $result->error,
                'errorcode' => $result->errorcode,
            ]);
        }

        if ($result->interactionid !== '') {
            $conversation->interactionid = $result->interactionid;
            $conversation->provider = $this->driver->get_name();
        }

        $text = $result->text();
        if ($text !== '') {
            $conversation->add_message('assistant', $text);
        }

        $call = $result->first_function_call();
        if ($call === null) {
            $conversation->state = conversation::STATE_IDLE;
            return $this->envelope($conversation, self::STATUS_COMPLETE);
        }

        $conversation->pendingcallid = $call['callid'];
        $conversation->pendingtool = $call['name'];
        $conversation->pendingargs = $call['args'];

        if (tools::needs_approval($call['name'])) {
            $conversation->state = conversation::STATE_AWAITING_APPROVAL;
            return $this->envelope($conversation, self::STATUS_REQUIRES_APPROVAL);
        }
        $conversation->state = conversation::STATE_AWAITING_TOOL;
        return $this->envelope($conversation, self::STATUS_REQUIRES_TOOL);
    }

    /**
     * Record what one model call cost and how it went.
     *
     * @param conversation $conversation
     * @param array $input what was sent
     * @param result $result what came back
     * @param bool $replayed whether the transcript had to be sent instead of a handle
     * @return void
     */
    protected function measure(conversation $conversation, array $input, result $result, bool $replayed): void {
        [$bytes, $attachmentbytes] = metrics::measure_input($input);
        metrics::record($conversation, [
            'toolname' => $this->lasttool,
            'toolrejected' => $this->lastrejection !== null ? 1 : 0,
            'rejectreason' => $this->lastrejection,
            'requestbytes' => $bytes,
            'attachmentbytes' => $attachmentbytes,
            'inputtokens' => (int) ($result->usage['input_tokens'] ?? 0),
            'outputtokens' => (int) ($result->usage['output_tokens'] ?? 0),
            'replayed' => $replayed ? 1 : 0,
            'errorcode' => $result->errorcode,
        ]);
        $this->lasttool = null;
        $this->lastrejection = null;
    }

    /**
     * Run one aigen function and describe the outcome for the model.
     *
     * Three gates stand in front of the call, and all of them assume the model is untrusted:
     * the tool has to be one we advertise, the teacher has to hold the capability on this
     * lesson right now, and the arguments have to name this lesson and no other. The web
     * service function then applies its own capability checks on top.
     *
     * @param conversation $conversation
     * @param string $functionname
     * @param array $args
     * @return array ['text' => string, 'job' => array|null]
     */
    protected function execute(conversation $conversation, string $functionname, array $args): array {
        $this->lasttool = $functionname;
        $this->lastrejection = null;

        if (!tools::is_allowed($functionname)) {
            $this->lastrejection = metrics::REJECT_NOTALLOWED;
            return ['text' => 'Error: ' . $functionname . ' is not a tool this agent offers.', 'job' => null];
        }

        $modulecontext = \context_module::instance($conversation->cmid);
        if (!has_capability('mod/minilesson:canuseaigen', $modulecontext, $conversation->userid)) {
            $this->lastrejection = metrics::REJECT_NOCAPABILITY;
            return ['text' => 'Error: you do not have permission to use AI generation on this lesson.', 'job' => null];
        }
        if (tools::is_write($functionname) && isset($args['cmid']) && (int) $args['cmid'] !== $conversation->cmid) {
            // Writes are pinned to the lesson the teacher opened the agent from. Reads are not:
            // pulling another lesson to reuse as a template is one of the three request kinds,
            // and that function's own capability check already governs it.
            $this->lastrejection = metrics::REJECT_WRONGLESSON;
            return [
                'text' => 'Error: this conversation can only change lesson cmid ' . $conversation->cmid
                    . '. You may read other lessons, but not write to them.',
                'job' => null,
            ];
        }

        $response = facade::call($functionname, $args);
        if (!empty($response['error'])) {
            $this->lastrejection = metrics::REJECT_FAILED;
            // A rejected call is information the model can act on - usually a missing or
            // malformed argument - so it goes back as a result, not as a dead end.
            $message = $response['exception']->message ?? 'The call failed.';
            return ['text' => 'Error: ' . $message, 'job' => null];
        }

        // The teacher approved this and it ran: that is the pair of facts an administrator needs
        // later, and the reason ordinary conversation is not logged alongside it.
        if (tools::needs_approval($functionname)) {
            \mod_minilesson\event\chatagent_action_performed::create_from_action(
                $modulecontext,
                $conversation->id,
                $functionname,
                tools::summarise($functionname, $args)
            )->trigger();
        }

        // Writes that land immediately change the item list; the queued one changes it later, and
        // is reported when its job finishes instead.
        if (tools::is_write($functionname) && $functionname !== tools::ASYNC_TOOL) {
            $this->lessonchanged = true;
        }

        $data = $response['data'];
        $job = null;
        if ($functionname === tools::ASYNC_TOOL && !empty($data['jobid'])) {
            // This one queues an adhoc task rather than doing the work. If the model were left
            // to poll for the result, every poll would be another paid model call gated on cron
            // frequency, so the job is handed to the caller to watch instead.
            $job = ['jobid' => (int) $data['jobid'], 'poll' => true];
            return [
                'text' => json_encode([
                    'jobid' => $job['jobid'],
                    'status' => 'queued',
                    'note' => 'Generation has been queued and will run in the background. Do not poll for'
                        . ' its status: the teacher is shown a progress bar and the outcome will be'
                        . ' reported to you as a new message when it finishes.',
                ]),
                'job' => $job,
            ];
        }

        return ['text' => tools::encode_result($data), 'job' => null];
    }

    /**
     * The system instruction: the shared routing guidance, plus which lesson this conversation
     * is about.
     *
     * Naming the lesson is not a nicety. Without it the model has no idea which of a site's
     * lessons it is looking at, so it starts calling the listing tools to hunt for it - burning
     * the turn's tool budget on discovery and risking landing on the wrong lesson. Telling it up
     * front is also honest about the constraint the controller enforces anyway: this conversation
     * can only change this lesson.
     *
     * @param conversation $conversation
     * @return string
     */
    protected function instruction(conversation $conversation): string {
        if ($this->lessoncontext === null) {
            $this->lessoncontext = $this->describe_lesson($conversation);
        }
        return facade::agent_instructions() . "\n\n" . $this->lessoncontext;
    }

    /**
     * Describe the lesson this conversation is pinned to.
     *
     * @param conversation $conversation
     * @return string
     */
    protected function describe_lesson(conversation $conversation): string {
        global $DB;

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $conversation->cmid, 0, false, MUST_EXIST);
        $lesson = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], 'id, name', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], 'id, fullname', MUST_EXIST);
        $itemcount = $DB->count_records(constants::M_QTABLE, ['minilesson' => $lesson->id]);

        return get_string('chatagent_context', constants::M_COMPONENT, (object) [
            'lesson' => $lesson->name,
            'cmid' => $conversation->cmid,
            'course' => $course->fullname,
            'courseid' => $course->id,
            'itemcount' => $itemcount,
        ]);
    }

    /**
     * Rebuild the conversation as a single input block, for when the provider no longer holds it.
     *
     * The transcript is replayed as narrated text rather than as the provider's own step
     * objects: those are not stored, and the wording is what the conversation actually needs
     * to continue. Attachments are re-sent, since the provider has forgotten those too.
     *
     * @param conversation $conversation
     * @return array
     */
    protected function replay_input(conversation $conversation): array {
        $lines = [get_string('chatagent_replay_preamble', constants::M_COMPONENT)];
        foreach ($conversation->messages as $message) {
            switch ($message['role']) {
                case 'user':
                    $lines[] = 'Teacher: ' . $message['content'];
                    break;
                case 'assistant':
                    $lines[] = 'You: ' . $message['content'];
                    break;
                case 'tool':
                    $lines[] = 'Result of ' . $message['toolname'] . ': ' . $message['content'];
                    break;
            }
        }

        $input = [['type' => 'text', 'text' => implode("\n\n", $lines)]];
        // A conversation read back from storage records which files were attached but not the
        // files themselves, so the bytes are fetched again here - the provider has forgotten
        // them along with everything else.
        foreach (attachments::hydrate($conversation) as $attachment) {
            if (!empty($attachment['data'])) {
                $input[] = $this->attachment_block($attachment);
            }
        }
        return $input;
    }

    /**
     * Turn an attachment descriptor into an input block.
     *
     * @param array $attachment ['mimetype' => string, 'data' => base64 string]
     * @return array
     */
    protected function attachment_block(array $attachment): array {
        $mimetype = $attachment['mimetype'] ?? 'application/octet-stream';

        if (strpos($mimetype, 'image/') === 0) {
            return ['type' => 'image', 'mime_type' => $mimetype, 'data' => $attachment['data'] ?? ''];
        }

        // A lesson export is text the model has to read and rewrite, not a document to look at,
        // so it goes in as text - and through the same pruning as a tool result, because an
        // export carries its media as base64 and a lesson with images runs to megabytes of it.
        if ($mimetype === 'application/json') {
            return [
                'type' => 'text',
                'text' => $this->describe_json_attachment($attachment),
            ];
        }

        return ['type' => 'document', 'mime_type' => $mimetype, 'data' => $attachment['data'] ?? ''];
    }

    /**
     * Render an uploaded JSON file as text for the model, without its media.
     *
     * @param array $attachment
     * @return string
     */
    protected function describe_json_attachment(array $attachment): string {
        $filename = $attachment['filename'] ?? 'attachment.json';
        $raw = base64_decode($attachment['data'] ?? '', true);
        if ($raw === false || $raw === '') {
            return get_string('chatagent_attachedjsonempty', constants::M_COMPONENT, $filename);
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            // Not valid JSON after all. Pass it through as text and let the model say so.
            return get_string('chatagent_attachedfile', constants::M_COMPONENT, $filename) . "\n\n" . $raw;
        }

        $pruned = json_encode(tools::prune($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // The file itself is not capped, because stripping the media usually shrinks it by orders
        // of magnitude. What is left still can be too much - a payload of thousands of items - and
        // this is the first point at which its real size is known.
        $maxbytes = attachments::max_bytes();
        if (strlen($pruned) > $maxbytes) {
            $pruned = substr($pruned, 0, $maxbytes) . ' ...[TRUNCATED]';
            return get_string('chatagent_attachedjsontrimmed', constants::M_COMPONENT, (object) [
                'name' => $filename,
                'max' => round($maxbytes / 1048576, 1),
            ]) . "\n\n" . $pruned;
        }

        return get_string('chatagent_attachedjson', constants::M_COMPONENT, $filename) . "\n\n" . $pruned;
    }

    /**
     * Build the response the caller gets back, whatever happened.
     *
     * @param conversation $conversation
     * @param string $status one of the STATUS_* constants
     * @param array $extra merged in, for errors and queued jobs
     * @return array
     */
    protected function envelope(conversation $conversation, string $status, array $extra = []): array {
        $envelope = [
            'lessonchanged' => $this->lessonchanged,
            'conversationid' => $conversation->id,
            'status' => $status,
            'state' => $conversation->state,
            'messages' => $conversation->messages,
            'pending' => null,
            'job' => null,
        ];

        if ($conversation->pendingcallid !== '') {
            $envelope['pending'] = [
                'callid' => $conversation->pendingcallid,
                'tool' => $conversation->pendingtool,
                'args' => $conversation->pendingargs,
                'summary' => tools::summarise($conversation->pendingtool, $conversation->pendingargs),
                'needsapproval' => $status === self::STATUS_REQUIRES_APPROVAL,
            ];
        }

        return array_merge($envelope, $extra);
    }
}
