define(['jquery','core/log','mod_poodlltime/definitions','mod_poodlltime/popoverhelper'], function($,log,def,popoverhelper) {
    "use strict"; // jshint ;_;

    log.debug('Gradenow helper: initialising');

    return{
        //controls

        controls: {},
        currentmode: def.modegrading,

        constants: {
          REVIEWMODE_NONE: 0,
          REVIEWMODE_MACHINE: 1,
          REVIEWMODE_HUMAN: 2,
          REVIEWMODE_SCORESONLY: 3
        },

        //class definitions
        cd: {

            audioplayerclass: def.audioplayerclass,
            wordplayerclass: def.wordplayerclass,
            wordclass: def.wordclass,
            spaceclass: def.spaceclass,
            badwordclass: def.badwordclass,
            endspaceclass: def.endspaceclass,
            unreadwordclass:  def.unreadwordclass,
            unreadspaceclass:  def.unreadspaceclass,
            wpmscoreid: def.wpmscoreid,
            accuracyscoreid: def.accuracyscoreid,
            sessionscoreid: def.sessionscoreid,
            errorscoreid: def.errorscoreid,
            errorrateid: def.errorrateid,
            scrateid: def.scrateid,
            formelementwpmscore: def.formelementwpmscore,
            formelementaccuracy: def.formelementaccuracy,
            formelementsessionscore: def.formelementsessionscore,
            formelementendword: def.formelementendword,
            formelementtime: def.formelementtime,
            formelementerrors: def.formelementerrors,
            formelementselfcorrections: def.formelementselfcorrections,
            formelementnotes: def.formelementnotes,
            modebutton: def.modebutton,

            spotcheckmodebutton: def.spotcheckmodebutton,
            transcriptmodebutton: def.transcriptmodebutton,
            msvmodebutton: def.msvmodebutton,
            gradingmodebutton: def.gradingmodebutton,
            clearbutton: def.clearbutton,
            spotcheckmode: def.spotcheckmode,
            msvmode: def.msvmode,
            transcriptmode: def.transcriptmode,
            gradingmode: def.gradingmode,
            aiunmatched: def.aiunmatched,
            passagecontainer: def.passagecontainer,

            maybeselfcorrectedwordclass: def.maybeselfcorrectedwordclass,
            selfcorrectedwordclass: def.selfcorrectedwordclass,
            notesclass: def.notesclass,
            structuralclass: def.structuralclass,
            meaningclass: def.meaningclass,
            visualclass: def.visualclass,
            msvcontainerclass: def.msvcontainerclass

        },

        options: {
            enabletts: false,
            targetwpm: 100,
            ttslanguage: 'en',
            totalseconds: 60,
            allowearlyexit: false,
            timelimit: 60,
            enforcemarker: true,
            totalwordcount: 0,
            wpm: 0,
            accuracy: 0,
            sessionscore: 0,
            endwordnumber: 0,
            errorwords: {},
            activityid: null,
            attemptid: null,
            sesskey: null
        },


        init: function(config){

            //pick up opts from html
            var theid='#' + config['id'];
            var configcontrol = $(theid).get(0);
            if(configcontrol){
                var opts = JSON.parse(configcontrol.value);
                $(theid).remove();
            }else{
                //if there is no config we might as well give up
                log.debug('Gradenow helper js: No config found on page. Giving up.');
                return;
            }

            //register the controls
            this.register_controls();

            //stash important info
            this.options.activityid = opts['activityid'];
            this.options.attemptid = opts['attemptid'];
            this.options.sesskey = opts['sesskey'];
            this.options.enabletts = opts['enabletts'];
            this.options.ttslanguage = opts['ttslanguage'];
            this.options.targetwpm = opts['targetwpm'];
            this.options.allowearlyexit = opts['allowearlyexit'];
            this.options.timelimit = opts['timelimit'];
            this.options.reviewmode = opts['reviewmode'];
            this.options.readonly = opts['readonly'];
            this.options.totalwordcount = $('.' + this.cd.wordclass).length ;

            if(opts['sessiontime']>0){
                //session errors
                if(opts['sessionerrors']==='' || opts['sessionerrors']===null) {
                    this.options.errorwords = {};
                }else{
                    this.options.errorwords = JSON.parse(opts['sessionerrors']);
                }
                if(opts['selfcorrections']==='' || opts['selfcorrections']===null) {
                    this.options.selfcorrections = {};
                }else{
                    this.options.selfcorrections = JSON.parse(opts['selfcorrections']);
                }
                this.options.totalseconds=opts['sessiontime'];
                this.options.endwordnumber=opts['sessionendword'];
                this.options.sessionscore=opts['sessionscore'];
                this.options.accuracy=opts['accuracy'];
                this.options.wpm=opts['wpm'];

                //We may have session matches and AI data, if AI is turned on
                this.options.sessionmatches=JSON.parse(opts['sessionmatches']);
                this.options.aidata=opts['aidata'];
                if(this.options.aidata) {
                    this.options.transcriptwords = opts['aidata'].transcript.split(" ");

                    //remove empty elements ... these can get in there
                    this.options.transcriptwords = this.options.transcriptwords.filter(function (el) {
                        return el !== '';
                    });

                }else{
                    this.options.transcriptwords=[];
                }

                //if this has been graded, draw the gradestate
                this.redrawgradestate();
            }else{
                //set up our end passage marker
                this.options.endwordnumber = this.options.totalwordcount;
            }

            //add the endword marker
            var thespace = $('#' + this.cd.spaceclass + '_' + this.options.endwordnumber );
            thespace.addClass(this.cd.endspaceclass);

            //register events
           this.register_events();

            //initialise our audio duration. We need this to calc. wpm
            //but if allowearlyexit is false, actually we can skip waiting for audio.
            //After audio loaded(if nec.) we call processscores to init score boxe
            //TODO: really should get audio duration at recording time.
            var m = this;
            var processloadedaudio= function(){
                if(m.options.allowearlyexit){
                    //using the audio player duration is actually more accurate than aidata.sessiontime
                    //but it will give diff results to score used in autograding which when allowing earlyexit uses aiddata.sessiontime
                    // (aidata.sessiontime is the end time of last recognised word.)
                    //So to ensure consistency we also use the aidata.sessiontime here
                    if(m.options.aidata && m.options.aidata.sessiontime) {
                        m.options.totalseconds = m.options.aidata.sessiontime;
                    }else {
                        m.options.totalseconds = Math.round($('#' + m.cd.audioplayerclass).prop('duration'));
                    }
                }else{
                    m.options.totalseconds = m.options.timelimit;
                }
                //update form field
                m.controls.formelementtime.val(m.options.totalseconds);
                m.processscores();
            };

            //we used to use the audio player time, but we try not to, and anyway its best not to
            //depend on a duration being available. Audio might expire
            /*
            var audioplayer = $('#' + this.cd.audioplayerclass);
            if(audioplayer.prop('readyState')<1 && this.options.allowearlyexit){
                audioplayer.on('loadedmetadata',processloadedaudio);
            }else{
                processloadedaudio();
            }
            */
            processloadedaudio();

            //init our popover helper which sets up the button events
            this.init_popoverhelper();

        },

        //set up events related to popover helper
        init_popoverhelper: function(){
            var that =this;

            //when the user clicks the reject popover accept button, we arrive here
            popoverhelper.onReject=function(){
                var clickwordnumber = $(this).attr('data-wordnumber');

                //if nothing changed, just close the popover
                var nochange = (clickwordnumber in that.options.errorwords);
                if(nochange){
                    popoverhelper.remove();
                    return;
                }

                //if a new choice was made update things
                var playchain= that.fetchPlayChain(clickwordnumber);
                for(var wordindex = playchain.startword;wordindex<=playchain.endword;wordindex++){
                    if(!(wordindex in that.options.errorwords)){
                        if(wordindex==clickwordnumber) {
                            var theword = $('#' + that.cd.wordclass + '_' + wordindex);
                            var thespace = $('#' + that.cd.spaceclass + '_' + wordindex);
                            var themsv = {};
                            that.storewordstate(def.stateerror, wordindex, theword.text(), themsv);
                            theword.addClass(that.cd.badwordclass);
                            if (wordindex != playchain.endword) {
                                //  thespace.addClass(that.cd.spotcheckmode);
                            }
                        }
                    }
                }
                //that.markup_badspaces();
                that.markup_aiunmatchedspaces();
                that.processscores();
                popoverhelper.remove();
            };

            //when the user clicks the popover accept button, we arrive here
            popoverhelper.onAccept=function(){
                var clickwordnumber = $(this).attr('data-wordnumber');

                //if nothing changed, just close the popover
                var nochange = !(clickwordnumber in that.options.errorwords);
                if(nochange){
                    popoverhelper.remove();
                    return;
                }
                //if a new choice was made update things
                var playchain= that.fetchPlayChain(clickwordnumber);
                for(var wordindex = playchain.startword;wordindex<=playchain.endword;wordindex++){
                    if(wordindex in that.options.errorwords){
                        if(wordindex==clickwordnumber) {
                            delete that.options.errorwords[wordindex];
                            var theword = $('#' + that.cd.wordclass + '_' + wordindex);
                            var thespace = $('#' + that.cd.spaceclass + '_' + wordindex);
                            theword.removeClass(that.cd.badwordclass);
                        }
                    }
                }
                //that.markup_badspaces();
                that.markup_aiunmatchedspaces();
                that.processscores();
                popoverhelper.remove();
            };

            //when the user clicks the reject popover close button in msv mode, we arrive here
            popoverhelper.onMSVClose=function(){
                var clickwordnumber = $(this).attr('data-wordnumber');
                var msvResults = popoverhelper.fetchMSVResults();

                that.markupPlayChain(msvResults.state,clickwordnumber,msvResults.msv,that);

                that.markup_aiunmatchedspaces();
                that.processscores();
                popoverhelper.remove();
            };

            //init the popover now that we have set the correct callback event handling thingies
            popoverhelper.init();
        },

        getMSVBadge: function(msv){
            var items = '';

            //if we have no MSV just move on
            if(!msv) {
                return false;
            }

            //if have msv see whats there.
            if(msv.m && msv.m==="1"){
                items+="M";
            }
            if(msv.s && msv.s==="1"){
                items+="S";
            }
            if(msv.v && msv.v==="1"){
                items+="V";
            }
            if(items===''){
                return false;
            }else{
                return items;
            }
        },

        getMSVClasses: function(msv){
            var addclasses = '';
            var removeclasses = '';

            //if we have no MSV just move on
            if(!msv) {
                removeclasses = this.cd.structuralclass + ' '
                    +  this.cd.meaningclass + ' '
                    + this.cd.visualclass + ' ';

                return {addclasses: addclasses,
                    removeclasses: removeclasses};
            }

            //if have msv see whats there.
            if(msv.s && msv.s==1){
                addclasses += this.cd.structuralclass + ' ' ;
            }else{
                removeclasses += this.cd.structuralclass + ' ' ;
            }
            if(msv.m && msv.m==1){
                addclasses += this.cd.meaningclass + ' ' ;
            }else{
                removeclasses += this.cd.meaningclass + ' ' ;
            }
            if(msv.v && msv.v==1){
                addclasses += this.cd.visualclass + ' ' ;
            }else{
                removeclasses += this.cd.visualclass + ' ' ;
            }
            return {addclasses: addclasses, removeclasses: removeclasses};
        },

        markupPlayChain: function(state,wordnumber,msv,that){
            //add to error
            var addclasses_word = '';
            var removeclasses_word = '';
            var addclasses_space = '';
            var removeclasses_space= '';

            //fetch css classes for msv
            var msvmarkup = that.getMSVClasses(msv);
            addclasses_word = msvmarkup.addclasses;
            removeclasses_word = msvmarkup.removeclasses;
            
            //fetch data-msvbadge
            var msvbadge = that.getMSVBadge(msv);

            //state
            switch(state){
                case def.stateerror:
                    addclasses_word += that.cd.badwordclass  + ' ' ;
                    removeclasses_word += that.cd.selfcorrectedwordclass + ' ' +  that.cd.maybeselfcorrectedwordclass + ' ';
                    break;
                case def.stateselfcorrect:
                    addclasses_word +=  that.cd.selfcorrectedwordclass  + ' ';
                    removeclasses_word +=  that.cd.badwordclass + ' ' +  that.cd.maybeselfcorrectedwordclass + ' ';
                    break;

                case def.statecorrect:
                    removeclasses_word +=  that.cd.badwordclass + ' ' +  that.cd.selfcorrectedwordclass + ' ' +  that.cd.maybeselfcorrectedwordclass + ' ';
            }

            var playchain= that.fetchPlayChain(wordnumber);
            for(var wordindex = playchain.startword;wordindex<=playchain.endword;wordindex++){
                if(wordnumber==wordindex) {
                    var theword = $('#' + that.cd.wordclass + '_' + wordindex);
                    var thespace = $('#' + that.cd.spaceclass + '_' + wordindex);
                    that.storewordstate(state, wordindex, theword.text(), msv);
                    //add classes
                    theword.addClass(addclasses_word);
                    if (wordindex != playchain.endword) {
                        thespace.addClass(addclasses_space);
                    }
                    //remove classes
                    theword.removeClass(removeclasses_word);
                    thespace.removeClass(removeclasses_space);

                    //msv badge
                    if (msvbadge) {
                        theword.attr('data-msvbadge', msvbadge);
                    } else {
                        theword.removeAttr('data-msvbadge');
                    }
                }

            }
        },

        register_controls: function(){

            this.controls.wordplayer = $('#' + this.cd.wordplayerclass);
            this.controls.audioplayer = $('#' + this.cd.audioplayerclass);
            this.controls.eachword = $('.' + this.cd.wordclass);
            this.controls.eachspace = $('.' + this.cd.spaceclass);
            this.controls.endwordmarker =  $('#' + this.cd.spaceclass + '_' + this.options.endwordnumber);
            this.controls.spotcheckword = $('.' + this.cd.spotcheckmode);

            this.controls.wpmscorebox = $('#' + this.cd.wpmscoreid);
            this.controls.accuracyscorebox = $('#' + this.cd.accuracyscoreid);
            this.controls.sessionscorebox = $('#' + this.cd.sessionscoreid);
            this.controls.errorscorebox = $('#' + this.cd.errorscoreid);
            this.controls.errorratebox = $('#' + this.cd.errorrateid);
            this.controls.scratebox = $('#' + this.cd.scrateid);

            this.controls.formelementwpmscore = $("#" + this.cd.formelementwpmscore);
            this.controls.formelementsessionscore = $("#" + this.cd.formelementsessionscore);
            this.controls.formelementaccuracy = $("#" + this.cd.formelementaccuracy);
            this.controls.formelementendword = $("#" + this.cd.formelementendword);
            this.controls.formelementerrors = $("#" + this.cd.formelementerrors);
            this.controls.formelementnotes = $("#" + this.cd.formelementnotes);
            this.controls.formelementselfcorrections = $("#" + this.cd.formelementselfcorrections);
            this.controls.formelementtime = $("#" + this.cd.formelementtime);

            this.controls.passagecontainer = $("." + this.cd.passagecontainer);
            this.controls.notes = $('#' +  this.cd.notesclass);

            //passage action buttons
            this.controls.modebutton =  $("#" + this.cd.modebutton);
            this.controls.gradingmodebutton =  $("#" + this.cd.gradingmodebutton);
            this.controls.spotcheckmodebutton =  $("#" + this.cd.spotcheckmodebutton);
            this.controls.msvmodebutton =  $("#" + this.cd.msvmodebutton);
            this.controls.transcriptmodebutton =  $("#" + this.cd.transcriptmodebutton);
            this.controls.clearbutton =  $("#" + this.cd.clearbutton);

        },

        register_events: function(){
            var that = this;
            //set up event handlers


            //Play audio from and to spot check part
            this.controls.passagecontainer.on('click',
                '.' + this.cd.badwordclass +
                ', .' + this.cd.maybeselfcorrectedwordclass +
                ', .' + this.cd.selfcorrectedwordclass +
                ', .' + this.cd.aiunmatched,function(){
                if(that.currentmode===def.modespotcheck || that.currentmode===def.modemsv) {
                    var wordnumber = parseInt($(this).attr('data-wordnumber'));
                    that.doPlaySpotCheck(wordnumber);
                }
            });


            //in review mode, do nuffink though ... thats for the student
            if(this.options.readonly){
                //do nothing

            //here we will put real options for playing the model reading and user reading etc
            }else if(false){
                /*
                if(this.enabletts && this.options.ttslanguage != 'none'){
                    this.controls.eachword.click(this.playword);
                }
                */

                //if we have AI data then turn on spotcheckmode
                if(this.options.sessionmatches) {
                    this.doSpotCheckMode();
                }

                //add listeners for click events
                this.controls.eachword.click(
                    function() {
                        //if we are in spotcheck mode just return, we do not grade
                        if (that.currentmode === def.modespotcheck) {
                            return;
                        }

                        //get the word that was clicked
                        var wordnumber = $(this).attr('data-wordnumber');
                        var theword = $(this).text();

                        if (that.currentmode === def.modetranscript) {
                            var chunk = that.fetchTranscriptChunk(wordnumber);
                            if(chunk){
                                popoverhelper.addTranscript(this,chunk);
                            }
                            return;
                        }
                    });

            //if not in review mode
            }else{

                //update hidden form control value each time we change notes
                this.controls.notes.change(function(){
                    that.controls.formelementnotes.val($(this).val());
                });

                //process word clicks
                this.controls.eachword.click(
                    function() {

                        //get the word that was clicked
                        var wordnumber = $(this).attr('data-wordnumber');
                        var theword = $(this).text();
                        var themsv = {};

                        switch(that.currentmode){
                            case def.modespotcheck:
                                if($(this).hasClass(that.cd.badwordclass) || $(this).hasClass(that.cd.aiunmatched)) {
                                    popoverhelper.addQuickGrader(this);
                                }
                                return;

                            case def.modetranscript:
                                var chunk = that.fetchTranscriptChunk(wordnumber);
                                if(chunk){
                                    popoverhelper.addTranscript(this,chunk);
                                }
                                return;

                            case def.modemsv:
                                // if($(this).hasClass(that.cd.badwordclass)||
                                // $(this).hasClass(that.cd.selfcorrectedwordclass) ||
                                // $(this).hasClass(that.cd.maybeselfcorrectedwordclass)  ||
                                // $(this).hasClass(that.cd.aiunmatched)) {
                                var msvdata={};
                                if(wordnumber in that.options.selfcorrections) {
                                    msvdata.state=def.stateselfcorrect;
                                    msvdata.msv=that.options.selfcorrections[wordnumber].msv;

                                 }else if(wordnumber in that.options.errorwords) {
                                    msvdata.state=def.stateerror;
                                    msvdata.msv=that.options.errorwords[wordnumber].msv;
                                }else{
                                    msvdata.state=def.statecorrect;
                                    msvdata.msv={s: 0, m: 0, v: 0};
                                }
                                //its possible we got here with no msv if error was not added correctly
                                if(!msvdata.msv || !Object.keys(msvdata.msv).length){msvdata.msv={s: 0, m: 0, v: 0};}

                                popoverhelper.addMSVGrader(this,msvdata);
                                // }
                                return;

                            case def.modegrading:
                            default:
                                //this will disallow badwords after the endmarker
                                if(that.options.enforcemarker && Number(wordnumber)>Number(that.options.endwordnumber)){
                                    return;
                                }

                                if(wordnumber in that.options.errorwords){
                                    that.storewordstate(def.statecorrect,wordnumber,theword,themsv);
                                    $(this).removeClass(that.cd.badwordclass + ' ' + that.cd.selfcorrectedwordclass);

                                }else{
                                    that.storewordstate(def.stateerror,wordnumber,theword,themsv);
                                    $(this).removeClass(that.cd.selfcorrectedwordclass);
                                    $(this).addClass(that.cd.badwordclass);
                                }

                                //we remove msv markup though its just tidy up. It will have been
                                //lost in the toggle in the data layer anyway
                                var msvclasses = that.getMSVClasses(themsv);
                                $(this).removeClass(msvclasses.removeclasses);

                                //finally update scores
                                that.processscores();
                        }
                    }
                ); //end of each word click

                //process space clicks
                this.controls.eachspace.click(
                    function() {

                        //if we are in spotcheck or transcript check mode just return, we do not grade
                        if(that.currentmode===def.modespotcheck ||
                            that.currentmode===def.modetranscript ||
                            that.currentmode===def.modemsv){
                            return;
                        }

                        //this event is entered by  click on space
                        //it relies on attr data-wordnumber being set correctly
                        var wordnumber = $(this).attr('data-wordnumber');
                        var thespace = $('#' + that.cd.spaceclass + '_' + wordnumber);

                        if(wordnumber === that.options.endwordnumber){
                            that.options.endwordnumber = that.options.totalwordcount;
                            thespace.removeClass(that.cd.endspaceclass);
                            $('#' + that.cd.spaceclass + '_' + that.options.totalwordcount).addClass(that.cd.endspaceclass);
                        }else{
                            $('#' + that.cd.spaceclass + '_' + that.options.endwordnumber).removeClass(that.cd.endspaceclass);
                            that.options.endwordnumber = wordnumber;
                            thespace.addClass(that.cd.endspaceclass);
                        }
                        that.processunread();
                        that.processscores();
                    }
                );//end of each space click17

                //process clearbutton's click event
                this.controls.clearbutton.click(function(){

                    //if we are in spotcheck or transcript check mode just return, we do not grade
                    if(that.currentmode===def.modespotcheck || that.currentmode===def.modetranscript || that.currentmode===def.modemsv){
                        return;
                    }

                    //clear all the error words
                    $('.' + that.cd.badwordclass).each(function(index){
                        var wordnumber = $(this).attr('data-wordnumber');
                        delete that.options.errorwords[wordnumber];
                        $(this).removeClass(that.cd.badwordclass);
                    });

                    //remove unread words
                    $('.' + that.cd.wordclass).removeClass(that.cd.unreadwordclass);

                    //set endspace to last space
                    that.options.endwordnumber = that.options.totalwordcount;
                    $('.' + that.cd.spaceclass).removeClass(that.cd.endspaceclass);
                    $('#' + that.cd.spaceclass + '_' + that.options.totalwordcount).addClass(that.cd.endspaceclass);

                    //reprocess scores
                    that.processscores();
                });


                //modebutton: turn on grading
                this.controls.gradingmodebutton.click(function(){
                    that.undoCurrentMode();
                    that.doGradingMode();
                    that.updateButtonStates();
                });

            }//end of if/else reviewmode

            //either in or out of review mode we want these
            //modebutton: turn on spotchecking
            this.controls.spotcheckmodebutton.click(function(){
                that.undoCurrentMode();
                that.doSpotCheckMode();
                that.updateButtonStates();
            });

            //either in or out of review mode we want these
            //modebutton: turn on MSV mode
            this.controls.msvmodebutton.click(function(){
                that.undoCurrentMode();
                that.doMSVMode();
                that.updateButtonStates();
            });

            //modebutton: turn on transcript checking
            this.controls.transcriptmodebutton.click(function(){
                that.undoCurrentMode();
                that.doTranscriptMode();
                that.updateButtonStates();
            });
        },

        undoCurrentMode: function(){
            switch(this.currentmode){
                case def.modegrading:
                    this.undoGradingMode();
                    break;
                case def.modespotcheck:
                    this.undoSpotCheckMode();
                    break;
                case def.modemsv:
                    this.undoMSVMode();
                    break;
                case def.modetranscript:
                    this.undoTranscriptMode();
                    break;
            }
        },

        updateButtonStates: function(){
            var printAttemptBtn = $("a#printattempt");
            switch(this.currentmode){
                case def.modegrading:
                    this.controls.gradingmodebutton.prop('disabled', true);
                    this.controls.spotcheckmodebutton.prop('disabled', false);
                    this.controls.msvmodebutton.prop('disabled', false);
                    this.controls.transcriptmodebutton.prop('disabled', false);
                    printAttemptBtn.attr('href', printAttemptBtn.attr('data-base-url') + "&mode=manual");
                    break;
                case def.modespotcheck:
                    this.controls.gradingmodebutton.prop('disabled', false);
                    this.controls.spotcheckmodebutton.prop('disabled', true);
                    this.controls.msvmodebutton.prop('disabled', false);
                    this.controls.transcriptmodebutton.prop('disabled', false);
                    printAttemptBtn.attr('href', printAttemptBtn.attr('data-base-url') + "&mode=quick");
                    break;
                case def.modemsv:
                    this.controls.gradingmodebutton.prop('disabled', false);
                    this.controls.spotcheckmodebutton.prop('disabled', false);
                    this.controls.msvmodebutton.prop('disabled', true);
                    this.controls.transcriptmodebutton.prop('disabled', false);
                    printAttemptBtn.attr('href', printAttemptBtn.attr('data-base-url') + "&mode=power");
                    break;
                case def.modetranscript:
                    this.controls.gradingmodebutton.prop('disabled', false);
                    this.controls.spotcheckmodebutton.prop('disabled', false);
                    this.controls.msvmodebutton.prop('disabled', false);
                    this.controls.transcriptmodebutton.prop('disabled', true);
                    printAttemptBtn.attr('href', printAttemptBtn.attr('data-base-url') + "&mode=transcript");
                    break;
            }

        },

        /*
        * Here we fetch the playchain, start playing frm audiostart and add an event handler to stop at audioend
         */
        doPlaySpotCheck: function(spotcheckindex){
          var playchain = this.fetchPlayChain(spotcheckindex);
          var theplayer = this.controls.audioplayer[0];
          //we pad the play audio by 0.5 seconds beginning and end
            var pad = 0.5;
            var duration = theplayer.duration;
            //determine starttime
            var endtime = parseFloat(playchain.audioend);
            if(!isNaN(duration) && duration > (endtime + pad)){
                endtime = endtime + pad;
            }
            //determine endtime
            var starttime = parseFloat(playchain.audiostart);
            if((starttime -pad) > 0){
                starttime = starttime -pad;
            }

          theplayer.currentTime=starttime;
          $(this.controls.audioplayer).off("timeupdate");
          $(this.controls.audioplayer).on("timeupdate",function(e){
              var currenttime = theplayer.currentTime;
              if(currenttime >= endtime){
                  $(this).off("timeupdate");
                  theplayer.pause();
              }
          });
            theplayer.play();
        },

        /*
        * The playchain is all the words in a string of badwords.
        * The complexity comes because a bad word  is usually one that isunmatched by AI.
        * So if the teacher clicks on a passage word that did not appear in the transcript, what should we play?
        * Answer: All the words from the last known to the next known word. Hence we create a play chain
        * For consistency, if the teacher flags matched words as bad, while we do know their precise location we still
        * make a play chain. Its not a common situation probably.
         */
        fetchPlayChain: function(spotcheckindex){

            //find startword
          var startindex=spotcheckindex;
          var starttime = -1;
          for(var wordnumber=spotcheckindex;wordnumber>0;wordnumber--){
             var isbad = $('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.badwordclass);
             var isunmatched =$('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.aiunmatched);
             //if current wordnumber part of the playchain, set it as the startindex.
              // And get the audiotime if its a matched word. (we only know audiotime of matched words)
             if(isbad || isunmatched){
                 startindex = wordnumber;
                 if(!isunmatched){
                     starttime=this.options.sessionmatches['' + wordnumber].audiostart;
                 }else{
                     starttime=-1;
                 }
             }else{
                 break;
             }
          }//end of for loop --
          //if we have no starttime then we need to get the next matched word's audioend and use that
          if(starttime==-1){
              starttime = 0;
              for(var wordnumber=startindex-1;wordnumber>0;wordnumber--){
                  if(this.options.sessionmatches['' + wordnumber]){
                      starttime=this.options.sessionmatches['' + wordnumber].audioend;
                      break;
                  }
              }
          }

            //find endword
            var endindex=spotcheckindex;
            var endtime = -1;
            var passageendword = this.options.totalwordcount;
            for(var wordnumber=spotcheckindex;wordnumber<=passageendword;wordnumber++){
                var isbad = $('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.badwordclass);
                var isunmatched =$('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.aiunmatched);
                //if its part of the playchain, set it to startindex. And get time if its a matched word.
                if(isbad || isunmatched){
                    endindex = wordnumber;
                    if(!isunmatched){
                        endtime=this.options.sessionmatches['' + wordnumber].audioend;
                    }else{
                        endtime=-1;
                    }
                }else{
                    break;
                }
            }//end of for loop --
            //if we have no endtime then we need to get the next matched word's audiostart and use that
            if(endtime==-1){
                endtime = this.options.totalseconds;
                for(var wordnumber=endindex+1;wordnumber<=passageendword;wordnumber++){
                    if(this.options.sessionmatches['' + wordnumber]){
                        endtime=this.options.sessionmatches['' + wordnumber].audiostart;
                        break;
                    }
                }
            }
            var playchain = {};
            playchain.startword=startindex;
            playchain.endword=parseInt(endindex);
            playchain.audiostart=starttime;
            playchain.audioend=parseInt(endtime);
            //console.log('audiostart:' + starttime);
            //console.log('audioend:' + endtime);

            return playchain;

        },

        //mark up all ai unmatched words as aiunmatched
        markup_aiunmatchedwords: function(){
            var that =this;
            if(this.options.sessionmatches){
                var prevmatch=0;
                $.each(this.options.sessionmatches,function(index,match){
                    var unmatchedcount = index - prevmatch - 1;
                    if(unmatchedcount>0){
                        for(var errorword =1;errorword<unmatchedcount+1; errorword++){
                            var wordnumber = prevmatch + errorword;
                            $('#' + that.cd.wordclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                        }
                    }
                    prevmatch = parseInt(index);
                });

                //mark all words from last matched word to the end as aiunmatched
                for(var errorword =prevmatch+1;errorword<=this.options.endwordnumber; errorword++){
                    var wordnumber = errorword;
                    $('#' + that.cd.wordclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                }
            }

        },


        markup_aiunmatchedspaces: function(){
            var that =this;
            $('.' + this.cd.wordclass + '.' + this.cd.aiunmatched).each(function(index){
                    var wordnumber = parseInt($(this).attr('data-wordnumber'));
                    //build chains (highlight spaces) of badwords or aiunmatched
                    if (that.currentmode===def.modespotcheck && $('#' + that.cd.wordclass + '_' + (wordnumber + 1)).hasClass(that.cd.badwordclass) ||
                        $('#' + that.cd.wordclass + '_' + (wordnumber + 1)).hasClass(that.cd.aiunmatched)) {
                        $('#' + that.cd.spaceclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                    }
            });
        },


        /*
        * Here we mark up the passage for spotcheck mode. This will make up the spaces and the words as either badwords
        * or aiunmatched words. We need to create playchains so aiunmatched still is indicated visibly even where its
        * not a badword (ie has been corrected)
         */
        doSpotCheckMode: function(){
            var that = this;

            //mark up all ai unmatched words as aiunmatched
            this.markup_aiunmatchedwords();

            //mark up spaces between aiunmatched word and spotcheck/aiunmatched word (aiunmatched spaces)
            this.markup_aiunmatchedspaces();

            //mark up passage as spotcheck
            this.controls.passagecontainer.addClass(this.cd.spotcheckmode);
            this.currentmode="spotcheck";
        },

        undoSpotCheckMode: function(){
            this.controls.passagecontainer.removeClass(this.cd.spotcheckmode);
            $('.' + this.cd.wordclass).removeClass(this.cd.aiunmatched);
            $('.' + this.cd.spaceclass).removeClass(this.cd.aiunmatched);
            $(this.controls.audioplayer).off("timeupdate");
            popoverhelper.remove();
        },

        /*
     * Here we mark up the passage for msv mode. This will make up the spaces and the words as either badwords
     * or aiunmatched words. We need to create playchains so aiunmatched still is indeicated visibly even where its
     * not a badword (ie has been corrected)
      */
        doMSVMode: function(){
            var that = this;

            //mark up all ai unmatched words as aiunmatched
            this.markup_aiunmatchedwords();

            //mark up spaces between aiunmatched word and spotcheck/aiunmatched word (aiunmatched spaces)
            this.markup_aiunmatchedspaces();

            //mark up passage as msv
            this.controls.passagecontainer.addClass(this.cd.msvmode);
            this.currentmode=def.modemsv;
        },

        undoMSVMode: function(){
            this.controls.passagecontainer.removeClass(this.cd.msvmode);
            $('.' + this.cd.wordclass).removeClass(this.cd.aiunmatched);
            $('.' + this.cd.spaceclass).removeClass(this.cd.aiunmatched);
            $(this.controls.audioplayer).off("timeupdate");
            popoverhelper.remove();
        },

        /*
       * Here we mark up the passage for transcript mode.
        */
        doTranscriptMode: function(){
            var that = this;
            //mark up all ai unmatched words as transcript
            if(this.options.sessionmatches){
                var prevmatch=0;
                $.each(this.options.sessionmatches,function(index,match){
                    var unmatchedcount = index - prevmatch - 1;
                    if(unmatchedcount>0){
                        for(var errorword =1;errorword<unmatchedcount+1; errorword++){
                            var wordnumber = prevmatch + errorword;
                            $('#' + that.cd.wordclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                        }
                    }
                    prevmatch = parseInt(index);
                });

                //mark all words from last matched word to the end as aiunmatched
                for(var errorword =prevmatch+1;errorword<=this.options.endwordnumber; errorword++){
                    var wordnumber = errorword;
                    $('#' + that.cd.wordclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                }
            }

            //mark up spaces between aiunmatched word and aiunmatched (bad spaces)
            $('.' + this.cd.aiunmatched).each(function(index){
                var wordnumber = parseInt($(this).attr('data-wordnumber'));
                //build chains (highlight spaces) of badwords or aiunmatched
                if($('#' + that.cd.wordclass + '_' + (wordnumber + 1)).hasClass(that.cd.aiunmatched)){
                    $('#' + that.cd.spaceclass + '_' + wordnumber).addClass(that.cd.aiunmatched);
                };
            });

            this.controls.passagecontainer.addClass(this.cd.transcriptmode);
            this.currentmode=def.modetranscript;
        },

        undoTranscriptMode: function(){
            this.controls.passagecontainer.removeClass(this.cd.transcriptmode);
            $('.' + this.cd.wordclass).removeClass(this.cd.aiunmatched);
            $('.' + this.cd.spaceclass).removeClass(this.cd.aiunmatched);
            popoverhelper.remove();
        },

        doGradingMode: function(){
            this.controls.passagecontainer.addClass(this.cd.gradingmode);
            this.currentmode=def.modegrading;
        },

        undoGradingMode: function(){
            this.controls.passagecontainer.removeClass(this.cd.gradingmode);
        },

        /*
       * This will take a wordindex and find the previous and next transcript indexes that were matched and
       * return all the transcript words in between those.
        */
        fetchTranscriptChunk: function(checkindex){

            var transcriptlength= this.options.transcriptwords.length;
            if(transcriptlength==0){return "";}

            //find startindex
            var startindex=-1;
            for(var wordnumber=checkindex;wordnumber>0;wordnumber--){

                var isunmatched =$('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.aiunmatched);
                //if we matched then the subsequent transcript word is the last unmatched one in the checkindex sequence
                if(!isunmatched){
                    startindex=this.options.sessionmatches['' + wordnumber].tposition+1;
                    break;
                }
            }//end of for loop

            //find endindex
            var endindex=-1;
            for(var wordnumber=checkindex;wordnumber<=transcriptlength;wordnumber++){

                var isunmatched =$('#' + this.cd.wordclass + '_' + wordnumber).hasClass(this.cd.aiunmatched);
                //if we matched then the previous transcript word is the last unmatched one in the checkindex sequence
                if(!isunmatched){
                    endindex=this.options.sessionmatches['' + wordnumber].tposition-1;
                    break;
                }
            }//end of for loop --

            //if there was no previous matched word, we set start to 1
            if(startindex==-1){startindex=1;}
            //if there was no subsequent matched word we flag the end as the -1
            if(endindex==transcriptlength){
                    endindex=-1;
            //an edge case is where the first word is not in transcript and first match is the second or later passage
            //word. It might not be possible for endindex to be lower than start index, but we don't want it anyway
            }else if(endindex==0 || endindex < startindex){
                return false;
            }

            //up until this point the indexes have started from 1, since the passage word numbers start from 1
            //but the transcript array is 0 based so we adjust. array splice function does not include item and endindex
            ///so it needs to be one more then start index. hence we do not adjust that
            startindex--;

            //finally we return the section
            var  ret=false;
            if(endindex>0) {
              ret = this.options.transcriptwords.slice(startindex, endindex).join(" ");
            }else{
              ret = this.options.transcriptwords.slice(startindex).join(" ");
            }
            if(ret.trim()==''){
                return false;
            }else{
                return ret;
            }
        },


        playword: function(){
            var m = this;
            m.controls.wordplayer.attr('src',M.cfg.wwwroot + '/' + def.componentpath  + '/tts.php?txt=' + encodeURIComponent($(this).text())
                + '&lang=' + m.options.ttslanguage + '&n=' + m.options.activityid);
            m.controls.wordplayer[0].pause();
            m.controls.wordplayer[0].load();
            m.controls.wordplayer[0].play();
        },

        redrawgradestate: function(){
            var m = this;
            this.processunread();

            if(this.options.reviewmode!==this.constants.REVIEWMODE_SCORESONLY) {
                //mark up errors
                $.each(m.options.errorwords, function (index) {
                        var theword = $('#' + m.cd.wordclass + '_' + m.options.errorwords[index].wordnumber);
                        theword.addClass(m.cd.badwordclass);
                        var msvmarkup = m.getMSVClasses(m.options.errorwords[index].msv);
                        theword.addClass(msvmarkup.addclasses);

                        var msvbadge =  m.getMSVBadge(m.options.errorwords[index].msv);
                        if(msvbadge){
                            theword.attr('data-msvbadge',msvbadge);
                        }
                    }
                );

                //mark up self corrects
                $.each(m.options.selfcorrections, function (index) {
                        var msvmarkup = m.getMSVClasses(m.options.selfcorrections[index].msv);
                        var msvbadge =  m.getMSVBadge(m.options.selfcorrections[index].msv);
                        var theword =  $('#' + m.cd.wordclass + '_' + m.options.selfcorrections[index].wordnumber);
                        theword.addClass(m.cd.selfcorrectedwordclass);
                        theword.addClass(msvmarkup.addclasses);
                        if(msvbadge){
                            theword.attr('data-msvbadge',msvbadge);
                        }
                    }
                );

                //mark up maybe self corrects
                //self corrections are now auto-detected by aigrade in PHP (ie no maybe).
                //if its too inaccurate we might go back to maybe(same algorythm)
                //this.markup_maybeselfcorrects();

                //mark up notes
                this.controls.notes.val(this.controls.formelementnotes.val());

                //mode distinct mark up. its unlikely you will arrive here in a non "grading" mode
                switch(this.currentmode){
                    case def.modegrading:
                        //this is really the absence of modes. They all build on this one.
                        //so we do not do anything
                        break;

                    case def.modespotcheck:
                        this.doSpotCheckMode();
                        break;

                    case def.modetranscript:
                        this.doTranscriptMode();
                        break;

                    case def.modemsv:
                        this.doMSVMode();
                        break;


                }
            }



        },

        storewordstate: function(wordstate, wordnumber,word, msv) {
            switch(wordstate){
                case def.stateerror:
                    this.options.errorwords[wordnumber] = {word: word, wordnumber: wordnumber, msv: msv};
                    if(wordnumber in this.options.selfcorrections) {
                        delete this.options.selfcorrections[wordnumber];
                    }
                    break;

                case def.stateselfcorrect:
                    this.options.selfcorrections[wordnumber] = {word: word, wordnumber: wordnumber, msv: msv};
                    if(wordnumber in this.options.errorwords) {
                        delete this.options.errorwords[wordnumber];
                    }
                    break;

                case def.statecorrect:
                    if(wordnumber in this.options.errorwords) {
                        delete this.options.errorwords[wordnumber];
                    }
                    if(wordnumber in this.options.selfcorrections) {
                        delete this.options.selfcorrections[wordnumber];
                    }
            }

            //console.log(this.errorwords);
            return;
        },
        processword: function() {
            var m = this;
            var wordnumber = $(this).attr('data-wordnumber');
            var theword = $(this).text();
            var themsv = {};
            //this will disallow badwords after the endmarker
            if(m.options.enforcemarker && Number(wordnumber)>Number(m.options.endwordnumber)){
                return;
            }

            if(wordnumber in m.options.errorwords){
                delete m.options.errorwords[wordnumber];
                $(this).removeClass(m.cd.badwordclass);
            }else{
                m.storewordstate(def.stateerror,wordnumber,theword,themsv);
                $(this).addClass(m.cd.badwordclass);
            }
            m.processscores();
        },
        //this function is never called it seems ....
        processspace: function() {
            //this event is entered by  click on space
            //it relies on attr data-wordnumber being set correctly
            var m = this;
            var wordnumber = $(this).attr('data-wordnumber');
            var thespace = $('#' + m.cd.spaceclass + '_' + wordnumber);

            if(wordnumber == m.options.endwordnumber){
                m.options.endwordnumber = m.options.totalwordcount;
                thespace.removeClass(m.cd.endspaceclass);
                $('#' + m.cd.spaceclass + '_' + m.options.totalwordcount).addClass(m.cd.endspaceclass);
            }else{
                $('#' + m.cd.spaceclass + '_' + m.options.endwordnumber).removeClass(m.cd.endspaceclass);
                m.options.endwordnumber = wordnumber;
                thespace.addClass(m.cd.endspaceclass);
            }
            m.processunread();
            m.processscores();
        },

        markup_maybeselfcorrects: function(){
            var that =this;
            if(this.options.sessionmatches){
                var prevmatch=false;
                //loop through matches checking for insertions prior to matches
                $.each(this.options.sessionmatches,function(index,match){
                    var maybe=false; // insertions exist between this match and prev match
                    var verymaybe=false; //this word is matched and prev word is matched, but insertions exist

                    if(prevmatch){
                        //there are insertions in transcript between matches
                        if(match.tposition - prevmatch.tposition > 1){
                            maybe=true;
                            //passage match positions are adjacent, and prev match was not an alternative match
                            if(match.pposition - prevmatch.pposition == 1){
                                if(prevmatch.hasOwnProperty('altmatch') && prevmatch.altmatch===1) {
                                    verymaybe = false;
                                }else{
                                    verymaybe = true;
                                }
                            }
                        }
                    }else if(prevmatch ===false){
                        //this is the first passage match, but there have been insertions already in the transcript
                        if(match.pposition<match.tposition){
                            maybe=true;
                            //this is also the first passage word
                            if(match.pposition==1){
                                verymaybe=true;
                            }
                        }
                    }
                    //for now we will just work with very maybes, but we could do maybes
                    if(verymaybe){
                        $('#' + that.cd.wordclass + '_' + match.pposition).addClass(that.cd.maybeselfcorrectedwordclass);
                    }
                    prevmatch =match;
                });
            }
        },

        processunread: function(){
            var m = this;
            m.controls.eachword.each(function(index){
                var wordnumber = $(this).attr('data-wordnumber');
                var thespace = $('#' + m.cd.spaceclass + '_' + wordnumber);

                if(Number(wordnumber)>Number(m.options.endwordnumber)){
                    $(this).addClass(m.cd.unreadwordclass);
                    thespace.addClass(m.cd.unreadspaceclass);

                    //this will clear badwords after the endmarker
                    if(m.options.enforcemarker && wordnumber in m.options.errorwords){
                        delete m.options.errorwords[wordnumber];
                        $(this).removeClass(m.cd.badwordclass);
                    }
                }else{
                    $(this).removeClass(m.cd.unreadwordclass);
                    thespace.removeClass(m.cd.unreadspaceclass);
                }
            });
        },
        processscores: function(){
            var m = this;
            var errorscore = Object.keys(m.options.errorwords).length;
            m.controls.errorscorebox.text(errorscore);

            var selfcorrectionsscore = Object.keys(m.options.selfcorrections).length;
            //m.controls.errorscorebox.text(errorscore);

            //wpm score
            //we do not apply accuracy adjustment here, that is only for machine grades.
            var wpmscore=0;
            if(m.options.totalseconds > 0){
                wpmscore = Math.round((m.options.endwordnumber - errorscore) * 60 / m.options.totalseconds);
            }
            m.options.wpm = wpmscore;
            m.controls.wpmscorebox.text(wpmscore);

            //accuracy score
            var accuracyscore=0;
            if(m.options.endwordnumber>0) {
                accuracyscore = Math.round((m.options.endwordnumber - errorscore) / m.options.endwordnumber * 100);
            }
            m.options.accuracy = accuracyscore;
            m.controls.accuracyscorebox.text(accuracyscore);

            //sessionscore
            var usewpmscore = wpmscore;
            if(usewpmscore > m.options.targetwpm){
                usewpmscore = m.options.targetwpm;
            }
            var sessionscore = Math.round(usewpmscore/m.options.targetwpm * 100);
            m.controls.sessionscorebox.text(sessionscore);

            //error rate. See util::calc_error_rate
            var errorratescore='';
            if(errorscore > 0 && m.options.endwordnumber > 0) {
                errorratescore = "1:" + Math.round(m.options.endwordnumber / errorscore);
            }else if(m.options.endwordnumber > 0){
                errorratescore  = "-:" + m.options.endwordnumber;
            }else{
                errorratescore  = "-:-";
            }
            m.options.errorrate = errorratescore;
            m.controls.errorratebox.text(errorratescore);

            //self correction rate. See utils::calc_sc_rate
            var scratescore='';
            if(errorscore > 0 && selfcorrectionsscore > 0) {
                scratescore = "1:" + Math.round((errorscore + selfcorrectionsscore) / selfcorrectionsscore);
            }else if(errorscore > 0){
                scratescore  = "-:" + errorscore;
            }else{
                scratescore  = "-:-";
            }
            m.options.scrate = scratescore;
            m.controls.scratebox.text(scratescore);

            //update form field
            m.controls.formelementwpmscore.val(wpmscore);
            m.controls.formelementsessionscore.val(sessionscore);
            m.controls.formelementaccuracy.val(accuracyscore);
            m.controls.formelementendword.val(m.options.endwordnumber);
            m.controls.formelementerrors.val(JSON.stringify(m.options.errorwords));
            m.controls.formelementselfcorrections.val(JSON.stringify(m.options.selfcorrections));

        }

    };
});