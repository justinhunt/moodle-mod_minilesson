define([
    'jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/yarn-bound',
    'core/str', 'core/modal_factory', 'core/fragment','core/templates'
],
    function ($, log, def, YarnBound, Str, ModalFactory, Fragment, Templates) {
        "use strict"; // jshint ;_;

        /*
       This file is to manage the fiction item type
        */

        log.debug('MiniLesson Fiction: initialising');

        return {

            runner: null,
            controls: {},
            itemdata: {},

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.prepare_html(itemdata);
                this.register_events(index, itemdata, quizhelper);
                var yarnopts = {
                    "dialogue": itemdata.fictionyarn,
                    "combineTextAndOptionsResults": true,
                    "startAt": "Start"
                };
                log.debug('MiniLesson Fiction: initializing yarnbound with options');
                log.debug(yarnopts);
                try{
                    this.runner = new YarnBound(yarnopts);
                    this.do_render();
                } catch (e) {
                    var userFriendlyError = "Yarn Parse Error: " + e.message;
                    this.controls.yarncontainer.html(
                        `<div class="alert alert-danger">${userFriendlyError}</div>`
                    );
                    log.error("Full Yarn Error:");
                    log.error(e);
                }
                
            },

            prepare_html: function (itemdata) {
                this.controls.yarncontainer = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_yarncontainer");
                this.controls.yarntext = this.controls.yarncontainer.find('.minilesson_fiction_yarntext');
                this.controls.yarnimage = this.controls.yarncontainer.find('.minilesson_fiction_yarnimage');
                this.controls.yarnoptions = this.controls.yarncontainer.find('.minilesson_fiction_yarnoptions');
                this.controls.yarncontinuebutton = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_continuebutton");

            },

            do_render: function () {
                var that = this;
                var yarncontent = {
                    'yarntext': false,
                    'yarnoptions': false,
                    'yarnimage': false,
                };

                var currentResult = this.runner.currentResult;
                currentResult = this.add_metadata(currentResult);
                log.debug('MiniLesson Fiction: doing render of currentResult');
                log.debug(currentResult);

                if(currentResult instanceof YarnBound.TextResult) {

                    // Render the text
                    yarncontent.yarntext = currentResult;
                    Templates.render('mod_minilesson/fictionyarntext', yarncontent.yarntext).then(
                    function (html, js) {
                        that.controls.yarntext.html(html);
                        that.controls.yarnoptions.html('');
                    });
                    // Enable the continue button
                    this.can_continuebutton(true);

                } else if(currentResult instanceof YarnBound.OptionsResult) {
                    // Render the options
                    yarncontent.yarnoptions = currentResult;
                    Templates.render('mod_minilesson/fictionyarnoptions', yarncontent.yarnoptions).then(
                    function (html, js) {
                        that.controls.yarnoptions.html(html);
                    });

                    //If there is some text as well render that, or clear the existing text otherwise
                    if('text' in yarncontent.yarnoptions){
                         Templates.render('mod_minilesson/fictionyarntext', yarncontent.yarnoptions).then(
                        function (html, js) {
                            that.controls.yarntext.html(html);
                        });
                    } else {
                        that.controls.yarntext.html('');
                    }


                    // Disable the continue button, because they need to select an option
                    that.can_continuebutton(false);

                } else if (currentResult instanceof YarnBound.CommandResult) {
                    // Process the command string a little. so we have command name and args
                    //eg "picture 1.png"
                    var rawCommand = currentResult.command; 
                    var parts = rawCommand.split(' ');
                    var commandName = parts[0]; // "picture"
                    var args = parts.slice(1); // ["1.png"]


                    switch(commandName) {
                        case 'picture':
                            log.debug('got picture command')
                            const imageURL = args[0]; // "picture https://blahblah"
                            Templates.render('mod_minilesson/fictionyarnimage', {"imageurl": imageURL}).then(
                            function (html, js) {
                                that.controls.yarnimage.html(html);
                            });
                            break;

                        case 'clearpicture': 
                            that.controls.yarnimage.html('');
                            break;

                        case 'blahblah':
                        default:
                            
                    }
                    // In all cases just do command and then jump to next line
                    if(!currentResult.isDialogueEnd) {
                        // Just skip through for now
                        that.do_runner_advance();
                        that.do_render();
                        return;
                    }
                } else {
                    log.debug('MiniLesson Fiction: unknown yarn result type');
                }

                 // In all cases on dialog end there is no continue
                if(currentResult.isDialogueEnd) {
                    that.can_continuebutton(false);
                    return;
                }
            },

            do_runner_advance: function (steps) {
                try {
                    if(steps !== null){
                        this.runner.advance(steps);
                    } else {
                        this.runner.advance();
                    }
                } catch (e) {
                    var userFriendlyError = "Yarn Parse Error: " + e.message;
                    this.controls.yarncontainer.html(
                        `<div class="alert alert-danger">${userFriendlyError}</div>`
                    );
                    log.error("Full Yarn Error:");
                    log.error(e);
                }
            },

            can_continuebutton: function (cancontinue) {
                this.controls.yarncontinuebutton.prop("disabled", !cancontinue);
                if (cancontinue){
                    this.controls.yarncontinuebutton.show();
                } else {
                    this.controls.yarncontinuebutton.hide();
                }
               
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
                        //this.do_render();
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


                this.controls.yarncontinuebutton.on('click', function (e) {
                    log.debug('MiniLesson Fiction: yarn continue button clicked');
                    e.preventDefault();
                    self.do_runner_advance();
                    self.do_render();
                });

                // add an event listener for option buttons that handles option buttons added at runtim
                this.controls.yarncontainer.on('click', '.minilesson_fiction_optionbutton', function (e) {
                    log.debug('MiniLesson Fiction: yarn option button clicked');
                    e.preventDefault();

                    var buttons = self.controls.yarncontainer.find('.minilesson_fiction_optionbutton');
                    var optionindex = buttons.index(this); // 0-based position in the rendered list
                    self.do_runner_advance(optionindex);
                    self.do_render();
                });
            },

            add_metadata: function (currentthing) {
                if (currentthing && 'markup' in currentthing && Array.isArray(currentthing.markup)) {
                    currentthing.md = {};
                    //for each markup entry, add the property name and values object to currentthing
                    currentthing.markup.forEach(function (entry) {
                        if ('properties' in entry && 'name' in entry) {
                            currentthing.md[entry.name] = entry.properties;
                        }
                    });
                }
                return currentthing;
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