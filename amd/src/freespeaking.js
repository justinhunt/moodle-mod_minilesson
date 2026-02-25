define(
    ['jquery', 'core/log', 'mod_minilesson/definitions',
    'mod_minilesson/ttrecorder', 'mod_minilesson/correctionsmarkup', 'core/templates'],
    function ($, log, def, ttrecorder, correctionsmarkup, templates) {
        "use strict"; // jshint ;_;

      /*
      This file is to manage the free speaking item type
       */

        log.debug('MiniLesson FreeSpeaking: initialising');

        return {

            transcript_evaluation: null,
            rawscore: 0,
            percentscore: 0,
            autosubmitmode: false,
            mediaurl: false,
            bloburl: false,

          //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                log.debug('itemdata', itemdata);
                this.quizhelper = quizhelper;
                this.init_components(quizhelper, itemdata);
                this.register_events(index, itemdata, quizhelper);

            },

            next_question: function () {
                var self = this;
                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.lessonitemid = self.itemdata.id;
                stepdata.totalitems = self.itemdata.totalmarks;
                stepdata.correctitems = self.rawscore > 0 ? self.rawscore : 0;
                stepdata.grade = self.percentscore;
              //Add media url to transcript evaluation before we save it
                if (self.transcript_evaluation && self.mediaurl) {
                    self.transcript_evaluation.mediaurl = self.mediaurl;
                }
                stepdata.resultsdata = self.transcript_evaluation;
                self.quizhelper.do_next(stepdata);
            },

            calculate_score: function (transcript_evaluation) {
                var self = this;

                if (transcript_evaluation === null) {
                    return 0;
                }

              //words ratio
                var wordsratio = 1;
                if (self.itemdata.countwords) {
                    wordsratio = transcript_evaluation.stats.words / self.itemdata.targetwordcount;
                    if (wordsratio > 1) {
                        wordsratio = 1; }
                }

              //relevance
                var relevanceratio = 1;
                if (self.itemdata.relevance > 0) {
                    relevanceratio = (transcript_evaluation.stats.relevance + 10) / 100;
                    if (relevanceratio > 1) {
                        relevanceratio = 1; }
                }
              //calculate score based on AI grade * relevance * wordcount
                var score = Math.round(transcript_evaluation.marks * relevanceratio * wordsratio);
                return score;
            },

            register_events: function (index, itemdata, quizhelper) {

                var self = this;
                self.index = index;
                self.quizhelper = quizhelper;
                var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");

                nextbutton.on('click', function (e) {
                    self.next_question();
                });

                $("#" + itemdata.uniqueid + "_container").on('showElement', () => {
                    if (itemdata.timelimit > 0) {
                        $("#" + itemdata.uniqueid + "_container .progress-container").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container i").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                            height: '5px',
                            timeLimit: itemdata.timelimit,
                            onFinish: function () {
                                self.ontimelimitreached();
                            }
                          });
                    }
                });

            },

            ontimelimitreached: function () {
                var self = this;
                if (self.ttrec && self.ttrec.audio && (self.ttrec.audio.isRecording || self.ttrec.audio.transcript)) {
                    if (self.ttrec.audio.isRecording) {
                        self.autosubmitmode = true;

                        if (self.ttrec.usebrowserrec) {
                            self.ttrec.browserrec.stop();
                        } else {
                            self.ttrec.audiohelper.stop();
                        }
                    } else if (self.ttrec.audio.transcript && !self.transcript_evaluation) {
                        self.autosubmitmode = true;
                        self.do_evaluation(self.ttrec.audio.transcript);
                    }
                } else if (!self.transcript_evaluation) {
                    var nextbutton = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
                    nextbutton.trigger('click');
                }
            },

            init_components: function (quizhelper, itemdata) {
                var self = this;
                self.allwords = $("#" + self.itemdata.uniqueid + "_container.mod_minilesson_mu_passage_word");
                self.thebutton = "thettrbutton"; // To Do impl. this
                self.wordcount = $("#" + self.itemdata.uniqueid + "_container span.ml_wordcount");
                self.actionbox = $("#" + self.itemdata.uniqueid + "_container div.ml_freespeaking_actionbox");
                self.pendingbox = $("#" + self.itemdata.uniqueid + "_container div.ml_freespeaking_pendingbox");
                self.resultsbox = $("#" + self.itemdata.uniqueid + "_container div.ml_freespeaking_resultsbox");
                self.timerdisplay = $("#" + self.itemdata.uniqueid + "_container div.ml_freespeaking_timerdisplay");
                self.iteminstructions = $("#" + self.itemdata.uniqueid + "_container div.mod_minilesson_iteminstructions");
                self.itemtext = $("#" + self.itemdata.uniqueid + "_container div.mod_minilesson_itemtext");
                self.finishedmessage = $("#" + self.itemdata.uniqueid + "_container div.ml_freespeaking_finishedmessage");
              // Callback: Recorder updates.
                var recorderCallback = function (message) {

                    switch (message.type) {
                        case 'recording':
                        break;

                        case 'interimspeech':
                            var wordcount = self.quizhelper.count_words(message.capturedspeech);
                            self.wordcount.text(wordcount);
                        break;

                        case 'speech':
                            var speechtext = message.capturedspeech;

                          //update the wordcount
                            var wordcount = self.quizhelper.count_words(speechtext);
                            self.wordcount.text(wordcount);
                            self.do_evaluation(speechtext);
                        break;

                        case 'mediasaved':
                            log.debug('Mediaurl saved at passage reading: ' + message.mediaurl);
                            self.mediaurl = message.mediaurl;
                            self.bloburl = message.bloburl;
                        break;
                    } //end of switch message type
                };



              //init tt recorder
                var opts = {};
                opts.uniqueid = itemdata.uniqueid;
                opts.callback = recorderCallback;
                opts.stt_guided = false
                self.ttrec = ttrecorder.clone();
                self.ttrec.init(opts);

            }, //end of init components

            do_corrections_markup: function (grammarerrors, grammarmatches, insertioncount) {
                var self = this;
              //corrected text container is created at runtime, so it wont exist at init_components time
              //thats we find it here
                var correctionscontainer = self.resultsbox.find('.mlfsr_correctedtext');
                correctionsmarkup.init({
                    "correctionscontainer": correctionscontainer,
                    "grammarerrors": grammarerrors,
                    "grammarmatches": grammarmatches,
                    "insertioncount": insertioncount
                });
            },

            do_evaluation: function (speechtext) {
                var self = this;

              //show a spinner while we do the AI stuff
                self.resultsbox.hide();
                self.actionbox.hide();
                self.pendingbox.show();

              //do evaluation
                this.quizhelper.evaluateTranscript(speechtext, this.itemdata.itemid).then(function (ajaxresult) {
                    var transcript_evaluation = JSON.parse(ajaxresult);
                    if (transcript_evaluation) {
                        transcript_evaluation.reviewsettings = self.itemdata.reviewsettings;
                      //calculate raw score and percent score
                        transcript_evaluation.rawscore = self.calculate_score(transcript_evaluation);
                        self.rawscore = self.calculate_score(transcript_evaluation);
                        self.percentscore = 0;
                        if (self.itemdata.totalmarks > 0) {
                            self.percentscore = Math.round((self.rawscore / self.itemdata.totalmarks) * 100);
                        }
                        if (isNaN(self.percentscore)) {
                            self.percentscore = 0;
                        }
                      //add raw and percent score to trancript_evaluation for mustache
                        transcript_evaluation.rawscore = self.rawscore;
                        transcript_evaluation.percentscore = self.percentscore;
                        transcript_evaluation.rawspeech = speechtext;
                        transcript_evaluation.maxscore = self.itemdata.totalmarks;

                      // If we have a media url from our recording lets use it
                      // We will just use the blob version here (its local and s3/mp3 may not be ready)
                        if (self.bloburl) {
                            transcript_evaluation.mediaurl = self.bloburl;
                        }

                      // And save it upstairs
                        self.transcript_evaluation = transcript_evaluation;

                        var ystarcnt = 0;
                        var gstarcnt;
                        const templatedata = Object.assign({}, transcript_evaluation);
                        if (transcript_evaluation.reviewsettings.showscorestarrating) {
                            if (self.percentscore == 0) {
                                ystarcnt = 0;
                            } else if (self.percentscore < 19) {
                                ystarcnt = 1;
                            } else if (self.percentscore < 39) {
                                ystarcnt = 2;
                            } else if (self.percentscore < 59) {
                                ystarcnt = 3;
                            } else if (self.percentscore < 79) {
                                ystarcnt = 4;
                            } else {
                                ystarcnt = 5;
                            }

                            gstarcnt = 5 - ystarcnt;
                            templatedata.yellowstars = new Array(ystarcnt).fill(M.cfg, 0, ystarcnt);
                            templatedata.graystars = new Array(gstarcnt).fill(M.cfg, 0, gstarcnt);
                        }

                        log.debug(templatedata);
                      //display results or move next if not show item review
                        if (!self.quizhelper.showitemreview && !self.autosubmitmode) {
                            self.next_question();
                        } else {
                          //display results
                            templates.render('mod_minilesson/freespeakingresults', templatedata).then(
                                function (html, js) {
                                    self.resultsbox.html(html);
                                    //do corrections markup
                                    if (transcript_evaluation.hasOwnProperty('grammarerrors')) {
                                        self.do_corrections_markup(
                                            transcript_evaluation.grammarerrors,
                                            transcript_evaluation.grammarmatches,
                                            transcript_evaluation.insertioncount
                                        );
                                    }
                                    //show and hide
                                    self.iteminstructions.hide();
                                    self.itemtext.hide();
                                    self.finishedmessage.hide();
                                    self.resultsbox.show();
                                    self.pendingbox.hide();
                                    self.actionbox.hide();
                                    templates.runTemplateJS(js);
                                    //reset timer and wordcount on this page, in case reattempt
                                    self.wordcount.text('0');
                                    self.ttrec.timer.stop();
                                    self.ttrec.timer.reset();
                                    var displaytime = self.ttrec.timer.fetch_display_time();
                                    self.timerdisplay.html(displaytime);
                                }
                            );// End of templates
                            if (self.itemdata.timelimit > 0) {
                                let timerelementcontainer = $("#" + self.itemdata.uniqueid + "_container .progress-container");
                                timerelementcontainer.hide();
                                var timerelement = $("#" + self.itemdata.uniqueid + "_container .progress-container #progresstimer");
                                var timerinterval = timerelement.attr('timer');
                                if (timerinterval) {
                                      clearInterval(timerinterval);
                                }
                            }
                        } //end of if show item review
                    } else {
                        log.debug('transcript_evaluation: oh no it failed');
                        self.resultsbox.hide();
                        self.pendingbox.hide();
                        self.actionbox.show();
                    }
                });
            },
        };
    }
);