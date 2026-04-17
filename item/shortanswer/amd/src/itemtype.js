define(
    ['jquery',
      'core/log',
      'mod_minilesson/definitions',
      'mod_minilesson/pollyhelper',
      'mod_minilesson/ttrecorder',
      'mod_minilesson/animatecss'],
    function ($, log, def, polly, ttrecorder, anim) {
        "use strict"; // jshint ;_;

    /*
    This file is to manage the quiz stage
    */

        log.debug('MiniLesson ShortAnswer: initialising');

        return {

          //a handle on the tt recorder
            ttrec: null,

            passmark: 100,//lower this if it often doesnt match (was 85)

            fullycorrect: false,

            partiallycorrect: false,

          //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {

      //anim
                var animopts = {};
                animopts.useanimatecss = quizhelper.useanimatecss;
                anim.init(animopts);
                this.itemdata = itemdata;

                this.register_events(index, itemdata, quizhelper);
                this.init_components(index, itemdata, quizhelper);
            },
            next_question: function () {
                var self = this;
                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.itemdata.correctmarks;
                stepdata.correctitems = 0;
                if (self.fullycorrect) {
                            stepdata.correctitems = self.itemdata.correctmarks;
                } else if (self.partiallycorrect) {
                          stepdata.correctitems = self.itemdata.partialmarks;
                }
                if (stepdata.correctitems > 0) {
                        stepdata.grade = 100 * stepdata.correctitems / stepdata.totalitems;
                }
                log.debug('stepdata');
                log.debug(stepdata);
                self.quizhelper.do_next(stepdata);
            },

          /* NOT NEEDED */
            prepare_audio: function (itemdata) {
      // debugger;
                $.each(itemdata.sentences, function (index, sentence) {
                            polly.fetch_polly_url(sentence.sentence, itemdata.voiceoption, itemdata.usevoice).then(function (audiourl) {
                                $("#" + itemdata.uniqueid + "_option" + (index + 1)).attr("data-src", audiourl);
                            });
                });
            },

            register_events: function (index, itemdata, quizhelper) {

                var self = this;
                self.index = index;
                self.quizhelper = quizhelper;

                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function (e) {
                    self.next_question();
                });

                $("#" + itemdata.uniqueid + "_container ." + itemdata.uniqueid + "_option").on('click', function (e) {

                    $("." + itemdata.uniqueid + "_option").prop("disabled", true);
                    $("." + itemdata.uniqueid + "_fb").html("<i style='color:red;' class='fa fa-times'></i>");
                    $("." + itemdata.uniqueid + "_option" + itemdata.correctanswer + "_fb").html("<i style='color:green;' class='fa fa-check'></i>");

                    $(".minilesson_nextbutton").prop("disabled", true);
                    setTimeout(function () {
                        $(".minilesson_nextbutton").prop("disabled", false);
                        self.next_question();
                    }, 2000);

                });

            },

            init_components: function (index, itemdata, quizhelper) {
                var app = this;
                var sentences = itemdata.sentences;//sentence & phonetic
                var partialresponses = itemdata.partialresponses;

                log.debug('initcomponents_shortanswer');
                log.debug(sentences);

      //clean the text of any junk
                for (var i = 0; i < sentences.length; i++) {
                            sentences[i].originalsentence = sentences[i].sentence;
                            sentences[i].sentence = quizhelper.cleanText(sentences[i].sentence);
                }

                for (var i = 0; i < partialresponses.length; i++) {
                          partialresponses[i].originalsentence = partialresponses[i].sentence;
                          partialresponses[i].sentence = quizhelper.cleanText(partialresponses[i].sentence);
                }

                var processFeedback = async function (speechtext) {
                        var cleanspeechtext = quizhelper.cleanText(speechtext);
                        var spoken = cleanspeechtext;

                        log.debug('speechtext:',speechtext);
                        log.debug('cleanspeechtext:',spoken);

                        var matched = false;
                        var percent = 0;

                        //Similarity check by direct-match/acceptable-mistranscriptio
                    for (var x = 0; x < sentences.length; x++) {
          //if this is the correct answer index, just move on
                        if (sentences[x].sentence === '') {
                            continue;}
                        var similar = quizhelper.similarity(spoken, sentences[x].sentence);
                        log.debug('JS similarity: ' + spoken + ':' + sentences[x].sentence + ':' + similar);
                        if (similar >= app.passmark ||
                        app.spokenIsCorrect(quizhelper, cleanspeechtext, sentences[x].sentence)) {
                            percent = app.process_accepted_response(itemdata, x);
                            matched = true;
                            break;
                        }//end of if similarity
                    }//end of for x

                  //Similarity check by phonetics(ajax)
                  //this is an expensive call since it goes out to the server and possibly to the cloud
                    if (!matched) {
                        for (x = 0; x < sentences.length; x++) {
                              var similarity = await quizhelper.checkByPhonetic(sentences[x].sentence, spoken, sentences[x].phonetic, itemdata.language);
                              log.debug(similarity, 'PHP similarity');
                            if (!similarity || similarity < app.passmark) {
                        //keep looking
                            } else {
                                matched = true;
                                log.debug('PHP similarity: ' + spoken + similarity);
                                percent = app.process_accepted_response(itemdata, x);
                                break;
                            }
                        }//end of Similarity check by phonetics(ajax) loop
                    }

        //we do not do a passage match check , but this is how we would ..
                    if (!matched ) {
                        for (x = 0; x < sentences.length; x++) {
                            var ajaxresult = await quizhelper.comparePassageToTranscript(sentences[x].sentence, spoken, sentences[x].phonetic, itemdata.language, itemdata.alternates);
                            var result = JSON.parse(ajaxresult);
                            var haserror = false;
                            for (var i = 0; i < result.length; i++) {
                                if (result[i].matched === false) {
                                    haserror = true;break;}
                            }
                            if (!haserror) {
                                percent = app.process_accepted_response(itemdata, x);
                                matched = true;
                                break;
                            }
                        }
                    }
                    app.fullycorrect = matched;

                    if (!matched) {
                        //Similarity check by direct-match/acceptable-mistranscriptio
                        for (var x = 0; x < partialresponses.length; x++) {
                            //if this is the correct answer index, just move on
                            if (partialresponses[x].sentence === '') {
                                continue;}
                            var similar = quizhelper.similarity(spoken, partialresponses[x].sentence);
                            log.debug('JS similarity: ' + spoken + ':' + partialresponses[x].sentence + ':' + similar);
                            if (similar >= app.passmark ||
                            app.spokenIsCorrect(quizhelper, cleanspeechtext, partialresponses[x].sentence)) {
                                percent = app.process_accepted_response(itemdata, x);
                                matched = true;
                                break;
                            }//end of if similarity
                        }//end of for x
                    }

        //Similarity check by phonetics(ajax)
        //this is an expensive call since it goes out to the server and possibly to the cloud
                    if (!matched) {
                        for (x = 0; x < partialresponses.length; x++) {
                            var similarity = await quizhelper.checkByPhonetic(partialresponses[x].sentence, spoken, partialresponses[x].phonetic, itemdata.language);
                            log.debug(similarity, 'PHP similarity');
                            if (!similarity || similarity < app.passmark) {
                              //keep looking
                            } else {
                                matched = true;
                                log.debug('PHP similarity: ' + spoken + similarity);
                                percent = app.process_accepted_response(itemdata, x);
                                break;
                            }
                        }//end of Similarity check by phonetics(ajax) loop
                    }

                    if (!matched ) {
                        for (x = 0; x < partialresponses.length; x++) {
                            var ajaxresult = await quizhelper.comparePassageToTranscript(partialresponses[x].sentence, spoken, partialresponses[x].phonetic, itemdata.language, itemdata.alternates);
                            var result = JSON.parse(ajaxresult);
                            var haserror = false;
                            for (var i = 0; i < result.length; i++) {
                                if (result[i].matched === false) {
                                    haserror = true;break;}
                            }
                            if (!haserror) {
                                percent = app.process_accepted_response(itemdata, x);
                                matched = true;
                                break;
                            }
                        }
                    }

                    app.partiallycorrect = matched;

        //if we got a match then process it
                    if (matched) {
                        //proceed to next question
                        $(".minilesson_nextbutton").prop("disabled", true);
                        setTimeout(function () {
                            $(".minilesson_nextbutton").prop("disabled", false);
                            app.next_question();
                        }, 2000);
                        return;
                    } else {
                        //shake the screen
                        var theanswer = $("#" + itemdata.uniqueid + "_correctanswer");
                        if (!itemdata.audiorecorder) {
                            theanswer = $("#" + itemdata.uniqueid + "_container .textinput_responsetype");
                        }
                        anim.do_animate(theanswer,'rubberBand animate__faster').then(
                            function () {}
                        );
                        //$("#" + itemdata.uniqueid + "_correctanswer").effect("shake");
                    }
                }

                var theCallback = async function (message) {

                    switch (message.type) {
                        case 'recording':

                    break;

                        case 'speech':
                            log.debug("speech at shortanswer");
                            log.debug(message.capturedspeech);
                            app.itemdata.audiotextanswer = message.capturedspeech;
                            await processFeedback(message.capturedspeech);
                    } //end of switch message type
                }; //end of callback declaration

                if (itemdata.audiorecorder) {
                      //init TT recorder
                      var opts = {};
                      opts.uniqueid = itemdata.uniqueid;
                      log.debug('sa uniqueid:' + itemdata.uniqueid);
                      opts.callback = theCallback;
                      opts.stt_guided = quizhelper.is_stt_guided();
                      app.ttrec = ttrecorder.clone();
                      app.ttrec.init(opts);

                      //set the prompt for TT Rec
                      var allsentences = "";
                    for (var i = 0; i < sentences.length; i++) {
                        allsentences += sentences[i].sentence + ' ';
                        sentences[i].originalsentence = sentences[i].sentence;
                        sentences[i].sentence = quizhelper.cleanText(sentences[i].sentence);
                    }
                    app.ttrec.currentPrompt = allsentences;
                } else {
                    $("#" + itemdata.uniqueid + "_container .shortanswer_check_btn").on('click', function (e) {
                        var textinput = $("#" + itemdata.uniqueid + "_container .textinput_responsetype");
                        var textinputvalue = textinput.val();
                        if (!textinputvalue) {
                            return;
                        }
                        processFeedback(textinputvalue);
                    });
                }

            } ,//end of init components

            spokenIsCorrect: function (quizhelper, phraseheard, currentphrase) {
      //lets lower case everything
                phraseheard = quizhelper.cleanText(phraseheard);
                currentphrase = quizhelper.cleanText(currentphrase);
                if (phraseheard === currentphrase) {
                            return true;
                }
                return false;
            },

            process_accepted_response: function (itemdata, sentenceindex) {
                var percent = sentenceindex >= 0 ? itemdata.correctmarks : 0;
      //TO DO .. disable TT recorder here
      //disable TT recorder

                if (percent > 0) {
                            //turn dots into text (if they were dots)
                    if (parseInt(itemdata.show_text) === 0) {
                        for (var i = 0; i < itemdata.sentences.length; i++) {
                            if (itemdata.audiorecorder) {
                                $("#" + itemdata.uniqueid + "_option" + (i + 1) + ' .minilesson_sentence').text(itemdata.sentences[i].sentence);
                            }
                        }
                    }

                    if (itemdata.audiorecorder) {
                    //hightlight successgit cm
                        var  answerdisplay =  $("#" + itemdata.uniqueid + "_correctanswer");
                        answerdisplay.text(itemdata.audiotextanswer);
                        answerdisplay.addClass("minilesson_success");
                    } else {
                        var textinput = $("#" + itemdata.uniqueid + "_container .textinput_responsetype");
                        textinput.addClass('textinput_success');
                    }
                }

                return percent;

            },

        };
    }
);