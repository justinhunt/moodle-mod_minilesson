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

namespace mod_minilesson\local;

use mod_minilesson\constants;

/**
 * Community page helper: sharing consent, eligible submissions and likes.
 *
 * The community page shows other students' submissions on an item (currently
 * free speaking, later free writing too). A submission is shown only when the
 * student consented AND their stored step grade meets the threshold AND their
 * recording has a usable (http) media url.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cpage {
    /** @var int minimum step grade (percent) for a submission to be shareable */
    public const ELIGIBLE_GRADE = 80;

    /** @var string sort newest first (default) */
    public const SORT_DATE = 'date';

    /** @var string sort by like count */
    public const SORT_LIKES = 'likes';

    /** @var int hard cap on entries returned to the page */
    public const MAX_ENTRIES = 100;

    /**
     * Is the community page feature enabled site wide?
     *
     * @return bool
     */
    public static function is_enabled_sitewide() {
        return !empty(get_config(constants::M_COMPONENT, 'enablecommunitypage'));
    }

    /**
     * Fetch the item record, module instance, cm and context for an itemid.
     *
     * @param int $itemid id of the minilesson_rsquestions row
     * @return array [itemrecord, moduleinstance, cm, context]
     */
    public static function fetch_item_environment($itemid) {
        global $DB;
        $itemrecord = $DB->get_record(constants::M_QTABLE, ['id' => $itemid], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $itemrecord->minilesson], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(constants::M_MODNAME, $moduleinstance->id, $moduleinstance->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        return [$itemrecord, $moduleinstance, $cm, $context];
    }

    /**
     * Get a user's consent/likes row for an item, or false.
     *
     * @param int $itemid
     * @param int $userid
     * @return \stdClass|false
     */
    public static function get_submission($itemid, $userid) {
        global $DB;
        return $DB->get_record(constants::M_CPAGESUBMISSIONS_TABLE, ['itemid' => $itemid, 'userid' => $userid]);
    }

    /**
     * Save (upsert) a user's sharing consent for an item.
     *
     * @param int $itemid
     * @param int $userid
     * @param bool $consent
     * @return \stdClass the submission record
     */
    public static function set_consent($itemid, $userid, $consent) {
        global $DB;
        $now = time();
        $submission = self::get_submission($itemid, $userid);
        if ($submission) {
            $submission->consent = $consent ? 1 : 0;
            $submission->timemodified = $now;
            $DB->update_record(constants::M_CPAGESUBMISSIONS_TABLE, $submission);
        } else {
            $submission = new \stdClass();
            $submission->itemid = $itemid;
            $submission->userid = $userid;
            $submission->consent = $consent ? 1 : 0;
            $submission->likes = 0;
            $submission->timecreated = $now;
            $submission->timemodified = $now;
            $submission->id = $DB->insert_record(constants::M_CPAGESUBMISSIONS_TABLE, $submission);
        }
        return $submission;
    }

    /**
     * Toggle the current user's like on a submission.
     *
     * @param int $submissionid
     * @param int $userid the liker
     * @return array [bool liked, int likecount]
     */
    public static function toggle_like($submissionid, $userid) {
        global $DB;
        $params = ['submissionid' => $submissionid, 'userid' => $userid];
        if ($DB->record_exists(constants::M_CPAGELIKES_TABLE, $params)) {
            $DB->delete_records(constants::M_CPAGELIKES_TABLE, $params);
            $liked = false;
        } else {
            $like = (object) $params;
            $like->timecreated = time();
            $DB->insert_record(constants::M_CPAGELIKES_TABLE, $like);
            $liked = true;
        }
        // Recount rather than increment, so the denormalized count self-heals.
        $likecount = $DB->count_records(constants::M_CPAGELIKES_TABLE, ['submissionid' => $submissionid]);
        $DB->set_field(constants::M_CPAGESUBMISSIONS_TABLE, 'likes', $likecount, ['id' => $submissionid]);
        return [$liked, $likecount];
    }

    /**
     * Fetch the display entries for the community page of an item.
     *
     * Group rules: when the activity groupmode is not NOGROUPS, viewers without
     * the accessallgroups capability only ever see submissions from users who
     * share at least one group with them (plus their own).
     *
     * @param \stdClass $itemrecord the minilesson_rsquestions row
     * @param \stdClass $moduleinstance the minilesson row
     * @param \stdClass|\cm_info $cm the course module
     * @param \context_module $context
     * @param int $viewerid the viewing user
     * @param string $sort self::SORT_DATE (default) or self::SORT_LIKES
     * @param int $mingrade minimum step grade (percent) for eligibility
     * @param bool $requiremedia whether a submission needs an audio recording
     *   (true for spoken item types, false for written ones)
     * @return array of entry objects ready for the mustache template
     */
    public static function fetch_community_entries(
        $itemrecord,
        $moduleinstance,
        $cm,
        $context,
        $viewerid,
        $sort = self::SORT_DATE,
        $mingrade = self::ELIGIBLE_GRADE,
        $requiremedia = true
    ) {
        global $DB;

        $submissions = $DB->get_records(
            constants::M_CPAGESUBMISSIONS_TABLE,
            ['itemid' => $itemrecord->id, 'consent' => 1],
            'timemodified DESC'
        );
        if (!$submissions) {
            return [];
        }

        // Group filtering.
        $groupmode = groups_get_activity_groupmode($cm);
        $filterbygroup = $groupmode != NOGROUPS && !has_capability('moodle/site:accessallgroups', $context, $viewerid);
        $allowedusers = false;
        if ($filterbygroup) {
            $allowedusers = [];
            $viewergroups = groups_get_all_groups($moduleinstance->course, $viewerid, $cm->groupingid);
            foreach ($viewergroups as $group) {
                $members = groups_get_members($group->id, 'u.id');
                foreach ($members as $member) {
                    $allowedusers[$member->id] = true;
                }
            }
            // A user may always see their own submission.
            $allowedusers[$viewerid] = true;
        }

        $candidates = [];
        foreach ($submissions as $submission) {
            if ($allowedusers !== false && !isset($allowedusers[$submission->userid])) {
                continue;
            }
            $candidates[$submission->userid] = $submission;
        }
        if (!$candidates) {
            return [];
        }

        // Fetch the users' attempts (newest first) and keep the newest step
        // for this item that meets the sharing requirements.
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($candidates), SQL_PARAMS_NAMED);
        $select = "moduleid = :moduleid AND userid $insql";
        $inparams['moduleid'] = $moduleinstance->id;
        $attempts = $DB->get_records_select(
            constants::M_ATTEMPTSTABLE,
            $select,
            $inparams,
            'id DESC',
            'id, userid, sessiondata, timemodified'
        );

        // The newest attempt containing a step for this item is the user's
        // current submission: if it does not qualify we do NOT fall back to an
        // older (superseded) recording.
        $steps = [];
        $decidedusers = [];
        foreach ($attempts as $attempt) {
            if (isset($decidedusers[$attempt->userid])) {
                continue;
            }
            $step = self::find_item_step($attempt, $itemrecord->id);
            if ($step === false) {
                continue;
            }
            $decidedusers[$attempt->userid] = true;
            if (self::step_qualifies($step, $mingrade, $requiremedia)) {
                $steps[$attempt->userid] = $step;
            }
        }
        if (!$steps) {
            return [];
        }

        // Fetch display data for the submitting users.
        global $PAGE;
        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($steps), SQL_PARAMS_NAMED);
        $userfields = implode(', ', array_merge(\core_user\fields::get_picture_fields(), ['country']));
        $users = $DB->get_records_select('user', "id $insql", $inparams, '', $userfields);
        $countries = get_string_manager()->get_list_of_countries(true);

        $entries = [];
        foreach ($steps as $userid => $step) {
            if (!isset($users[$userid])) {
                continue;
            }
            $submission = $candidates[$userid];
            $user = $users[$userid];
            $userpicture = new \user_picture($user);
            $userpicture->size = 64;
            $entry = new \stdClass();
            $entry->submissionid = $submission->id;
            $entry->fullname = fullname($user);
            $entry->profileimageurl = $userpicture->get_url($PAGE)->out(false);
            $entry->country = isset($countries[$user->country]) ? $countries[$user->country] : '';
            // The full transcript: the template renders a shortened version of it
            // (and item types that want to can offer a "more" link to expand).
            $entry->transcript = trim(strip_tags((string) ($step->resultsdata->rawspeech ?? '')));
            $entry->mediaurl = $step->resultsdata->mediaurl ?? '';
            $entry->likes = $submission->likes;
            $entry->timemodified = $submission->timemodified;
            $entry->submitdate = userdate($submission->timemodified, get_string('strftimedate', 'langconfig'));
            $entry->isowner = $userid == $viewerid;
            $entry->likedbyme = false; // Filled in below.
            $entries[] = $entry;
        }

        // Mark the entries the viewer has already liked.
        if ($entries) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_column($entries, 'submissionid'), SQL_PARAMS_NAMED);
            $inparams['userid'] = $viewerid;
            $mylikes = $DB->get_records_select(
                constants::M_CPAGELIKES_TABLE,
                "userid = :userid AND submissionid $insql",
                $inparams,
                '',
                'submissionid'
            );
            foreach ($entries as $entry) {
                $entry->likedbyme = isset($mylikes[$entry->submissionid]);
            }
        }

        // Sort: likes (then newest), or newest first (default).
        if ($sort === self::SORT_LIKES) {
            usort($entries, function ($a, $b) {
                return [$b->likes, $b->timemodified] <=> [$a->likes, $a->timemodified];
            });
        } else {
            usort($entries, function ($a, $b) {
                return $b->timemodified <=> $a->timemodified;
            });
        }

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /**
     * Find the step for an item in an attempt's sessiondata.
     *
     * @param \stdClass $attempt attempt record with sessiondata
     * @param int $itemid the minilesson_rsquestions id
     * @return \stdClass|false the step data, or false if the attempt has no step for the item
     */
    public static function find_item_step($attempt, $itemid) {
        if (empty($attempt->sessiondata)) {
            return false;
        }
        $sessiondata = json_decode($attempt->sessiondata);
        if (!$sessiondata || empty($sessiondata->steps)) {
            return false;
        }
        foreach ((array) $sessiondata->steps as $step) {
            if (!empty($step->lessonitemid) && $step->lessonitemid == $itemid) {
                return $step;
            }
        }
        return false;
    }

    /**
     * Does a stored step qualify for sharing on the community page?
     *
     * Qualifies when the grade meets the minimum and there is something to
     * share: for spoken item types the stored media url must be a real
     * http(s) url (a blob: url from an unfinished upload is useless to other
     * users), for written ones the submission text must not be empty.
     *
     * @param \stdClass $step the step data from sessiondata
     * @param int $mingrade minimum step grade (percent) for eligibility
     * @param bool $requiremedia whether a submission needs an audio recording
     * @return bool
     */
    public static function step_qualifies($step, $mingrade = self::ELIGIBLE_GRADE, $requiremedia = true) {
        if (empty($step->hasgrade) || !isset($step->grade) || $step->grade < $mingrade) {
            return false;
        }
        if (empty($step->resultsdata)) {
            return false;
        }
        if ($requiremedia) {
            if (empty($step->resultsdata->mediaurl)) {
                return false;
            }
            return strpos($step->resultsdata->mediaurl, 'http') === 0;
        }
        return trim((string) ($step->resultsdata->rawspeech ?? '')) !== '';
    }

    /**
     * Can this user currently share (or unshare) their submission on an item?
     *
     * True when the community page is on for the item and the user's newest
     * stored step for the item qualifies (grade + usable media url).
     *
     * @param \stdClass $itemrecord the minilesson_rsquestions row
     * @param \stdClass $moduleinstance the minilesson row
     * @param int $userid
     * @param int $mingrade minimum step grade (percent) for eligibility
     * @param bool $requiremedia whether a submission needs an audio recording
     * @return bool
     */
    public static function can_share($itemrecord, $moduleinstance, $userid, $mingrade = self::ELIGIBLE_GRADE,
            $requiremedia = true) {
        global $DB;
        $attempts = $DB->get_records(
            constants::M_ATTEMPTSTABLE,
            ['moduleid' => $moduleinstance->id, 'userid' => $userid],
            'id DESC',
            'id, userid, sessiondata'
        );
        foreach ($attempts as $attempt) {
            $step = self::find_item_step($attempt, $itemrecord->id);
            if ($step !== false) {
                // Newest submission decides; do not fall back to older attempts.
                return self::step_qualifies($step, $mingrade, $requiremedia);
            }
        }
        return false;
    }
}
