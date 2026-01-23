<?php

require('../../config.php');

$qtype   = required_param('qtype', PARAM_TEXT);
$action = required_param('action', PARAM_ALPHANUMEXT);

require_login();

$url = new moodle_url('/mod/minilesson/manage.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading($SITE->fullname);

$enabledplugin = get_config('minilesson', 'enableditems');
if (empty($enabledplugin)) {
    $enableditems = array();
} else {
    $enableditems = explode(',', $enabledplugin);
}
switch ($action) {
    case 'enable':
        if (!in_array($qtype, $enableditems)) {
            $enableditems[] = $qtype;
            $enableditems = array_unique($enableditems);
            set_config('enableditems', implode(',', $enableditems), 'minilesson');
        }
        break;
    case 'disable':
        $key = array_search($qtype, $enableditems);
        if ($key !== false) {
            unset($enableditems[$key]);
            set_config('enableditems', implode(',', $enableditems), 'minilesson');
        }
        break;
    default:
        break;
}
$returnurl = new moodle_url('/mod/minilesson/itemtypes.php');
redirect($returnurl, get_string('successfullyupdated', 'mod_minilesson'));
