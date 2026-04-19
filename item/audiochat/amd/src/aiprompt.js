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
 * TODO describe module aiprompt
 *
 * @module     minilessonitem_audiochat/aiprompt
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/fragment'], function ($, Fragment) {
    return {
        init: function () {
            $('select[data-name="instructionsaiprompt"]').on('change', function () {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_instructions"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'instructions', itemtype: itemtype }
                ).done(function (text) {
                    textarea.val(text);
                });
            });

            $('select[data-name="gradingaiprompt"]').on('change', function () {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_grade"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'grading', itemtype: itemtype }
                ).done(function (text) {
                    textarea.val(text);
                });
            });

            $('select[data-name="feedbackaiprompt"]').on('change', function () {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_feedback"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'feedback', itemtype: itemtype }
                ).done(function (text) {
                    textarea.val(text);
                });
            });
        }
    };
});