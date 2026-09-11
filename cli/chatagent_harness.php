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
 * Drive the MiniLesson agent loop from the command line, with no UI and no database tables.
 *
 * The point of this script is to prove the loop before anything is built on top of it: the
 * conversation is kept in a JSON file rather than in the tables, so the controller, the driver
 * and the tool layer can be exercised, broken and fixed on their own. Everything it does here
 * is what the web services will do later.
 *
 * Usage:
 *   php cli/chatagent_harness.php --cmid=123 --user=admin --message="A lesson on ordering coffee"
 *   php cli/chatagent_harness.php --approve            # run the tool the assistant asked to run
 *   php cli/chatagent_harness.php --reject             # decline it instead
 *   php cli/chatagent_harness.php --forget --message="carry on"   # drop the provider's handle,
 *                                                             # forcing the replay path
 *   php cli/chatagent_harness.php --show               # print the transcript
 *   php cli/chatagent_harness.php --reset              # start over
 *
 * Provider. The site's own Gemini key unless told otherwise:
 *   --provider=cloudpoodll   go through Cloud Poodll over HTTP, with the site's Poodll credentials
 *   --loopback --cpuser=<username>
 *               go through Cloud Poodll in-process, as that Cloud Poodll account, on a dev site
 *               that has local_cpapi installed (see local/cpapi/cli/chatagent_probe.php --setup)
 *
 * Failure paths, so the messages a teacher would see can be checked without waiting for a real
 * outage or clobbering the site's API key:
 *   --badkey    send a bogus API key           (exercises the auth path against the live API)
 *   --badhost   point at an unresolvable host  (exercises the connection path)
 *   --cpbadtoken  send Cloud Poodll a token it will refuse (exercises the refresh-and-retry, then
 *                 the auth path, against the real server)
 *   --simulate=quota|auth|connection|expired|transport|unknown
 *               make the provider return that failure without calling it at all
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// Behave as a web service entry point, as mcp.php does. call_external_function() otherwise
// demands a logged-in session and a sesskey, neither of which a CLI script has - and behind
// that gate the aigen functions still run their own validate_context() and capability checks
// against the user set below, which is the protection that actually matters here. The web
// services in step 4 will not need this: they are called from a browser that has both.
define('WS_SERVER', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_minilesson\local\chatagent\conversation;
use mod_minilesson\local\chatagent\controller;
use mod_minilesson\local\chatagent\cloudpoodll_driver;
use mod_minilesson\local\chatagent\cloudpoodll_loopback_driver;
use mod_minilesson\local\chatagent\failing_driver;
use mod_minilesson\local\chatagent\gemini_driver;
use mod_minilesson\local\chatagent\misconfigured_gemini_driver;

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'cmid' => 0,
        'user' => 'admin',
        'message' => '',
        'attach' => '',
        'approve' => false,
        'reject' => false,
        'forget' => false,
        'show' => false,
        'reset' => false,
        'state' => '',
        'maxsteps' => 12,
        'badkey' => false,
        'badhost' => false,
        'simulate' => '',
        'provider' => 'ownkey',
        'loopback' => false,
        'cpuser' => '',
        'cpbadtoken' => false,
    ],
    ['h' => 'help', 'm' => 'message']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help']) {
    cli_writeln(file_get_contents(__FILE__, false, null, 0, 3400));
    exit(0);
}

// Where the conversation lives between invocations. One file per site by default, which is
// all a single developer driving one conversation at a time needs.
$statefile = $options['state'] ?: ($CFG->tempdir . '/minilesson_chatagent_harness.json');

if ($options['reset']) {
    if (file_exists($statefile)) {
        unlink($statefile);
    }
    cli_writeln('Conversation reset.');
    exit(0);
}

// Act as a real user: the controller checks capabilities on the module context, and the aigen
// functions check their own on top of that.
$user = $DB->get_record('user', ['username' => $options['user']], '*', MUST_EXIST);
\core\session\manager::set_user($user);

$conversation = load_session($statefile);

if ($options['cmid']) {
    $conversation->cmid = (int) $options['cmid'];
}
if (!$conversation->cmid) {
    cli_error('No lesson yet. Pass --cmid=<course module id> the first time.');
}
$conversation->userid = $user->id;

$cm = get_coursemodule_from_id('minilesson', $conversation->cmid, 0, false, MUST_EXIST);
$modulecontext = context_module::instance($cm->id);
if (!has_capability('mod/minilesson:canuseaigen', $modulecontext, $user->id)) {
    cli_error($user->username . ' cannot use AI generation on cmid ' . $conversation->cmid . '.');
}

if ($options['show']) {
    print_transcript($conversation);
    exit(0);
}

if ($options['simulate'] !== '') {
    cli_writeln('-- provider will fail with: ' . $options['simulate'] . ' --');
    $driver = new failing_driver($options['simulate']);
} else if ($options['badkey']) {
    cli_writeln('-- using a bogus API key --');
    $driver = new misconfigured_gemini_driver(misconfigured_gemini_driver::MODE_BADKEY);
} else if ($options['badhost']) {
    cli_writeln('-- pointing at an unresolvable host --');
    $driver = new misconfigured_gemini_driver(misconfigured_gemini_driver::MODE_BADHOST);
} else if ($options['loopback']) {
    if ($options['cpuser'] === '') {
        cli_error('--loopback needs --cpuser=<Cloud Poodll account username>.');
    }
    cli_writeln('-- Cloud Poodll, in-process, as ' . $options['cpuser'] . ' --');
    $driver = new cloudpoodll_loopback_driver($options['cpuser']);
} else if ($options['cpbadtoken']) {
    cli_writeln('-- Cloud Poodll, with a token it will refuse --');
    $driver = new class extends cloudpoodll_driver {
        /**
         * A token no server issued.
         *
         * @param bool $force
         * @return string
         */
        protected function token(bool $force) {
            return 'not-a-real-token';
        }
    };
} else if ($options['provider'] === 'cloudpoodll') {
    cli_writeln('-- Cloud Poodll, over HTTP --');
    $driver = new cloudpoodll_driver();
} else {
    $driver = new gemini_driver();
}
if (!$driver->is_available()) {
    cli_error('The provider has no credentials: a Gemini API key (geminiapikey) for ownkey, or a Poodll '
        . 'API user and secret for cloudpoodll.');
}
$agent = new controller($driver);

