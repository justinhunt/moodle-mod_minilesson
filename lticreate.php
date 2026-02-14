<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/minilesson/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;

global $DB, $CFG, $OUTPUT, $PAGE, $USER;

$launchid = required_param('launchid', PARAM_TEXT);
$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

// 1. Re-hydrate the launch
$sesscache = new launch_cache_session();
$issdb = new issuer_database(new application_registration_repository(), new deployment_repository());
$cookie = new lti_cookie();
$serviceconnector = new LtiServiceConnector($sesscache, new http_client());

try {
    $messagelaunch = LtiMessageLaunch::fromCache($launchid, $issdb, $sesscache, $cookie, $serviceconnector);
} catch (Exception $e) {
    throw new moodle_exception('Launch data missing or expired.');
}

$launchdata = $messagelaunch->getLaunchData();
$resourcelink = $launchdata['https://purl.imsglobal.org/spec/lti/claim/resource_link'] ?? [];
$resourceid = $resourcelink['id'] ?? null;

// 2. Handle Form Submission
if ($name = optional_param('name', '', PARAM_TEXT)) {
    require_sesskey();
    
    // Create the module
    $module = $DB->get_record('modules', ['name' => 'minilesson'], '*', MUST_EXIST);
    
    $modinfo = new stdClass();
    $modinfo->course = $course->id;
    $modinfo->module = $module->id;
    $modinfo->modulename = 'minilesson';
    $modinfo->section = 1; // Default to first section
    $modinfo->visible = 1;
    $modinfo->name = $name;
    if ($resourceid) {
        $modinfo->idnumber = $resourceid; // Map to LTI resource_id if available
    }
    
    // MiniLesson specific defaults
    $modinfo->grade = 100;
    
    // Use course/modlib.php to add instance
    $modinfo = add_moduleinfo($modinfo, $course);
    $cmid = $modinfo->coursemodule;
    
    // Rebuild cache
    rebuild_course_cache($course->id);
    
    // Redirect back to ltistart.php to complete deep linking
    $redirecturl = new moodle_url('/mod/minilesson/ltistart.php', [
        'launchid' => $launchid,
        'select_minilesson' => $cmid,
        'sesskey' => sesskey()
    ]);
    redirect($redirecturl);
}

// 3. Render Simple Form
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/minilesson/lticreate.php', ['launchid' => $launchid, 'courseid' => $courseid]));
$PAGE->set_title('Create New MiniLesson');
$PAGE->set_heading('Create New MiniLesson');

echo $OUTPUT->header();
echo $OUTPUT->heading('New MiniLesson');

echo '<form method="POST" class="form-inline">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="launchid" value="' . s($launchid) . '">';
echo '<input type="hidden" name="courseid" value="' . s($courseid) . '">';
echo '<div class="form-group mr-2">';
echo '<label for="name" class="mr-2">Lesson Name:</label>';
echo '<input type="text" name="name" id="name" class="form-control" required placeholder="e.g. Introduction to LTI">';
echo '</div>';
echo '<button type="submit" class="btn btn-primary">Create and Select</button>';
echo '</form>';

echo $OUTPUT->footer();
