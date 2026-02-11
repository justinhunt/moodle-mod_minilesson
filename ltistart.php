<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/minilesson/lib.php');
require_once($CFG->dirroot . '/enrol/lti/lib.php');
require_once($CFG->dirroot . '/enrol/poodlllti/lib.php'); // Encure class is loaded

use core\http_client;
use enrol_lti\local\ltiadvantage\lib\lti_cookie;
use enrol_lti\local\ltiadvantage\lib\launch_cache_session;
use enrol_lti\local\ltiadvantage\lib\issuer_database;
use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;
use Packback\Lti1p3\LtiConstants;
use Packback\Lti1p3\DeepLinkResources\Resource;
use Packback\Lti1p3\LtiLineitem;

global $DB, $CFG, $OUTPUT, $PAGE, $USER;

require_login(null, false);

$launchid = required_param('launchid', PARAM_TEXT);
$selectid = optional_param('select_minilesson', 0, PARAM_INT);

// 1. Re-hydrate the launch
$sesscache = new launch_cache_session();
$issdb = new issuer_database(new application_registration_repository(), new deployment_repository());
$cookie = new lti_cookie();
$serviceconnector = new LtiServiceConnector($sesscache, new http_client());

try {
    $messagelaunch = LtiMessageLaunch::fromCache($launchid, $issdb, $sesscache, $cookie, $serviceconnector);
} catch (Exception $e) {
    throw new moodle_exception('Launch data missing or expired. Please launch again.');
}

if (!$messagelaunch->isDeepLinkLaunch()) {
    throw new moodle_exception('Not a deep link launch.');
}

$launchdata = $messagelaunch->getLaunchData();
$deploymentid = $launchdata['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? null;

if (empty($deploymentid)) {
    throw new moodle_exception('No deployment ID in launch data.');
}

// 2. Find Course
$course = $DB->get_record('course', ['idnumber' => $deploymentid]);
if (!$course) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification("No course found with ID number matching deployment ID: " . s($deploymentid), 'notifyproblem');
    echo $OUTPUT->footer();
    die();
}

// 3. Handle Selection
if ($selectid) {
    require_sesskey();
    
    $cm = get_coursemodule_from_id('minilesson', $selectid, $course->id, false, MUST_EXIST);
    $minilesson = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);
    $modcontext = context_module::instance($cm->id);

    // Find or Create enrol_poodlllti tool for this module
    $toolid = null;
    $tooluuid = null;

    // Look for existing tool
    $sql = "SELECT t.* 
              FROM {enrol_lti_tools} t
              JOIN {enrol} e ON e.id = t.enrolid
             WHERE e.enrol = :enrol 
               AND t.contextid = :contextid
               AND t.ltiversion = :ltiversion";
    $params = [
        'enrol' => 'poodlllti',
        'contextid' => $modcontext->id,
        'ltiversion' => 'LTI-1p3'
    ];
    
    $existing = $DB->get_record_sql($sql, $params);
    
    if ($existing) {
        $toolid = $existing->id;
        $tooluuid = $existing->uuid;
        // Ensure user is enrolled or enrol instance is active? 
        // We assume existing instance is fine.
    } else {
        // Create new instance
        $plugin = enrol_get_plugin('poodlllti');
        // add_instance will create the enrol record AND enrol_lti_tools record (via our lib.php overrides or parent)
        // We pass contextid to bind it to the module
        $enrolid = $plugin->add_instance($course, ['contextid' => $modcontext->id]);
        
        // Fetch the created tool
        $newtool = $DB->get_record('enrol_lti_tools', ['enrolid' => $enrolid], '*', MUST_EXIST);
        $toolid = $newtool->id;
        $tooluuid = $newtool->uuid;
    }

    // Create Resource
    // Use the generic launch.php but maybe we don't need it if we use standard LTI launch?
    // enrol_lti/launch.php works if we send the right UUID.
    // But we are building "enrol_poodlllti".
    // Does enrol_lti/launch.php support 'poodlllti' plugin type?
    // It checks 'enrol' table 'enrol' field?
    // enrol/lti/launch.php -> $tool = $DB->get_record('enrol_lti_tools', ...) -> $enrol = $DB->get_record('enrol', ['id'=>$tool->enrolid]) -> check is_enabled($enrol->enrol).
    // So YES, enrol_lti/launch.php SHOULD work for poodlllti if poodlllti plugin is enabled!
    // BUT we want to use OUR launch.php if we customized anything.
    // Our launch.php is just a wrapper.
    // Let's use OUR launch.php for control.
    
    // Resource URL: .../enrol/poodlllti/launch.php (without params, params in custom)
    // Actually, enrol_lti uses 'id' parameter in URL or POST body as the UUID.
    
    $resourceurl = $CFG->wwwroot . '/enrol/poodlllti/launch.php';
    
    $resource = Resource::new()
        ->setUrl($resourceurl)
        ->setCustomParams(['id' => $tooluuid]) // Pass UUID as 'id'
        ->setTitle($minilesson->name);
        
    // AGS Support
    if (isset($minilesson->grade) && $minilesson->grade != 0) {
       $lineitem = LtiLineitem::new()
           ->setScoreMaximum((float)$minilesson->grade)
           ->setResourceId($tooluuid); // ResourceId usually matches the tool ID/UUID
           
       $resource->setLineitem($lineitem);
    }

    $dl = $messagelaunch->getDeepLink();
    $responsejwt = $dl->getResponseJwt([$resource]);
    $returnurl = $launchdata[LtiConstants::DL_DEEP_LINK_SETTINGS]['deep_link_return_url'];

    echo "<form id='autosubmit' action='" . s($returnurl) . "' method='POST'>";
    echo "<input type='hidden' name='JWT' value='" . s($responsejwt) . "'>";
    echo "</form>";
    echo "<script>document.getElementById('autosubmit').submit();</script>";
    die();
}

// 4. List Activities
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/minilesson/ltistart.php', ['launchid' => $launchid]));
$PAGE->set_title('Select MiniLesson');
$PAGE->set_heading('Select MiniLesson for ' . $course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading('Available MiniLessons');

$modinfo = get_fast_modinfo($course);
$minilessons = $modinfo->get_instances_of('minilesson');

if (empty($minilessons)) {
    echo $OUTPUT->notification('No MiniLessons found in this course.', 'warning');
} else {
    echo '<ul>';
    foreach ($minilessons as $cm) {
        $selecturl = new moodle_url('/mod/minilesson/ltistart.php', [
            'launchid' => $launchid,
            'select_minilesson' => $cm->id,
            'sesskey' => sesskey()
        ]);
        echo '<li>';
        echo '<a href="' . $selecturl . '" class="btn btn-secondary">' . s($cm->name) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

echo $OUTPUT->footer();
