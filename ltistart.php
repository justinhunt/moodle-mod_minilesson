<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/minilesson/lib.php');
require_once($CFG->dirroot . '/enrol/lti/lib.php');
require_once($CFG->dirroot . '/enrol/poodlllti/lib.php'); // Encure class is loaded
require_once($CFG->dirroot . '/auth/lti/auth.php'); // Ensure auth constants are loaded

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

// 2. Find/Provision Category & Course
$launchdata = $messagelaunch->getLaunchData();
$deploymentid = $launchdata['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? null;
$context = $launchdata['https://purl.imsglobal.org/spec/lti/claim/context'] ?? [];
$contextid = $context['id'] ?? null;
$customparams = $launchdata['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
$sectionid = $customparams['section_id'] ?? null;

if (empty($deploymentid) || empty($contextid)) {
    throw new moodle_exception('Missing required LTI launch parameters.');
}

$category = $DB->get_record('course_categories', ['idnumber' => $deploymentid]);
if (!$category) {
    throw new moodle_exception('Tenant category not found for deployment ID: ' . s($deploymentid));
}

$coursetargetidnumber = $deploymentid . ':' . $contextid;
$course = $DB->get_record('course', ['idnumber' => $coursetargetidnumber]);

if (!$course) {
    // Provision from Pool
    $sql = "SELECT * FROM {course} 
             WHERE category = :category 
               AND (idnumber IS NULL OR idnumber = '')
             ORDER BY sortorder ASC";
    $poolcourse = $DB->get_record_sql($sql, ['category' => $category->id], IGNORE_MULTIPLE);
    
    if (!$poolcourse) {
        throw new moodle_exception('No available pool course found in category: ' . s($category->name));
    }
    
    $poolcourse->idnumber = $coursetargetidnumber;
    $poolcourse->fullname = $context['title'] ?? ('Course ' . $contextid);
    $poolcourse->shortname = $context['label'] ?? $poolcourse->idnumber;
    $DB->update_record('course', $poolcourse);
    $course = $poolcourse;
}

// 2b. Ensure Instructor is enrolled
if (!is_enrolled(context_course::instance($course->id), $USER->id)) {
    $ltiroles = $launchdata['https://purl.imsglobal.org/spec/lti/claim/roles'] ?? [];
    $isinstructor = false;
    foreach ($ltiroles as $role) {
        if (strpos($role, 'Membership#Instructor') !== false || strpos($role, 'system/person#Administrator') !== false) {
            $isinstructor = true;
            break;
        }
    }
    
    if ($isinstructor) {
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            if ($enrol) {
                $enrolid = $enrol->add_instance($course);
                $instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
            }
        }
        if ($instance && $enrol) {
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
            $enrol->enrol_user($instance, $USER->id, $roleid);
        }
    }
}

// 3. Handle Selection
if ($selectid) {
    require_sesskey();
    
    $cm = get_coursemodule_from_id('minilesson', $selectid, $course->id, false, MUST_EXIST);
    $minilesson = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);
    $modcontext = context_module::instance($cm->id);

    $resourcelink = $launchdata['https://purl.imsglobal.org/spec/lti/claim/resource_link'] ?? [];
    $resourceid = $resourcelink['id'] ?? null;
    
    if ($resourceid && $cm->idnumber != $resourceid) {
        $DB->set_field('course_modules', 'idnumber', $resourceid, ['id' => $cm->id]);
    }

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
        
        // Get enrol_lti settings for defaults
        $lticonfig = get_config('enrol_lti');
        global $SITE;
        
        $fields = [
            'contextid' => $modcontext->id,
            'institution' => $lticonfig->institution ?? $SITE->fullname,
            'city' => $lticonfig->city ?? $CFG->defaultcity ?? '',
            'country' => $lticonfig->country ?? $CFG->country ?? 'AU',
            'roleinstructor' => 3, // Editing teacher
            'rolelearner' => 5, // Student
            'roleid' => 5, // Default role for enrolment instance (Student)
            'provisioningmodeinstructor' => auth_plugin_lti::PROVISIONING_MODE_PROMPT_NEW_EXISTING,
            'provisioningmodelearner' => auth_plugin_lti::PROVISIONING_MODE_AUTO_ONLY
        ];

        // add_instance will create the enrol record AND enrol_lti_tools record (via our lib.php overrides or parent)
        // We pass contextid to bind it to the module
        $enrolid = $plugin->add_instance($course, $fields);
        
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
        ->setCustomParams([
            'id' => $tooluuid,
            'modid' => $cm->id
        ]) // Pass UUID as 'id' and CM ID as 'modid'
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
$PAGE->set_context(context_course::instance($course->id));
$PAGE->set_url(new moodle_url('/mod/minilesson/ltistart.php', ['launchid' => $launchid]));
$PAGE->set_title('Select MiniLesson');
$PAGE->set_heading('Select MiniLesson for ' . $course->fullname);

echo $OUTPUT->header();

// Option A: Create New
echo $OUTPUT->heading('Option A: Create New', 3);
$createurl = new moodle_url('/mod/minilesson/lticreate.php', ['launchid' => $launchid, 'courseid' => $course->id]);
echo html_writer::link($createurl, 'Create New MiniLesson', ['class' => 'btn btn-primary mb-4']);

// Option B: Select Existing
echo $OUTPUT->heading('Option B: Select Existing', 3);

$modinfo = get_fast_modinfo($course);
$minilessons = $modinfo->get_instances_of('minilesson');

// Filter by section_id if available (custom logic: maybe activities have tags or groups?)
// For now, we'll just list all, but we provide the hook.
if ($sectionid) {
    // Potentially filter $minilessons here. 
    // Moodle Activities don't usually have a single 'section' field in this way, 
    // but they might belong to an actual Moodle section.
}

if (empty($minilessons)) {
    echo $OUTPUT->notification('No MiniLessons found in this course.', 'info');
} else {
    echo '<ul class="list-group">';
    foreach ($minilessons as $cm) {
        $selecturl = new moodle_url('/mod/minilesson/ltistart.php', [
            'launchid' => $launchid,
            'select_minilesson' => $cm->id,
            'sesskey' => sesskey()
        ]);
        echo '<li class="list-group-item d-flex justify-content-between align-items-center">';
        echo s($cm->name);
        echo '<a href="' . $selecturl . '" class="btn btn-secondary btn-sm">Select</a>';
        echo '</li>';
    }
    echo '</ul>';
}

echo $OUTPUT->footer();
