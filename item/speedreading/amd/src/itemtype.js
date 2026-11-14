define(['jquery',
    'core/log',
    'mod_minilesson/animatecss',
    'core/templates',
    'core/str',
    'core/notification',
    'mod_minilesson/translatetext'
], function($, log, anim, templates, str, notification, translatetext) {
    "use strict"; // jshint ;_;

    /*
    The speed reading item. The student sees four screens:
    1. the introduction (the splash screen; quizhelper triggers showElement on its start button),
    2. the reading passage, timed from showElement until they tap "I have finished reading",
    3. the questions, with no right or wrong shown,
    4. the results: reading speed, speed points, quiz score, answers and the passage.
    */

    log.debug('MiniLesson Speed Reading: initialising');

    var COMPONENT = 'minilessonitem_speedreading';

    return {

        //for making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.index = index;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.pointer = 0;
            self.started = false;
            self.finishedreading = false;
            self.starttime = 0;
            self.readseconds = 0;
            self.timerid = null;
            self.locked = false;
            self.showingresults = false;
            //the chosen answer (1-based, 0 = none) of each question
            self.answers = itemdata.questions.map(function() {
                return 0;
            });

            anim.init({useanimatecss: quizhelper.useanimatecss});

            self.init_controls();
            self.shuffle_answers();
            self.register_events();
            //the lesson's next button only appears on the results screen, so the student cannot
            //skip the reading or the questions by accident
            self.controls.nextbutton.hide();
        },

        init_controls: function() {
            var self = this;
            var container = $("#" + self.itemdata.uniqueid + "_container");
            self.controls = {
                container: container,
                nextbutton: container.find(".minilesson_nextbutton"),
                reading: container.find('[data-region="reading"]'),
                timertext: container.find('[data-region="timertext"]'),
                quiz: container.find('[data-region="quiz"]'),
                counter: container.find('[data-region="questioncounter"]'),
                dots: container.find('[data-region="dots"]'),
                submitbutton: container.find('[data-action="submitanswers"]'),
                results: container.find('[data-region="results"]'),
            };
        },

        shuffle_answers: function() {
            var self = this;
            if (!self.itemdata.shuffleanswers) {
                return;
            }
            //correctness is checked by data-index, which travels with the option
            self.controls.container.find('.ml_sr_options').each(function() {
                var $parent = $(this);
                var $children = $parent.children();
                $children.sort(function() {
                    return 0.5 - Math.random();
                });
                $parent.empty().append($children);
            });
        },

        register_events: function() {
            var self = this;

            //the start button on the introduction screen shows the activity: the reading starts now
            self.controls.container.on('showElement', function() {
                self.start_reading();
            });

            self.controls.container.on('click', '[data-action="finishreading"]', function() {
                self.finish_reading();
            });

            self.controls.container.on('click', '.ml_sr_option', function() {
                self.choose($(this));
            });

            self.controls.submitbutton.on('click', function() {
                if (self.all_answered()) {
                    self.finish_quiz();
                }
            });

            self.controls.nextbutton.on('click', function() {
                if (self.showingresults) {
                    self.next_question();
                }
            });
        },

        start_reading: function() {
            var self = this;
            if (self.started) {
                return;
            }
            self.started = true;
            self.starttime = Date.now();
            if (self.controls.timertext.length > 0) {
                self.timerid = setInterval(function() {
                    var seconds = Math.floor((Date.now() - self.starttime) / 1000);
                    self.controls.timertext.text(self.format_time(seconds));
                }, 1000);
            }
        },

        format_time: function(seconds) {
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        },

        finish_reading: function() {
            var self = this;
            if (self.finishedreading) {
                return;
            }
            //the start button should always have started the timer, but be safe
            if (!self.started) {
                self.start_reading();
            }
            self.finishedreading = true;
            self.readseconds = Math.max(1, Math.round((Date.now() - self.starttime) / 1000));
            if (self.timerid) {
                clearInterval(self.timerid);
                self.timerid = null;
            }
            //the passage is gone for good: take it out of the page, not just out of sight
            self.controls.reading.remove();
            self.controls.quiz.show();
            self.scroll_to_top();
            if (!self.itemdata.showallquestions) {
                self.show_question(0);
            }
        },

        scroll_to_top: function() {
            var el = this.controls.container[0];
            if (el && el.getBoundingClientRect().top < 0) {
                el.scrollIntoView({block: 'start'});
            }
        },

        question_element: function(qindex) {
            return this.controls.quiz.find('.ml_sr_question[data-qindex="' + qindex + '"]');
        },

        show_question: function(qindex) {
            var self = this;
            self.pointer = qindex;
            var total = self.itemdata.questions.length;
            str.get_string('questionofn', COMPONENT, {number: qindex + 1, total: total}).then(function(s) {
                self.controls.counter.text(s);
                return s;
            }).catch(notification.exception);
            var dots = '';
            for (var i = 0; i < total; i++) {
                var dotclass = i < qindex ? ' ml_sr_dotdone' : (i === qindex ? ' ml_sr_dotdone ml_sr_dotnew' : '');
                dots += '<span class="ml_sr_dot' + dotclass + '"></span>';
            }
            self.controls.dots.html(dots);
            var thequestion = self.question_element(qindex);
            thequestion.show();
            //the first question just appears; later ones slide in, so the change is easy to see
            self.animate(thequestion, qindex === 0 ? 'fadeIn animate__faster' : self.slide('In'), 'in');
        },

        /**
         * The slide for a question leaving ('Out') or arriving ('In'), from mod_minilesson's shared animations.
         * Questions move from the end of the line to its start, so the direction flips for right to left
         * languages.
         *
         * @param {string} inout 'In' or 'Out'
         * @returns {string} the animation name
         */
        slide: function(inout) {
            var rtl = this.itemdata.contentdir === 'rtl';
            if (inout === 'Out') {
                return rtl ? 'slideOutRight' : 'slideOutLeft';
            }
            return rtl ? 'slideInLeft' : 'slideInRight';
        },

        /**
         * Run an animation, and settle even if it never ends (e.g. its CSS is missing), so the quiz
         * cannot get stuck waiting for it.
         *
         * @param {jQuery} $el the element
         * @param {string} animation the animation name (animate__ is added by animatecss)
         * @param {string} inout 'in' or 'out'
         * @returns {Promise}
         */
        animate: function($el, animation, inout) {
            var fallback = new Promise(function(resolve) {
                setTimeout(function() {
                    $el.removeClass('animate__animated animate__' + animation);
                    if (inout === 'out') {
                        $el.hide();
                    }
                    resolve('timeout');
                }, 1500);
            });
            return Promise.race([anim.do_animate($el, animation, inout), fallback]);
        },

        choose: function($option) {
            var self = this;
            var $question = $option.closest('.ml_sr_question');
            var qindex = $question.data('qindex');
            if (self.locked || (!self.itemdata.showallquestions && qindex !== self.pointer)) {
                return;
            }
            self.answers[qindex] = $option.data('index');
            $question.find('.ml_sr_option').removeClass('ml_sr_selected').attr('aria-pressed', 'false');
            $option.addClass('ml_sr_selected').attr('aria-pressed', 'true');

            if (self.itemdata.showallquestions) {
                //answers can be changed until they submit
                self.controls.submitbutton.prop('disabled', !self.all_answered());
                return;
            }
            //one at a time: a tap moves on, after a moment to see the choice. The question slides out
            //the way a page turns (towards the start of the line), and the next one slides in from the end.
            self.locked = true;
            setTimeout(function() {
                self.animate($question, self.slide('Out'), 'out').then(function() {
                    $question.hide();
                    self.locked = false;
                    if (qindex < self.itemdata.questions.length - 1) {
                        self.show_question(qindex + 1);
                    } else {
                        self.finish_quiz();
                    }
                    return true;
                }).catch(notification.exception);
            }, 800);
        },

        all_answered: function() {
            return this.answers.every(function(answer) {
                return answer > 0;
            });
        },

        /**
         * Speed points out of 10: the target speed or faster gets 10, half the target or slower gets 0,
         * and it is a straight line in between. Keep in step with itemtype::speed_points() in PHP.
         *
         * @param {number} rate the reading speed
         * @param {number} target the target speed
         * @returns {number}
         */
        speed_points: function(rate, target) {
            if (target <= 0) {
                return 0;
            }
            var half = target / 2;
            var points = Math.round(10 * (rate - half) / half);
            return Math.max(0, Math.min(10, points));
        },

        /**
         * The results data stored with the step. The attempt review builds the results screen from it
         * (itemtype::build_results_context in PHP).
         *
         * @returns {object}
         */
        results_data: function() {
            var self = this;
            var itemdata = self.itemdata;
            var correctitems = itemdata.questions.filter(function(question) {
                return self.answers[question.qindex] === question.correctanswer;
            }).length;
            var rate = self.finishedreading ? Math.round(itemdata.wordcount * 60 / self.readseconds) : 0;
            var toofast = self.finishedreading && rate > itemdata.toofastwpm;
            var speedcounts = self.finishedreading && !toofast && correctitems >= itemdata.needed;
            return {
                finishedreading: self.finishedreading,
                cpm: !!itemdata.cpm,
                rate: rate,
                wordcount: itemdata.wordcount,
                readseconds: self.readseconds,
                targetwpm: itemdata.targetwpm,
                passmark: itemdata.passmark,
                needed: itemdata.needed,
                toofast: toofast,
                speedcounts: speedcounts,
                speedpoints: speedcounts ? self.speed_points(rate, itemdata.targetwpm) : 0,
                correctitems: correctitems,
                totalitems: itemdata.questions.length,
                answers: self.answers.slice(),
            };
        },

        /**
         * The data for the results template. Keep in step with itemtype::build_results_context() in PHP.
         *
         * @param {object} rd the results data
         * @returns {object}
         */
        build_results: function(rd) {
            var self = this;
            var itemdata = self.itemdata;
            var translatefields = ['cantranslatetext', 'translatecmid', 'translateitemid', 'translatesourcelang',
                'translatedestlang', 'translatedir', 'translatertl', 'translatetext', 'translatemasked', 'translatephrases'];
            return {
                finishedreading: rd.finishedreading,
                cpm: rd.cpm,
                rate: rd.rate,
                wordcount: rd.wordcount,
                minutes: Math.floor(rd.readseconds / 60),
                seconds: rd.readseconds % 60,
                speedpoints: rd.speedpoints,
                speedpercent: rd.speedpoints * 10,
                maxspeedpoints: 10,
                targetwpm: rd.targetwpm,
                speedcounts: rd.speedcounts,
                toofast: rd.toofast,
                belowpassmark: rd.finishedreading && !rd.speedcounts && !rd.toofast,
                correctitems: rd.correctitems,
                totalitems: rd.totalitems,
                percent: rd.totalitems > 0 ? Math.round(100 * rd.correctitems / rd.totalitems) : 0,
                needed: rd.needed,
                questions: itemdata.questions.map(function(question) {
                    var given = rd.answers[question.qindex] || 0;
                    var giventext = '';
                    question.sentences.forEach(function(sentence) {
                        if (sentence.indexplusone === given) {
                            giventext = sentence.sentence;
                        }
                    });
                    var correct = given > 0 && given === question.correctanswer;
                    var row = {
                        qnumber: question.qnumber,
                        questiontext: question.questiontext,
                        given: giventext,
                        hasgiven: given > 0,
                        correct: correct,
                        wrong: !correct,
                        correcttext: question.correcttext,
                        feedback: question.feedback,
                    };
                    translatefields.forEach(function(field) {
                        if (question[field] !== undefined) {
                            row[field] = question[field];
                        }
                    });
                    return row;
                }),
                passage: itemdata.passage,
                contentdir: itemdata.contentdir,
                contentlang: itemdata.contentlang,
            };
        },

        finish_quiz: function() {
            var self = this;
            if (self.showingresults) {
                return;
            }
            self.showingresults = true;
            self.resultsdata = self.results_data();
            templates.render(COMPONENT + '/speedreadingresults', self.build_results(self.resultsdata)).then(
                function(html, js) {
                    self.controls.quiz.hide();
                    self.controls.results.html(html).show();
                    templates.runTemplateJS(js);
                    translatetext.init(self.controls.results);
                    // On the lesson's last item, the results page is where the next button finishes the lesson.
                    self.quizhelper.set_finish_label(self.index, true);
                    self.controls.nextbutton.show();
                    self.scroll_to_top();
                    return html;
                }
            ).catch(notification.exception);
        },

        next_question: function() {
            var self = this;
            var rd = self.resultsdata || self.results_data();
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = !!self.itemdata.graded;
            stepdata.totalitems = rd.totalitems;
            stepdata.correctitems = rd.correctitems;
            stepdata.grade = rd.totalitems > 0 ? Math.round((rd.correctitems / rd.totalitems) * 100) : 0;
            stepdata.resultsdata = rd;
            self.quizhelper.do_next(stepdata);
        },

    };
});
