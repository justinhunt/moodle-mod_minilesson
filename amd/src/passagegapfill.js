define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates'
], function($, log, ajax, def, polly, anim, progresstimer, templates) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson passage gap fill: initialising');

    return {

        // For making multiple instances
        clone: function() {
            return $.extend(true, {hintsused: 0}, this);
        },

        controls: {},
        gapitems: [],
        items: [],
        hintsused: 0,

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;

            // Animation. - we might use this to shake the incorrect text boxes or something. See how its used in speakinggapfill.js
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.register_controls();
           // self.prepare_audio(); .. we pass the audio url in from PHP now, so no need to prepare it here.
            self.register_events();
            self.getGapItems();
        },

        register_controls: function() {
            var self = this;
            self.controls.rootelement = document.querySelector(`#${self.itemdata.uniqueid}_container`);
            self.controls.audioplayer =$("#" + self.itemdata.uniqueid + "_container .pgapfill_audio_player");
            self.controls.resultsbox = $("#" + self.itemdata.uniqueid + "_container .passage_gapfill_results_actions");
            self.controls.finishbtn = $("#" + self.itemdata.uniqueid + "_container .ml_finishbutton");
            self.controls.hintbtn = $("#" + self.itemdata.uniqueid + "_container .ml_hintbutton");
            self.controls.nextbtn = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
        },

        prepare_audio: function() {
            var self = this;
            polly.fetch_polly_url(self.itemdata.passagedata.plaintext, self.itemdata.voiceoption,
                self.itemdata.usevoice).then(function(audiourl) {
                self.controls.audioplayer.attr("src", audiourl);
            });
        },

        next_question: function() {
            var self = this;
            var stepdata = self.get_stepdata();
            self.quizhelper.do_next(stepdata);
        },

        submit_grade: function() {
            var self = this;
            var stepdata = self.get_stepdata();
            self.quizhelper.report_step_grade(stepdata);
        },

        get_stepdata: function() {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(e => e.correct).length;
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);
            stepdata.penalty = self.hintsused * 30;
            stepdata.grade = Math.max(0, stepdata.grade - stepdata.penalty);
            return stepdata;
        },

        show_item_review:function(){
            var self=this;
            var review_data = self.get_stepdata();
            var resultsbox = self.controls.resultsbox;
            review_data.items = self.items;

            //display results
            templates.render('mod_minilesson/passagegapfillresults',review_data).then(
              function(html,js){
                  resultsbox.html(html);

                  // Run js for audio player events
                  templates.runTemplateJS(js);
              }
            );// End of templates
        },

        register_events: function() {

            var self = this;

            self.controls.nextbtn.on('click', function() {
                self.next_question();
            });


            self.controls.hintbtn.on("click", function(e) {
                e.preventDefault();
                self.give_hint();
            });

            self.controls.finishbtn.on("click", function(e) {
                e.preventDefault();
                self.check_answer([], true, true);
                // Prevent submit grade when finishing.
                // self.submit_grade();
                self.show_item_review();
            });

            self.controls.rootelement.addEventListener('input', e => {
                const inputelement = e.target;
                self.items.forEach(item => {
                    if (item.inputelement === inputelement) {
                        self.check_answer(item, false);
                    }
                });
            });


        },


        check_answer: function(items = null, displaywrong = true, readonly = false) {
            var self = this;
            items = [].concat(items);
            if (items.length === 0) {
                items = self.items;
            }
            self.items.map(gapitem => {
                if (!gapitem.inputelement) {
                    return;
                }
                const gapelement = gapitem.inputelement.parentElement;
                gapelement.classList.remove('psg_gapfill_wrong', 'psg_gapfill_correct');
                gapitem.correct = gapitem.inputelement.value === gapitem.text;
                if (gapitem.correct) {
                    gapelement.classList.add('psg_gapfill_correct');
                } else if (displaywrong) {
                    gapelement.classList.add('psg_gapfill_wrong');
                }
                if (readonly) {
                    if (self.quizhelper.showitemreview) {
                        gapitem.inputelement.value = gapitem.text;
                        gapelement.classList.add('pgapfill_gap_reviewing');
                    }
                    gapitem.inputelement.setAttribute('readonly', 'readonly');
                }
                return gapitem;
            });
        },

        give_hint: function() {
            var self = this;
            var anyhintdisplayed = false;
            self.items.forEach(element => {
                const inputelement = element.inputelement;
                if (!inputelement) {
                    return;
                }
                if (inputelement.value !== element.text) {
                    const placeholder = inputelement.placeholder;
                    const replaceposition = self.hintsused === 1 ? 1: element.placeholder.length - 1;
                    inputelement.placeholder = placeholder.slice(0, replaceposition) +
                        element.text[replaceposition] + placeholder.slice(replaceposition + 1);
                    inputelement.setAttribute('placeholder', inputelement.placeholder);
                    inputelement.value = '';
                    anyhintdisplayed = true;
                }
                inputelement.setAttribute('data-hints', 1 + self.hintsused);
            });
            if (anyhintdisplayed) {
                self.hintsused++;
            }
            if (self.hintsused >= parseInt(self.itemdata.hints)) {
                self.controls.hintbtn.remove();
            } else if (self.hintsused === 1 && self.hintsused < parseInt(self.itemdata.hints)) {
                self.controls.hintbtn.text(self.controls.hintbtn.data('alttext'));
            }
        },



        getGapItems: function() {
            //TO DO implement this
            // This function prepares the gap items from the passage data.
           log.debug("getting gap items");

            var self = this;
            var passagedata = self.itemdata.passagedata;

            //Track the inputboxes and assoc data so we can work with it from JS
            self.gapitems = passagedata.chunks.map(target => ({
                wordindex: target.wordindex,
                text: target.text,
                placeholder: target.placeholder,
                isgap: target.isgap,
                correct: false
            }));
            self.items = self.gapitems.filter(gapitem => gapitem.isgap).map(item => {
                item.inputelement = self.controls.rootelement
                    .querySelector(`.pgapfill_gap_input[data-wordindex="${item.wordindex}"]`);
                return item;
            });
        },

        appReady: function() {
            var self = this;
            self.start();
        },


        end: function() {
            var self = this;
            $(".minilesson_nextbutton").prop("disabled", true);

            //disable the buttons and go to next question or review
            setTimeout(function() {
                $(".minilesson_nextbutton").prop("disabled",false);
                if(self.quizhelper.showitemreview){
                    self.show_item_review();
                }else{
                    self.next_question();
                }
            }, 2000);
        },

        start: function() {
            var self = this;
            self.controls.question.show();

        },


        stopTimer: function(timer) {
            if (timer) {
                    clearInterval(timer);
            }
        },
    };
});