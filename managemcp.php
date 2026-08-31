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
 * Manage MCP admin page.
 *
 * Explains MiniLesson MCP, lets an admin create the system-context
 * "MiniLesson MCP User" role (carrying mod/minilesson:usemcp), migrate the
 * aigenservice external service's legacy allowed-users list into that role,
 * and shows who currently holds the capability and their token status.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;

require_once(dirname(__FILE__, 3) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('mod_minilesson_managemcp');


$action = optional_param('action', '', PARAM_ALPHA);

$capability = 'mod/minilesson:usemcp';
$roleshortname = 'minilessonmcpuser';
$rolename = get_string('mcpuserrolename', constants::M_COMPONENT);
$systemcontext = context_system::instance();
$thisurl = new moodle_url(constants::M_URL . '/managemcp.php');
$helpurl = 'https://support.poodll.com/a/solutions/articles/19000175579';

$serviceid = $DB->get_field('external_services', 'id', ['shortname' => 'aigenservice']);
$allowedusers = $serviceid ? $DB->get_records('external_services_users', ['externalserviceid' => $serviceid]) : [];
$role = $DB->get_record('role', ['shortname' => $roleshortname]);

if ($action === 'createrole' && !$role) {
    require_sesskey();
    $roleid = create_role($rolename, $roleshortname, get_string('mcpuserroledesc', constants::M_COMPONENT));
    set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
    assign_capability($capability, CAP_ALLOW, $roleid, $systemcontext->id, true);
    redirect(
        $thisurl,
        get_string('managemcp_rolecreated', constants::M_COMPONENT, $rolename),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else if ($action === 'migrateusers' && $role && !empty($allowedusers)) {
    require_sesskey();

    $migrated = 0;
    $alreadyhadrole = 0;
    foreach ($allowedusers as $alloweduser) {
        $hadrole = $DB->record_exists('role_assignments', [
            'roleid' => $role->id,
            'userid' => $alloweduser->userid,
            'contextid' => $systemcontext->id,
        ]);
        role_assign($role->id, $alloweduser->userid, $systemcontext->id);
        if ($hadrole) {
            $alreadyhadrole++;
        } else {
            $migrated++;
        }
    }

    redirect($thisurl, get_string('managemcp_migratedone', constants::M_COMPONENT, (object) [
        'migrated' => $migrated,
        'skipped' => $alreadyhadrole,
        'rolename' => $rolename,
    ]), null, \core\output\notification::NOTIFY_SUCCESS);
}

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

$createbutton = new \single_button(
    new moodle_url($thisurl, ['action' => 'createrole', 'sesskey' => sesskey()]),
    get_string('managemcp_createrolebtn', constants::M_COMPONENT, $rolename),
    'post'
);
if ($role) {
    $createbutton->disabled = true;
} else {
    $createbutton->add_confirm_action(get_string('managemcp_createroleconfirm', constants::M_COMPONENT, $rolename));
}

$migratebutton = new \single_button(
    new moodle_url($thisurl, ['action' => 'migrateusers', 'sesskey' => sesskey()]),
    get_string('managemcp_migratebtn', constants::M_COMPONENT, count($allowedusers)),
    'post'
);
$migratedisabled = !$role || empty($allowedusers);
if ($migratedisabled) {
    $migratebutton->disabled = true;
} else {
    $migratebutton->add_confirm_action(get_string('managemcp_migrateconfirm', constants::M_COMPONENT, count($allowedusers)));
}

if (!$role) {
    $migratedisabledreason = get_string('managemcp_migratedisabled_norole', constants::M_COMPONENT);
} else if (empty($allowedusers)) {
    $migratedisabledreason = get_string('managemcp_migratedisabled_nousers', constants::M_COMPONENT);
} else {
    $migratedisabledreason = '';
}

$namefields = \core_user\fields::for_name()->get_sql('u')->selects;
$mcpuserfields = 'u.id, u.email' . $namefields;
$mcpusers = get_users_by_capability($systemcontext, $capability, $mcpuserfields, 'u.lastname, u.firstname');
$userrows = [];
foreach ($mcpusers as $mcpuser) {
    $tokenparams = ['userid' => $mcpuser->id, 'externalserviceid' => $serviceid];
    $token = $serviceid ? $DB->get_record('external_tokens', $tokenparams, '*', IGNORE_MULTIPLE) : false;
    if ($token) {
        $hastoken = get_string('yes');
        $tokenexpiry = $token->validuntil
            ? userdate($token->validuntil)
            : get_string('managemcp_neverexpires', constants::M_COMPONENT);
    } else {
        $hastoken = get_string('no');
        $tokenexpiry = '-';
    }
    $userrows[] = [
        'fullname' => fullname($mcpuser),
        'email' => $mcpuser->email,
        'hastoken' => $hastoken,
        'tokenexpiry' => $tokenexpiry,
    ];
}

$templatedata = [
    'intro' => get_string('managemcp_intro', constants::M_COMPONENT),
    'enableheading' => get_string('managemcp_enableheading', constants::M_COMPONENT),
    'enablebody' => get_string('managemcp_enablebody', constants::M_COMPONENT, $rolename),
    'helpurl' => $helpurl,
    'helplinktext' => get_string('managemcp_helplink', constants::M_COMPONENT),
    'hasoauthplugin' => class_exists('\local_oauthmcp\api'),
    'oauthclientsurl' => (new moodle_url('/local/oauthmcp/manageoauthclients.php'))->out(false),
    'oauthclientslinktext' => get_string('managemcp_oauthclientslink', constants::M_COMPONENT),
    'oauthpluginmissing' => get_string('managemcp_oauthpluginmissing', constants::M_COMPONENT),
    'oauthrequirements' => get_string('managemcp_oauthrequirements', constants::M_COMPONENT),
    'createroleheading' => get_string('managemcp_createroleheading', constants::M_COMPONENT),
    'createrolebody' => get_string('managemcp_createrolebody', constants::M_COMPONENT, (object) [
        'rolename' => $rolename,
        'capability' => $capability,
    ]),
    'createrolebuttonhtml' => $renderer->render($createbutton),
    'rolealreadyexists' => (bool) $role,
    'rolealreadyexiststext' => $role
        ? get_string('managemcp_rolealreadyexists', constants::M_COMPONENT, $rolename)
        : '',
    'migrateheading' => get_string('managemcp_migrateheading', constants::M_COMPONENT),
    'migratebody' => get_string('managemcp_migratebody', constants::M_COMPONENT),
    'migratebuttonhtml' => $renderer->render($migratebutton),
    'migratedisabled' => $migratedisabled,
    'migratedisabledreason' => $migratedisabledreason,
    'usersheading' => get_string('managemcp_usersheading', constants::M_COMPONENT),
    'hasusers' => !empty($userrows),
    'nousersmessage' => get_string('managemcp_nousers', constants::M_COMPONENT),
    'fullnamelabel' => get_string('fullname'),
    'emaillabel' => get_string('email'),
    'hastokenlabel' => get_string('managemcp_hastoken', constants::M_COMPONENT),
    'tokenexpirylabel' => get_string('managemcp_tokenexpiry', constants::M_COMPONENT),
    'users' => $userrows,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managemcp', constants::M_COMPONENT));
echo $renderer->render_managemcp($templatedata);
echo $OUTPUT->footer();
