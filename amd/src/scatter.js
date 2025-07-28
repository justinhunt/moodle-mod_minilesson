
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
 * Scatter app javascript for Poodll minilesson
 *
 * @package    mod_minilesson
 * @copyright  2024 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'mod_minilesson/definitions', 'core/log', 'core/templates','mod_minilesson/animatecss', 'core/ajax', 'core/str'],
    function($, notification, def, log, templates, anim,  Ajax, str) {

  "use strict"; // jshint ;_;

  /*
  This file is to manage the Space Game item type.
   */

  log.debug('MiniLesson Scatter: initialising');

        return {

            pointer: 1,
            jsondata: null,
            props: null,
            language: 'en-US',
            terms: [],
            definitions: [],
            scatteritems: [],
            displayterms: [],
            results: [],
            controls: {},

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },


            init: function (index, itemdata, quizhelper) {
                var self = this;

                console.log(itemdata);


                //init terms
                for (var i = 0; i < itemdata.scatteritems.length; i++) {
                    self.terms[i] = itemdata.scatteritems[i].term;
                    self.definitions[i] = itemdata.scatteritems[i].definition;
                }
                self.scatteritems = itemdata.scatteritems;
                self.language = itemdata.language;
                self.itemdata = itemdata;
                self.index = index;
                self.quizhelper = quizhelper;

                //anim
                var animopts = {};
                animopts.useanimatecss = quizhelper.useanimatecss;
                anim.init(animopts);

                this.init_controls();
                this.register_events();
                this.start();


            },  //end of init

            init_controls: function () {
                var self = this;

                self.controls = {};
                self.controls.stage = $("#" + self.itemdata.uniqueid + "_container .ml_scatter_stage");
                self.controls.next_button = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
            },

            next_question: function () {
                var self = this;

                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.terms.length;
                stepdata.correctitems = self.results.filter(function (e) {
                    return e.points > 0;
                }).length;
                stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);
                self.quizhelper.do_next(stepdata);
            },

            register_events: function () {
                var self = this;

                self.controls.next_button.click(function () {
                    self.next_question();
                });
            },

            start: function() {

            }


        }; //end of return
});