if ($options['forget']) {
    // Simulate the provider's retention window expiring, so the replay path gets exercised
    // deliberately rather than only in the wild a day after a conversation starts.
    cli_writeln('-- dropping the stored interaction id; this turn must replay the transcript --');
    $conversation->interactionid = '';
}

// Work out what this invocation is: an answer to an approval card, or a new message.
if ($options['approve'] || $options['reject']) {
    if ($conversation->pendingcallid === '') {
        cli_error('Nothing is waiting for approval.');
    }
    cli_writeln(($options['approve'] ? '-- approving ' : '-- declining ') . $conversation->pendingtool . ' --');
    $envelope = $agent->approve($conversation, $conversation->pendingcallid, (bool) $options['approve']);
} else if ($options['message'] !== '') {
    $attachments = [];
    if ($options['attach'] !== '') {
        $attachments[] = read_attachment($options['attach']);
        cli_writeln('-- attaching ' . basename($options['attach']) . ' --');
    }
    cli_writeln('Teacher: ' . $options['message']);
    $envelope = $agent->send_message($conversation, $options['message'], $attachments);
} else {
    cli_error('Nothing to do. Pass --message="...", --approve, --reject, --show or --reset.');
}

// Drive the loop the way the browser will: keep stepping while there is a tool to run, and
// stop when the assistant has answered or is waiting on the teacher.
$steps = 0;
while (true) {
    report($envelope);
    save_session($statefile, $conversation);

    if ($envelope['status'] !== controller::STATUS_REQUIRES_TOOL) {
        break;
    }
    if (++$steps > (int) $options['maxsteps']) {
        cli_writeln('-- harness step limit reached --');
        break;
    }
    $envelope = $agent->step($conversation);
}

exit($envelope['status'] === controller::STATUS_ERROR ? 1 : 0);

/**
 * Print what one call to the loop produced.
 *
 * @param array $envelope
 * @return void
 */
function report(array $envelope): void {
    static $shown = 0;

    // Only the messages added since the last report, so a multi-step turn reads as a sequence.
    $messages = array_slice($envelope['messages'], $shown);
    $shown = count($envelope['messages']);

    foreach ($messages as $message) {
        switch ($message['role']) {
            case 'assistant':
                cli_writeln("\nAssistant: " . $message['content']);
                break;
            case 'tool':
                cli_writeln('  [' . $message['toolname'] . '] ' . shorten_text($message['content'], 300));
                break;
        }
    }

    if (!empty($envelope['job'])) {
        cli_writeln('  [job ' . $envelope['job']['jobid'] . ' queued - run cron, then report the outcome]');
    }

    switch ($envelope['status']) {
        case controller::STATUS_REQUIRES_TOOL:
            cli_writeln('  -> running ' . $envelope['pending']['tool']);
            break;
        case controller::STATUS_REQUIRES_APPROVAL:
            cli_writeln("\nAPPROVAL NEEDED: " . $envelope['pending']['summary']);
            cli_writeln(json_encode($envelope['pending']['args'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            cli_writeln('Run again with --approve or --reject.');
            break;
        case controller::STATUS_ERROR:
            cli_writeln("\nERROR (" . ($envelope['errorcode'] ?? 'none') . '): ' . ($envelope['error'] ?? ''));
            break;
    }
}

/**
 * Print the whole conversation so far.
 *
 * @param conversation $conversation
 * @return void
 */
function print_transcript(conversation $conversation): void {
    cli_writeln('cmid ' . $conversation->cmid . ', state ' . $conversation->state
        . ', interaction ' . ($conversation->interactionid ?: '(none - next turn replays)'));
    foreach ($conversation->messages as $message) {
        $label = $message['role'] === 'tool' ? '[' . $message['toolname'] . ']' : ucfirst($message['role']) . ':';
        cli_writeln($label . ' ' . shorten_text($message['content'], 400));
    }
}

/**
 * Load the conversation from disk, or start a new one.
 *
 * @param string $statefile
 * @return conversation
 */
function load_session(string $statefile): conversation {
    if (!file_exists($statefile)) {
        return new conversation();
    }
    $data = json_decode(file_get_contents($statefile), true);
    return is_array($data) ? conversation::from_array($data) : new conversation();
}

/**
 * Write the conversation back to disk.
 *
 * @param string $statefile
 * @param conversation $conversation
 * @return void
 */
function save_session(string $statefile, conversation $conversation): void {
    file_put_contents($statefile, json_encode($conversation->to_array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Read a local file into the attachment descriptor the controller expects.
 *
 * @param string $path
 * @return array
 */
function read_attachment(string $path): array {
    if (!is_readable($path)) {
        cli_error('Cannot read ' . $path);
    }
    $mimetype = mime_content_type($path) ?: 'application/octet-stream';
    return [
        'id' => sha1_file($path),
        'mimetype' => $mimetype,
        'data' => base64_encode(file_get_contents($path)),
    ];
}
