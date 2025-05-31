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
            return $.extend(true, {}, this);
        },

        controls: {},
        gapitems: [],

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
            self.controls.audioplayer =$("#" + self.itemdata.uniqueid + "_container .pgapfill_audio_player");
            self.controls.resultsbox = $("#" + self.itemdata.uniqueid + "_container .pgapfill_resultscontainer");
            self.controls.checkbtn = $("#" + self.itemdata.uniqueid + "_container .ml_checkbutton");
            self.controls.hintbtn = $("#" + self.itemdata.uniqueid + "_container .ml_hintbutton");
            self.controls.nextbtn = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
        },

        prepare_audio: function() {
            var self = this;
            polly.fetch_polly_url(self.itemdata.passagedata.plaintext, self.itemdata.voiceoption, self.itemdata.usevoice).then(function(audiourl) {
                self.controls.audioplayer.attr("src", audiourl);
            });
        },

        next_question: function() {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function(e) {
                return e.correct;
            }).length;
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);
            self.quizhelper.do_next(stepdata);
        },

        show_item_review:function(){
            var self=this;
            var review_data = {};
            review_data.items = self.items;
            review_data.totalitems=self.items.length;
            review_data.correctitems=self.items.filter(function(e) {return e.correct;}).length;


            //display results
            templates.render('mod_minilesson/passagegapfillresults',review_data).then(
              function(html,js){
                  resultsbox.html(html);
                  //show and hide
                  resultsbox.show();


                  // Run js for audio player events
                  templates.runTemplateJS(js);
              }
            );// End of templates
        },

        register_events: function() {

            var self = this;

            self.controls.nextbtn.on('click', function(e) {
                self.next_question();
            });


            self.controls.hintbtn.on("click", function() {
                self.give_hint();
            });

            self.controls.checkbtn.on("click", function() {
                self.check_answer();
            });


        },


        check_answer: function() {

        },

        give_hint: function() {

        },



        getGapItems: function() {
            //TO DO implement this
            // This function prepares the gap items from the passage data.
           log.debug("getting gap items");

            var self = this;
            var passagedata = self.itemdata.passagedata;

            //We probably want something like this to track the inputboxes and assoc data so we can work with it from JS - right now it doesnt do anything special
            //check item_passagegapfill exportfortemplate function to prepare the passagedata to use here
            //Alternatively any data we need could also be set as attributes on divs in mustache template and picked up here.
            self.gapitems = passagedata.words.map(function(target) {
                return {
                    wordindex: target.wordindex,
                    text: target.text,
                    placeholder: target.placeholder,
                    isgap: target.isgap,
                };
            }).filter(function(e) {
                return e.target !== "";
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