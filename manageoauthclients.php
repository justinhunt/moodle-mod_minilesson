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
 * Manage OAuth clients admin page.
 *
 * Lists DCR-registered and manually-created OAuth clients for the MCP authorization
 * server, and lets an admin create/delete manual clients (public or confidential) - the
 * fallback path for clients like Gemini Spark that, per its own help docs, ask an admin to
 * paste in a client_id/secret manually when a server doesn't support Dynamic Client
 * Registration.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;
use mod_minilesson\local\oauth\helper;

require_once(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('mod_minilesson_manageoauthclients');
$PAGE->set_heading(get_string('manageoauthclients', constants::M_COMPONENT));

$action = optional_param('action', '', PARAM_ALPHA);
$actionclientid = optional_param('clientid', '', PARAM_RAW_TRIMMED);
$thisurl = new moodle_url(constants::M_URL . '/manageoauthclients.php');

if ($action === 'create') {
    require_sesskey();
    $name = clean_param(required_param('clientname', PARAM_RAW_TRIMMED), PARAM_TEXT);
    $urislines = required_param('redirecturis', PARAM_RAW_TRIMMED);
    $confidential = optional_param('confidential', 0, PARAM_BOOL);

    $uris = array_values(array_filter(array_map('trim', explode("\n", $urislines)), 'strlen'));
    $allvalid = !empty($uris);
    foreach ($uris as $uri) {
        if (!helper::valid_redirect_uri($uri)) {
            $allvalid = false;
            break;
        }
    }
    if (!$allvalid) {
        redirect(
            $thisurl,
            get_string('manageoauthclients_invaliduris', constants::M_COMPONENT),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    $newclientid = 'mlc_' . random_string(32);
    $secret = $confidential ? random_string(48) : null;
    $DB->insert_record('minilesson_oauth_clients', (object) [
        'clientid' => $newclientid,
        'clientsecrethash' => $secret !== null ? password_hash($secret, PASSWORD_DEFAULT) : null,
        'clientname' => $name,
        'clienturi' => null,
        'logouri' => null,
        'redirecturis' => json_encode($uris),
        'granttypes' => 'authorization_code,refresh_token',
        'responsetypes' => 'code',
        'tokenendpointauthmethod' => $confidential ? 'client_secret_post' : 'none',
        'scope' => null,
        'origin' => 'manual',
        'createdby' => $USER->id,
        'timecreated' => time(),
        'timemodified' => time(),
        'lastusedtime' => null,
    ]);

    if ($secret !== null) {
        $SESSION->minilesson_oauth_newsecret = (object) ['clientid' => $newclientid, 'secret' => $secret];
    }
    redirect(
        $thisurl,
        get_string('manageoauthclients_created', constants::M_COMPONENT),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'regenerate' && $actionclientid !== '') {
    require_sesskey();
    $client = $DB->get_record('minilesson_oauth_clients', ['clientid' => $actionclientid]);
    if ($client && $client->tokenendpointauthmethod === 'client_secret_post') {
        $secret = random_string(48);
        $client->clientsecrethash = password_hash($secret, PASSWORD_DEFAULT);
        $client->timemodified = time();
        $DB->update_record('minilesson_oauth_clients', $client);
        $SESSION->minilesson_oauth_newsecret = (object) ['clientid' => $actionclientid, 'secret' => $secret];
    }
    redirect(
        $thisurl,
        get_string('manageoauthclients_regenerated', constants::M_COMPONENT),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'delete' && $actionclientid !== '') {
    require_sesskey();
    // Deleting a client kills its outstanding grants immediately rather than leaving them
    // to expire naturally.
    $DB->delete_records('minilesson_oauth_clients', ['clientid' => $actionclientid]);
    $DB->delete_records('minilesson_oauth_codes', ['clientid' => $actionclientid]);
    $DB->delete_records('minilesson_oauth_refresh', ['clientid' => $actionclientid]);
    redirect(
        $thisurl,
        get_string('manageoauthclients_deleted', constants::M_COMPONENT),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

$newsecret = null;
if (!empty($SESSION->minilesson_oauth_newsecret)) {
    $newsecret = $SESSION->minilesson_oauth_newsecret;
    unset($SESSION->minilesson_oauth_newsecret);
}

$clients = $DB->get_records('minilesson_oauth_clients', [], 'timecreated DESC');
$clientrows = [];
foreach ($clients as $client) {
    $uris = json_decode($client->redirecturis, true) ?: [];
    $clientrows[] = [
        'clientid' => $client->clientid,
        'clientname' => $client->clientname ?: get_string('oauthunnamedclient', constants::M_COMPONENT),
        'isdcr' => $client->origin === 'dcr',
        'confidential' => $client->tokenendpointauthmethod === 'client_secret_post',
        'redirecturis' => implode(', ', $uris),
        'created' => userdate($client->timecreated),
        'deleteurl' => (new moodle_url($thisurl, [
            'action' => 'delete',
            'clientid' => $client->clientid,
            'sesskey' => sesskey(),
        ]))->out(false),
        'regenerateurl' => (new moodle_url($thisurl, [
            'action' => 'regenerate',
            'clientid' => $client->clientid,
            'sesskey' => sesskey(),
        ]))->out(false),
    ];
}

$templatedata = [
    'intro' => get_string('manageoauthclients_intro', constants::M_COMPONENT),
    'createheading' => get_string('manageoauthclients_createheading', constants::M_COMPONENT),
    'createformurl' => $thisurl->out(false),
    'sesskey' => sesskey(),
    'namelabel' => get_string('manageoauthclients_namelabel', constants::M_COMPONENT),
    'redirecturislabel' => get_string('manageoauthclients_redirecturislabel', constants::M_COMPONENT),
    'confidentiallabel' => get_string('manageoauthclients_confidentiallabel', constants::M_COMPONENT),
    'createbtn' => get_string('manageoauthclients_createbtn', constants::M_COMPONENT),
    'hasnewsecret' => $newsecret !== null,
    'newsecret' => $newsecret->secret ?? null,
    'newsecretclientid' => $newsecret->clientid ?? null,
    'listheading' => get_string('manageoauthclients_listheading', constants::M_COMPONENT),
    'hasclients' => !empty($clientrows),
    'noclientsmessage' => get_string('manageoauthclients_noclients', constants::M_COMPONENT),
    'namecollabel' => get_string('manageoauthclients_namelabel', constants::M_COMPONENT),
    'typecollabel' => get_string('manageoauthclients_typecollabel', constants::M_COMPONENT),
    'redirectscollabel' => get_string('manageoauthclients_redirecturislabel', constants::M_COMPONENT),
    'createdcollabel' => get_string('manageoauthclients_createdcollabel', constants::M_COMPONENT),
    'dcrlabel' => get_string('manageoauthclients_dcrlabel', constants::M_COMPONENT),
    'publiclabel' => get_string('manageoauthclients_publiclabel', constants::M_COMPONENT),
    'confidentialtypelabel' => get_string('manageoauthclients_confidentialtypelabel', constants::M_COMPONENT),
    'deletelabel' => get_string('delete'),
    'regeneratelabel' => get_string('manageoauthclients_regeneratelabel', constants::M_COMPONENT),
    'clients' => $clientrows,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageoauthclients', constants::M_COMPONENT));
echo $renderer->render_manageoauthclients($templatedata);
echo $OUTPUT->footer();
