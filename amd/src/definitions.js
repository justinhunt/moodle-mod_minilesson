define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */

    log.debug('Poodll Time definitions: initialising');

    return{
        component: 'mod_poodlltime',
        componentpath: 'mod/poodlltime',
        quizcontainer: 'mod_poodlltime_quiz_cont',
        //player code
        hiddenplayer: 'mod_poodlltime_hidden_player',
        hiddenplayerbutton: 'mod_poodlltime_hidden_player_button',
        hiddenplayerbuttonactive: 'mod_poodlltime_hidden_player_button_active',
        hiddenplayerbuttonpaused: 'mod_poodlltime_hidden_player_button_paused',
        hiddenplayerbuttonplaying: 'mod_poodlltime_hidden_player_button_playing',
        qr_player: 'mod_poodlltime_qr_player',

        //popover
        okbuttonclass: 'mod_poodlltime_quickgrade_ok',
        ngbuttonclass: 'mod_poodlltime_quickgrade_ng',
        quickgradecontainerclass: 'mod_poodlltime_quickgrade_cont',


        //rsquestions
        noitemscontainer: 'mod_poodlltime_noitems_cont',
        itemscontainer: 'mod_poodlltime_items_cont',
        itemstable: 'mod_poodlltime_qpanel',
        itemrow: 'mod_poodlltime_item_row',
        movearrow: 'mod_poodlltime_item_move',

        //grade now
        passagecontainer: 'mod_poodlltime_grading_passagecont',
        audioplayerclass: 'mod_poodlltime_grading_player',
        wordplayerclass: 'mod_poodlltime_hidden_player',
        wordclass: 'mod_poodlltime_grading_passageword',
        spaceclass: 'mod_poodlltime_grading_passagespace',
        badwordclass: 'mod_poodlltime_grading_badword',
        endspaceclass: 'mod_poodlltime_grading_endspace',
        unreadwordclass:  'mod_poodlltime_grading_unreadword',
        unreadspaceclass: 'mod_poodlltime_grading_unreadspace',
        modebutton: 'mod_poodlltime_modebutton',

        //activity

        gradingmodebutton: 'mod_poodlltime_gradingbutton',
        clearbutton: 'mod_poodlltime_clearbutton',


        //quiz
        qtype_pictureprompt: 'multichoicepicture',
        qtype_audioprompt: 'multichoiceaudio',
        qtype_textpromptlong: 'multichoicelong',
        qtype_textpromptshort: 'multichoice',
        qtype_textpromptaudio: 'audioresponse',

        //question types
        qtype_page: 'page',
        qtype_multichoice: 'multichoice',
        qtype_dictationchat: 'dictationchat',
        qtype_dictation: 'dictation',
        qtype_speechcards: 'speechcards',
        qtype_listenrepeat: 'listenrepeat',

        //running records features
        maybeselfcorrectedwordclass: 'mod_poodlltime_grading_maybeselfcorrectedword',
        selfcorrectedwordclass: 'mod_poodlltime_grading_selfcorrectedword',
        structuralclass: 'mod_poodlltime_grading_structural',
        meaningclass: 'mod_poodlltime_grading_meaning',
        visualclass: 'mod_poodlltime_grading_visual',
        notesclass: 'mod_poodlltime_notes',

        //modes
        modegrading: 'grading',
        modespotcheck: 'spotcheck',
        modetranscript: 'transcript',
        modemsv: 'msv',

        //MSV stuff
        msvclosebuttonclass: 'mod_poodlltime_msvgrade_close',
        s_buttonclass: 'mod_poodlltime_msv_s',
        m_buttonclass: 'mod_poodlltime_msv_m',
        v_buttonclass: 'mod_poodlltime_msv_v',
        correctbuttonclass: 'mod_poodlltime_msv_correct',
        errorbuttonclass: 'mod_poodlltime_msv_error',
        selfcorrectbuttonclass: 'mod_poodlltime_msv_selfcorrect',
        msvcontainer: 'mod_poodlltime_msv_cont',
        msvmodebutton: 'mod_poodlltime_msvbutton',
        msvgradebutton: 'mod_poodlltime_msvgrade_msv',
        msvmode: 'mod_poodlltime_msvmode',
        stateerror: 'stateerror',
        statecorrect: 'statecorrect',
        stateselfcorrect: 'stateselfcorrect',
        formelementnotes: 'mod_poodlltime_grading_form_notes',
        formelementselfcorrections: 'mod_poodlltime_grading_form_selfcorrections',
        gradingmode: 'mod_poodlltime_gradingmode',
        transcriptmode: 'mod_poodlltime_transcriptmode',
        msvcontainerclass: 'mod_poodlltime_msvcontainer',
        msvbuttonsbox: 'mod_poodlltime_msvbuttonsbox',


    };//end of return value
});