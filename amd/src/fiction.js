define([
    'jquery', 'core/log', 'mod_minilesson/definitions',
    'core/str', 'core/modal_factory', 'core/fragment'
],
    function ($, log, def, Str, ModalFactory, Fragment) {
        "use strict"; // jshint ;_;

        /*
       This file is to manage the fiction item type
        */

        log.debug('MiniLesson Fiction: initialising');

        return {

            instance: null,

            itemdata: {},

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.register_events(index, itemdata, quizhelper);
            },

            prepare_html: function (itemdata) {
                //do something
            },

            register_events: function (index, itemdata, quizhelper) {
                var self = this;
                //When click next button , report and leave it up to parent to eal with it.
                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function (e) {
                    var stepdata = {};
                    stepdata.index = index;
                    stepdata.hasgrade = false;
                    stepdata.totalitems = 0;
                    stepdata.correctitems = 0;
                    stepdata.grade = 0;
                    quizhelper.do_next(stepdata);
                });
                $("#" + itemdata.uniqueid + "_container").on("showElement", async (e) => {
                    if (!self.instance) {
                        // Maybe init yarn-bound here
                        self.instance.initialize();
                    }

                    if (itemdata.timelimit > 0) {
                        $("#" + itemdata.uniqueid + "_container .progress-container").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container i").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                            height: '5px',
                            timeLimit: itemdata.timelimit,
                            onFinish: function () {
                                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").trigger('click');
                            }
                        });
                    }
                });
            },

            register_previewbutton: function (buttonid, region = 'default') {
                var previewbtn = document.getElementById(buttonid);
                if (!previewbtn) {
                    return;
                }
                previewbtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var form = previewbtn.form;
                    if (!form) {
                        return;
                    }
                    ModalFactory.create({
                        type: ModalFactory.types.CANCEL,
                        large: true,
                        removeOnClose: true,
                        templateContext: {
                            classes: 'minilesson-fiction-preview-modal'
                        },
                        title: Str.get_string('fiction:previewmodaltitle', 'mod_minilesson'),
                        body: Fragment.loadFragment('mod_minilesson', 'preview_fiction', M.cfg.contextid, {
                            formdata: new URLSearchParams([...new FormData(form).entries()]).toString()
                        })
                    }).then(function (modal) {
                        modal.getFooter().addClass('d-none');
                        modal.show();
                    });
                    return;
                });
                
            }
        }; //end of return value
    });