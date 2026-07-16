define(['jquery',
    'core/log',
    'mod_minilesson/definitions',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates',
    'core/str',
    'core/notification'
], function ($, log, def, anim, progresstimer, templates, str, notification) {
    "use strict"; // jshint ;_;

    /*
    This file manages the quiz stage for the multichoice quiz item type.
    A set of multichoice questions about a single shared resource, shown one at a time.
    */

    log.debug('MiniLesson Multichoice Quiz: initialising');

    return {

        //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        pointer: 0,

        init: function (index, itemdata, quizhelper) {
            var self = this;
            self.index = index;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.pointer = 0;
            self.strings = {};

            //anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.init_controls();
            self.init_strings();
            self.init_items();
            self.register_events();
            if (self.itemdata.showallquestions) {
                //all questions are already visible; nothing to reveal
                self.updateProgressDots();
            } else {
                self.show_question(0);
            }
        },

        init_controls: function () {
            var self = this;
            self.controls = {
                container: $("#" + self.itemdata.uniqueid + "_container"),
                nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
                confirmchoicebutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_mc_confirmchoice"),
                questionsbox: $("#" + self.itemdata.uniqueid + "_container .mcq_questionsbox"),
                progressdots: $("#" + self.itemdata.uniqueid + "_container .mcq_progressdots"),
                progresscontainer: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
                resultscontainer: $("#" + self.itemdata.uniqueid + "_container .mcq_resultscontainer"),
            };
        },

        init_strings: function () {
            var self = this;
            str.get_strings([
                {"key": "nextlessonitem", "component": 'mod_minilesson'},
                {"key": "confirm_desc", "component": 'mod_minilesson'},
                {"key": "yes", "component": 'moodle'},
                {"key": "no", "component": 'moodle'},
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
            });
        },

        init_items: function () {
            var self = this;
            self.items = self.itemdata.questions.map(function (question) {
                //the text of the correct answer, for the results screen
                //(resulttext carries the A/B/C/D letter prefix when the answer text is hidden)
                var correctsentence = question.sentences.filter(function (sentence) {
                    return sentence.indexplusone === question.correctanswer;
                }).map(function (sentence) {
                    return sentence.resulttext;
                }).join('');
                return {
                    qindex: question.qindex,
                    questiontext: question.questiontext,
                    correcttext: correctsentence,
                    correctanswer: question.correctanswer,
                    answered: false,
                    correct: false,
                    hadwrong: false,
                };
            });

            //shuffle the on-screen order of the answer options
            //(correctness is checked by data-index, which travels with the option)
            if (self.itemdata.shuffleanswers) {
                self.controls.container.find('.mcchoiceswrapper').each(function () {
                    var $parent = $(this);
                    var $children = $parent.children();
                    $children.sort(function () {
                        return 0.5 - Math.random();
                    });
                    $parent.empty().append($children);
                });
            }
        },

        register_events: function () {
            var self = this;

            self.controls.nextbutton.on('click', function () {
                if (self.items.some(function (item) {return !item.answered;})) {
                    notification.confirm(
                        self.strings.nextlessonitem,
                        self.strings.confirm_desc,
                        self.strings.yes,
                        self.strings.no,
                        function () {
                            self.next_question();
                        }
                    );
                } else {
                    self.next_question();
                }
            });

            //on tapping of a response, we either action the choice or show a confirmation button
            self.controls.container.on('click', '.minilesson_mc_response', function () {
                var chosen = $(this);
                var qindex = chosen.closest('.mcq_question').data('qindex');
                if (self.itemdata.showallquestions) {
                    //every question is live at once; the tapped question becomes the current one
                    self.pointer = qindex;
                } else if (qindex !== self.pointer) {
                    //only respond to taps on the current question
                    return;
                }
                //if disabled => just return (already answered / already tried this option)
                if (chosen.hasClass('minilesson_mc_disabled')) {
                    return;
                }
                if (self.items[self.pointer].answered) {
                    return;
                }

                if (self.itemdata.confirmchoice === 1 || self.itemdata.confirmchoice === '1') {
                    //highlight selected answer and wait for confirmation
                    self.chosenelement = chosen;
                    self.current_question().find('.minilesson_mc_response').removeClass('minilesson_mc_selected');
                    chosen.addClass('minilesson_mc_selected');
                    anim.do_animate(self.controls.confirmchoicebutton, 'zoomIn animate__faster', 'in');
                } else {
                    self.chosenelement = chosen;
                    self.final_choice();
                }
            });

            //on tapping of confirmation button, execute final choice
            self.controls.confirmchoicebutton.on('click', function () {
                if (self.chosenelement) {
                    self.final_choice();
                }
            });

            //time limit is for the whole item
            self.controls.container.on('showElement', function () {
                if (self.itemdata.timelimit > 0) {
                    self.controls.progresscontainer.show();
                    self.controls.progresscontainer.find('i').show();
                    self.controls.progresscontainer.find('#progresstimer').progressTimer({
                        height: '5px',
                        timeLimit: self.itemdata.timelimit,
                        onFinish: function () {
                            self.time_up();
                        }
                    });
                }
            });
        },

        current_question: function () {
            var self = this;
            return self.controls.container.find('.mcq_question_' + self.pointer);
        },

        final_choice: function () {
            var self = this;
            var chosen = self.chosenelement;
            var item = self.items[self.pointer];
            var checked = chosen.data('index');
            var correct = checked === item.correctanswer;

            self.controls.confirmchoicebutton.hide();
            chosen.addClass('minilesson_mc_selected');

            if (correct) {
                item.answered = true;
                item.correct = !item.hadwrong;
                chosen.find('.minilesson_mc_right').show();
                self.current_question().find('.minilesson_mc_response').addClass('minilesson_mc_disabled');
                self.updateProgressDots();
                self.move_on();
            } else if (self.itemdata.allowretry) {
                //wrong, but they can keep choosing until they find the correct answer
                //the question is already recorded as incorrect
                item.hadwrong = true;
                chosen.find('.minilesson_mc_wrong').show();
                chosen.addClass('minilesson_mc_disabled');
                self.updateProgressDots();
                anim.do_animate(chosen, 'shakeX animate__faster');
            } else {
                //wrong and no retry: reveal the correct answer and move on
                item.answered = true;
                item.correct = false;
                chosen.find('.minilesson_mc_wrong').show();
                self.current_question()
                    .find('.minilesson_mc_response[data-index="' + item.correctanswer + '"] .minilesson_mc_right')
                    .show();
                self.current_question().find('.minilesson_mc_response').addClass('minilesson_mc_disabled');
                self.updateProgressDots();
                self.move_on();
            }
        },

        move_on: function () {
            var self = this;
            if (self.itemdata.showallquestions) {
                //the questions all stay on the page; once the last one is answered, finish up
                var allanswered = self.items.every(function (item) {
                    return item.answered;
                });
                if (allanswered) {
                    setTimeout(function () {
                        self.end();
                    }, 1600);
                }
                return;
            }
            setTimeout(function () {
                if (self.pointer < self.items.length - 1) {
                    self.current_question().hide();
                    self.pointer++;
                    self.show_question(self.pointer);
                } else {
                    self.end();
                }
            }, 1600);
        },

        show_question: function (qindex) {
            var self = this;
            self.pointer = qindex;
            self.chosenelement = null;
            self.updateProgressDots();
            var thequestion = self.current_question();
            thequestion.show();
            anim.do_animate(thequestion, 'zoomIn animate__faster', 'in');
        },

        updateProgressDots: function () {
            var self = this;
            var color;
            var icon;
            var progress = self.items.map(function (item) {
                color = "#E6E9FD";
                icon = "fa fa-square";
                if (item.answered && item.correct) {
                    color = "#74DC72";
                    icon = "fa fa-check-square";
                } else if ((item.answered && !item.correct) || item.hadwrong) {
                    color = "#FB6363";
                    icon = "fa fa-window-close";
                }
                return "<i style='color:" + color + "' class='" + icon + " pl-1'></i>";
            }).join(" ");
            self.controls.progressdots.html(progress);
        },

        time_up: function () {
            var self = this;
            //the time limit is up: no more answering, straight to the results
            self.controls.container.find('.minilesson_mc_response').addClass('minilesson_mc_disabled');
            self.end();
        },

        end: function () {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);
            self.updateProgressDots();

            setTimeout(function () {
                self.controls.nextbutton.prop("disabled", false);
                if (self.quizhelper.showitemreview) {
                    self.show_item_review();
                } else {
                    self.next_question();
                }
            }, 1600);
        },

        escape_html: function (text) {
            return $('<div>').text(text).html();
        },

        show_item_review: function () {
            var self = this;
            var review_data = {};
            //show the correct answer alongside the question text, green if they got it, red if not
            self.items.forEach(function (item) {
                var answerclass = item.correct ? 'correctitem' : 'wrongitem';
                item.target = self.escape_html(item.questiontext) +
                    ' <span class="' + answerclass + '">' + self.escape_html(item.correcttext) + '</span>';
            });
            review_data.items = self.items;
            review_data.totalitems = self.items.length;
            review_data.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;

            templates.render('mod_minilesson/listitemresults', review_data).then(
                function (html, js) {
                    self.controls.resultscontainer.html(html);
                    self.controls.resultscontainer.show();
                    self.controls.questionsbox.hide();
                    self.controls.confirmchoicebutton.hide();
                    self.controls.progressdots.hide();
                    self.controls.progresscontainer.removeClass('d-flex');
                    self.controls.progresscontainer.hide();
                    templates.runTemplateJS(js);
                }
            );
        },

        next_question: function () {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;
            stepdata.grade = stepdata.totalitems > 0 ?
                Math.round((stepdata.correctitems / stepdata.totalitems) * 100) : 0;
            self.quizhelper.do_next(stepdata);
        },

    };
});
