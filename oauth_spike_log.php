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
 * THROWAWAY SPIKE - read the spike log and current state.
 *
 * Site admins only. This is the readout: what each client fetched, in what order, with
 * which parameters. Reading this top to bottom after a connection attempt answers the
 * spike's questions.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/oauth_spike_lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());
ml_spike_require_enabled();

$clear = optional_param('clear', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/minilesson/oauth_spike_log.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('OAuth spike log');

if ($clear) {
    require_sesskey();
    @unlink(ml_spike_dir() . '/spike.log');
    @unlink(ml_spike_dir() . '/state.json');
    redirect($PAGE->url, 'Spike log and state cleared.');
}

echo $OUTPUT->header();
echo $OUTPUT->heading('OAuth spike log');

// The URLs under test, so they can be checked by hand or with curl.
echo html_writer::start_div('card p-3 mb-3');
echo html_writer::tag('h4', 'Endpoints under test');
$urls = [
    'Issuer' => ml_spike_issuer(),
    'AS metadata (OIDC path-appended - the one that matters)' => ml_spike_issuer() . '/.well-known/openid-configuration',
    'AS metadata (oauth-authorization-server path-appended)' => ml_spike_issuer() . '/.well-known/oauth-authorization-server',
    'Protected resource metadata' => ml_spike_prm_url(),
    'Resource (MCP endpoint)' => ml_spike_resource(),
];
echo html_writer::start_tag('ul');
foreach ($urls as $label => $url) {
    echo html_writer::tag('li', s($label) . ': <code>' . s($url) . '</code>');
}
echo html_writer::end_tag('ul');
echo html_writer::tag('p', 'These two are at the site root and <strong>cannot</strong> be served by a plugin. '
    . 'Check the web server access log to see whether a client tried them first:');
echo html_writer::start_tag('ul');
foreach ([
    $CFG->wwwroot . '/.well-known/oauth-authorization-server/mod/minilesson/oauth_spike.php',
    $CFG->wwwroot . '/.well-known/openid-configuration/mod/minilesson/oauth_spike.php',
] as $url) {
    echo html_writer::tag('li', '<code>' . s($url) . '</code>');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();

// Current state: who has registered, what is outstanding.
$state = ml_spike_state_read();
echo html_writer::start_div('card p-3 mb-3');
echo html_writer::tag('h4', 'Registered clients (' . count($state['clients']) . ')');
if (empty($state['clients'])) {
    echo html_writer::tag('p', 'None yet.');
} else {
    foreach ($state['clients'] as $id => $client) {
        echo html_writer::tag('p', '<strong>' . s($client['client_name'] ?? $id) . '</strong> '
            . '(<code>' . s($id) . '</code>, ' . s($client['source'] ?? '?') . ')<br>'
            . 'redirect_uris: <code>' . s(implode(' | ', $client['redirect_uris'] ?? [])) . '</code><br>'
            . 'auth method: <code>' . s($client['token_endpoint_auth_method'] ?? '?') . '</code>');
    }
}
echo html_writer::tag('p', 'Live authorization codes: ' . count($state['codes'])
    . ' &middot; refresh tokens: ' . count($state['refresh']));
echo html_writer::end_div();

// The log itself, newest first.
$logfile = ml_spike_dir() . '/spike.log';
$lines = file_exists($logfile) ? array_filter(explode("\n", file_get_contents($logfile))) : [];
echo $OUTPUT->heading('Requests (' . count($lines) . ', newest first)', 3);

if (empty($lines)) {
    echo html_writer::tag('p', 'Nothing logged yet. Connect a client and reload this page.');
} else {
    echo html_writer::start_tag('div', ['style' => 'font-family:monospace;font-size:12px']);
    foreach (array_reverse($lines) as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            continue;
        }
        $useragent = $entry['headers']['User-Agent'] ?? '(no user agent)';
        echo html_writer::start_div('border rounded p-2 mb-2');
        echo html_writer::tag('div', '<strong>' . s($entry['event']) . '</strong> &middot; '
            . s($entry['time']) . ' &middot; ' . s($entry['method']) . ' ' . s($entry['uri']));
        echo html_writer::tag('div', 'PATH_INFO: <code>' . s($entry['pathinfo']) . '</code> &middot; from '
            . s($entry['ip']) . ' &middot; ' . s($useragent), ['style' => 'color:#666']);
        echo html_writer::tag('pre', s(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            ['style' => 'white-space:pre-wrap;margin:.5rem 0 0 0']);
        echo html_writer::end_div();
    }
    echo html_writer::end_tag('div');
}

echo $OUTPUT->single_button(
    new moodle_url($PAGE->url, ['clear' => 1, 'sesskey' => sesskey()]),
    'Clear log and state',
    'post'
);

echo $OUTPUT->footer();
