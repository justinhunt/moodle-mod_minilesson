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
 * A widget item in a lesson. The widget never moves the lesson on: the learner uses the next button, and the
 * last valid score the widget sent before that is the one used, as the item's grade policy says.
 *
 * @module     minilessonitem_smartframe/itemtype
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'minilessonitem_smartframe/widgethost'], function($, log, widgethost) {
    "use strict";

    // These match itemtype::GRADE_* in PHP.
    var GRADE_RECORD = 1;
    var GRADE_USE = 2;

    return {

        // For making multiple instances.
        clone: function() {
            return $.extend(true, {}, this);
        },

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.index = index;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.score = null;
            self.widget = null;

            var $container = $("#" + itemdata.uniqueid + "_container");
            $container.find(".minilesson_nextbutton").on('click', function(e) {
                e.preventDefault();
                self.next_question();
            });

            var iframe = $container.find("iframe.minilessonitem_smartframe_frame")[0];
            if (iframe && itemdata.widgeturl) {
                self.widget = widgethost.mount(iframe, {
                    url: itemdata.widgeturl,
                    base: itemdata.widgetbase,
                    parentOrigin: itemdata.parentorigin,
                    uiLanguage: itemdata.uilanguage,
                    gradeReporting: itemdata.gradepolicy === GRADE_RECORD || itemdata.gradepolicy === GRADE_USE,
                    resizes: itemdata.widgetresizes,
                    onGrade: function(score) {
                        log.debug('Widget score ' + score.earned + '/' + score.possible);
                        self.score = score;
                    },
                });
            }
        },

        next_question: function() {
            var self = this;
            var stepdata = {
                index: self.index,
                hasgrade: false,
                totalitems: 0,
                correctitems: 0,
                grade: 0,
            };
            var score = self.score;
            if (self.itemdata.gradepolicy === GRADE_USE) {
                var marks = self.itemdata.totalmarks;
                stepdata.hasgrade = true;
                stepdata.totalitems = marks;
                // No score (the learner moved on before finishing) counts as nothing right.
                stepdata.correctitems = score ? Math.round(100 * marks * score.earned / score.possible) / 100 : 0;
                stepdata.grade = score ? Math.round(100 * score.earned / score.possible) : 0;
            }
            if (score && self.itemdata.gradepolicy !== 0) {
                stepdata.widgetscore = {earned: score.earned, possible: score.possible};
            }
            // The widget is forgotten when do_next removes its frame from the page.
            self.quizhelper.do_next(stepdata);
        }
    };
});
