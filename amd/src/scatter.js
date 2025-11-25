
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

define(['jquery', 'core/notification', 'mod_minilesson/definitions', 'core/log', 'core/templates', 'mod_minilesson/animatecss', 'core/ajax', 'core/str', 'mod_minilesson/spacegame'],
    function ($, notification, def, log, templates, anim, Ajax, str, spacegame) {

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
            startTime: null,
            scatteritems: [],
            controls: {},
            progressTimer: null,
            markedIndex: [],

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },


            init: function (index, itemdata, quizhelper) {
                var self = this;

                log.debug(itemdata);
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

                self.controls = {
                    container: $("#" + self.itemdata.uniqueid + "_container")
                };
                self.controls.stage = self.controls.container.find(".ml_scatter_stage");
                self.controls.next_button = self.controls.container.find(".minilesson_nextbutton");
                self.controls.progress_container = self.controls.container.find(".progress-container");
                self.controls.result_container = self.controls.container.find(".ml_scatter_resultscontainer");
                self.controls.actionbutton = self.controls.container.find(".minilesson_actionbutton");
                self.controls.retrybutton = self.controls.container.find(".minilesson-try-again");

                const $listItems = self.controls.stage.children('.ml_scatter_listitem');
                $listItems.each((i, listitem) => {
                    self.itemdata.shuffleditems[i].item = listitem;
                });
            },

            shuffleItems: function () {
                var self = this;
                let array = Array.from(self.itemdata.shuffleditems);
                let currentIndex = array.length, randomIndex;

                while (currentIndex != 0) {
                    randomIndex = Math.floor(Math.random() * currentIndex);
                    currentIndex--;
                    [array[currentIndex], array[randomIndex]] = [
                        array[randomIndex], array[currentIndex]];
                }
                self.itemdata.shuffleditems = array;
                self.itemdata.shuffleditems.forEach(shuffleitem => {
                    shuffleitem.item.classList.remove('borderblue', 'border', 'border-success',
                        'border-warning', 'shake-constant', 'invisible');
                    self.controls.stage.append(shuffleitem.item);
                });
            },

            check_crosscard: function (e) {
                var self = this;
                const $target = $(e.target);
                const $listItems = self.controls.stage.children('.ml_scatter_listitem');
                if (!$listItems.is($target)) {
                    return;
                }
                $listItems.filter((_, i) => !i.classList.contains('border-success'))
                    .removeClass('borderblue border border-warning shake-constant');
                const currentIndex = $target.index();
                if (self.markedIndex.length == 0) {
                    self.markedIndex.push(currentIndex);
                } else if (self.markedIndex.includes(currentIndex)) {
                    const rIndex = self.markedIndex.findIndex(i => i === currentIndex);
                    if (rIndex > -1) {
                        self.markedIndex.slice(rIndex, 1);
                    }
                } else {
                    const lastIndex = self.markedIndex[0];
                    const lastItem = self.itemdata.shuffleditems[lastIndex];
                    const currentItem = self.itemdata.shuffleditems[currentIndex];
                    if (lastItem.key === currentItem.key) {
                        //Correct Choice
                        self.itemdata.scatteritems[currentItem.key].correct = true;
                        $listItems.eq(lastIndex).addClass('border border-success ml_scatter_anim_correct');
                        $listItems.eq(currentIndex).addClass('border border-success ml_scatter_anim_correct');
                        setTimeout(function () {
                            $listItems.eq(lastIndex).addClass('invisible');
                            $listItems.eq(currentIndex).addClass('invisible');
                            if (!$listItems.filter(':not(.invisible)').length) {
                                self.end();
                            }
                        }, 500);
                    } else {
                        self.itemdata.scatteritems[currentItem.key].correct = false;
                        $listItems.eq(lastIndex).addClass('border border-warning ml_scatter_anim_incorrect');
                        $listItems.eq(currentIndex).addClass('border border-warning ml_scatter_anim_incorrect');
                        setTimeout(function () {
                            $listItems.eq(lastIndex).removeClass('border border-warning ml_scatter_anim_incorrect');
                            $listItems.eq(currentIndex).removeClass('border border-warning ml_scatter_anim_incorrect');
                        }, 500);
                    }
                    self.markedIndex = [];
                }
                self.markedIndex.forEach(i => {
                    $listItems.eq(i).addClass('borderblue');
                });
                if (!$listItems.filter(':not(.invisible)').length) {
                    self.end();
                }
            },

            next_question: function () {
                var self = this;

                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.itemdata.scatteritems.length;
                stepdata.correctitems = self.itemdata.scatteritems.filter(e => e.correct).length;
                stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);
                self.quizhelper.do_next(stepdata);
            },

            register_events: function () {
                var self = this;

                // Click and space key for scatter stage
                self.controls.stage.on('click', self.check_crosscard.bind(self));
                self.controls.stage.on('keydown', function (e) {
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        // Only respond if focused on a list item
                        var $focused = $(document.activeElement);
                        if ($focused.hasClass('ml_scatter_listitem')) {
                            self.check_crosscard.call(self, { target: document.activeElement });
                            e.preventDefault();
                        }
                    }
                });

                self.controls.container.on("showElement", () => {
                    if (self.itemdata.timelimit > 0) {
                        self.controls.progress_container.show();
                        self.controls.progress_container.find('i').show();
                        if (self.progressTimer) {
                            clearInterval(self.progressTimer);
                            self.progressTimer = null;
                        }
                        self.progressTimer = self.controls.progress_container.find('#progresstimer').progressTimer({
                            height: '5px',
                            timeLimit: self.itemdata.timelimit,
                            onFinish: function () {
                                self.end();
                            }
                        }).attr('timer');
                    }
                    self.startTime = Date.now();
                });

                // Click and space key for try again button
                self.controls.container.on('click', '#minilesson-try-again', () => {
                    self.startTime = null;
                    self.markedIndex = [];
                    self.shuffleItems();
                    self.controls.container.trigger("showElement");
                    self.controls.result_container.hide();
                    self.controls.stage.show();
                    self.controls.progress_container.find('#progresstimer,i').show();
                    self.controls.actionbutton.show();
                });
                self.controls.container.on('keydown', '#minilesson-try-again', function (e) {
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        $(this).trigger('click');
                        e.preventDefault();
                    }
                });

                // Click and space key for next button
                self.controls.next_button.on('click', function () {
                    self.next_question();
                });
                self.controls.next_button.on('keydown', function (e) {
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        $(this).trigger('click');
                        e.preventDefault();
                    }
                });
            },

            start: function () {

            },

            end: function () {
                var self = this;
                var tdata = {
                    prettytime: '00:00',
                    results: []
                };
                var totaltime = Date.now() - self.startTime;
                if (totaltime > 0) {
                    totaltime = Number.parseFloat(totaltime / 1000).toFixed(0);
                }
                if (totaltime > 0) {
                    tdata['prettytime'] = spacegame.pretty_print_secs(totaltime);
                }
                tdata['showitemreview'] = self.quizhelper.showitemreview;
                tdata['allowretry'] = self.itemdata.allowretry;
                tdata['total'] = self.itemdata.scatteritems.length;
                tdata['totalcorrect'] = self.itemdata.scatteritems.filter(e => e.correct).length;
                self.itemdata.scatteritems.forEach(item => {
                    tdata.results.push({
                        question: item.term,
                        answer: item.definition,
                        correct: item.correct
                    });
                });
                self.controls.result_container.show();
                self.controls.stage.hide();
                self.controls.progress_container.find('#progresstimer,i').hide();
                self.controls.actionbutton.hide();
                if (self.progressTimer) {
                    clearInterval(self.progressTimer);
                    self.progressTimer = null;
                }
                templates.render('mod_minilesson/scatter_feedback', tdata).then(
                    function (html, js) {
                        self.controls.result_container.html(html);
                        templates.runTemplateJS(js || '');
                    }
                );
            },


        }; //end of return
    });