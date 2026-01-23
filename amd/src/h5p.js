define(
    ['jquery', 'core/log', 'mod_minilesson/definitions'],
    function ($, log, def, anim) {
        "use strict"; // jshint ;_;

    /*
    This file is to manage the H5P item type
    */

        log.debug('MiniLesson H5P: initialising');

        return {

          //for making multiple instances
            clone: function () {
                return $.extend(true, {correctitems: 0, totalitems: 0}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.register_events(index, itemdata, quizhelper);
                log.debug('MiniLesson H5P URL: ' + itemdata.h5purl);
                log.debug('MiniLesson H5P Total Marks: ' + itemdata.totalmarks);

            },

            next_question: function () {
                var self = this;
                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.totalitems;
                stepdata.correctitems = self.correctitems;
                stepdata.grade = self.totalitems > 0 ? 100 * self.correctitems / self.totalitems : 0;
                self.quizhelper.do_next(stepdata);
            },

            register_events: function (index, itemdata, quizhelper) {

                var self = this;
                self.index = index;
                self.quizhelper = quizhelper;
                var h5pplayer = $("#" + itemdata.uniqueid + "_h5pplayer");
                var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");

                nextbutton.on('click', function (e) {
                            self.next_question();
                });

                var iframe = h5pplayer.find('iframe')[0];
                if (iframe) {
                            addEventListener('message', event => {
                                if (window === event.target && event.data.context === 'h5p' && !iframe.getAttribute('data-gradelistener')) {
                                    iframe.contentWindow.H5P.externalDispatcher.on('xAPI', function (event) {
                                        if (event.getMaxScore() > 0) {
                                            console.log('gradehit');
                                            self.correctitems = event.getScore();
                                            self.totalitems = event.getMaxScore();
                                        }
                                    });
                                    iframe.setAttribute('data-gradelistener', 1);
                                }
                            });
                }

            },

        };
    }
);