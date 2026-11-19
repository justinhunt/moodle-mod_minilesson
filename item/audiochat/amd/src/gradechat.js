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
 * Grades a finished audio chat from its transcript, for every chat driver.
 *
 * The transcript goes to the minilessonitem_audiochat_grade_chat web service, which has it graded
 * where the admin chose in the MiniLesson AI Manager (Cloud Poodll by default). Grading off the live
 * session gives the same grading whichever voice provider ran the chat.
 *
 * @module     minilessonitem_audiochat/gradechat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/log'], function (Ajax, log) {
    "use strict";

    return {
        /**
         * Turn a driver's conversation items, oldest first, into the turns the grader reads.
         *
         * @param {Array} items driver items: {usertype, content, media}
         * @param {Boolean} endedbyai the AI ended the chat
         * @returns {Array} [{role: 'user'|'assistant', text}]
         */
        transcript: function (items, endedbyai) {
            var turns = items
                .filter(function (item) { return item.media || (item.content && item.content.trim() !== ''); })
                .map(function (item) {
                    if (item.media) {
                        // The grader only reads text, so say what the AI showed.
                        return {role: 'assistant', text: '[The AI showed the student a picture: ' + item.media.filename + ']'};
                    }
                    return {role: item.usertype === 'user' ? 'user' : 'assistant', text: item.content};
                });
            if (endedbyai) {
                turns.push({role: 'assistant', text: '[The AI ended the chat]'});
            }
            return turns;
        },

        /**
         * Grade the chat. The call is tried twice. If it still fails the grading data says so, and the
         * item scores 0.
         *
         * @param {Number} cmid
         * @param {Number} itemid
         * @param {Array} turns from transcript()
         * @returns {Promise} resolves to {score, gradeexplanation, feedback} or {failed: true}
         */
        grade: function (cmid, itemid, turns) {
            var request = function () {
                return Ajax.call([{
                    methodname: 'minilessonitem_audiochat_grade_chat',
                    args: {cmid: cmid, itemid: itemid, items: turns}
                }])[0];
            };

            log.debug('Audio chat: sending grading request');
            return request().catch(function (err) {
                log.debug('Audio chat grading call failed, trying again');
                log.debug(err);
                return request();
            }).then(function (result) {
                if (!result || !result.success) {
                    throw result;
                }
                var gradingdata = {
                    score: result.score,
                    gradeexplanation: result.gradeexplanation,
                    feedback: result.feedback
                };
                log.debug('Audio chat grading:');
                log.debug(gradingdata);
                return gradingdata;
            }).catch(function (reason) {
                log.debug('Audio chat grading failed');
                log.debug(reason);
                return {failed: true};
            });
        },
    };
});
