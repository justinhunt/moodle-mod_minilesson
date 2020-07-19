<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_poodlltime;

defined('MOODLE_INTERNAL') || die();

class constants
{
//component name, db tables, things that define app
const M_COMPONENT='mod_poodlltime';
const M_FILEAREA_SUBMISSIONS='submission';
const M_TABLE='poodlltime';
const M_USERTABLE='poodlltime_attempt';
const M_AITABLE='poodlltime_ai_result';
const M_QTABLE='poodlltime_rsquestions';
const M_MODNAME='poodlltime';
const M_URL='/mod/poodlltime';
const M_CLASS='mod_poodlltime';
const M_PLUGINSETTINGS ='/admin/settings.php?section=modsettingpoodlltime';

//grading options
const M_GRADEHIGHEST= 0;
const M_GRADELOWEST= 1;
const M_GRADELATEST= 2;
const M_GRADEAVERAGE= 3;
const M_GRADENONE= 4;
//accuracy adjustment method options
const ACCMETHOD_NONE =0;
const ACCMETHOD_AUTO =1;
const ACCMETHOD_FIXED =2;
const ACCMETHOD_NOERRORS =3;
//what to display to user when reviewing activity options
const POSTATTEMPT_NONE=0;
const POSTATTEMPT_EVAL=1;
const POSTATTEMPT_EVALERRORS=2;
//more review mode options
const REVIEWMODE_NONE=0;
const REVIEWMODE_MACHINE=1;
const REVIEWMODE_HUMAN=2;
const REVIEWMODE_SCORESONLY=3;
//to use or not use machine grades
const MACHINEGRADE_NONE=0;
const MACHINEGRADE_MACHINE=1;

//Constants for RS Questions
const NONE=0;
const TYPE_TEXTPROMPT_LONG = 'multichoicelong';
const TYPE_MULTICHOICE = 'multichoice';
const TYPE_TEXTPROMPT_AUDIO = 'audioresponse';

const TYPE_PAGE = 'page';
const TYPE_DICTATIONCHAT = 'dictationchat';
const TYPE_DICTATION = 'dictation';
const TYPE_SPEECHCARDS = 'speechcards';
const TYPE_LISTENREPEAT = 'listenrepeat';

const AUDIOFNAME = 'itemaudiofname';
const AUDIOPROMPT = 'audioitem';
const AUDIOANSWER = 'audioanswer';
const AUDIOMODEL = 'audiomodel';
const CORRECTANSWER = 'correctanswer';
const AUDIOPROMPT_FILEAREA = 'audioitem';
const TEXTPROMPT_FILEAREA = 'textitem';
const TEXTQUESTION = 'itemtext';
const TEXTQUESTION_FORMAT = 'itemtextformat';
const TEXTANSWER = 'customtext';
const CUSTOMDATA = 'customdata';
const CUSTOMINT = 'customint';
const POLLYVOICE = 'customtext5';
const TEXTQUESTION_FILEAREA = 'itemarea';
const TEXTANSWER_FILEAREA ='customtextfilearea';
const PASSAGEPICTURE='passagepicture';
const PASSAGEPICTURE_FILEAREA = 'passagepicture';
const MAXANSWERS=4;
const MAXCUSTOMTEXT=5;
const MAXCUSTOMDATA=5;
const MAXCUSTOMINT=5;

const SHOWTEXTPROMPT = 'customint1';
const TEXTPROMPT_WORDS = 0;
const TEXTPROMPT_DOTS = 1;


//CSS ids/classes
const M_RECORD_BUTTON='mod_poodlltime_record_button';
const M_START_BUTTON='mod_poodlltime_start_button';
const M_READING_AUDIO_URL='mod_poodlltime_readingaudiourl';
const M_DRAFT_CONTROL='mod_poodlltime_draft_control';
const M_PROGRESS_CONTAINER='mod_poodlltime_progress_cont';
const M_HIDER='mod_poodlltime_hider';
const M_STOP_BUTTON='mod_poodlltime_stop_button';
const M_WHERETONEXT_CONTAINER='mod_poodlltime_wheretonext_cont';
const M_RECORD_BUTTON_CONTAINER='mod_poodlltime_record_button_cont';
const M_START_BUTTON_CONTAINER='mod_poodlltime_start_button_cont';
const M_STOP_BUTTON_CONTAINER='mod_poodlltime_stop_button_cont';
const M_RECORDERID='therecorderid';
const M_RECORDING_CONTAINER='mod_poodlltime_recording_cont';
const M_RECORDER_CONTAINER='mod_poodlltime_recorder_cont';
const M_DUMMY_RECORDER='mod_poodlltime_dummy_recorder';
const M_RECORDER_INSTRUCTIONS_RIGHT='mod_poodlltime_recorder_instr_right';
const M_RECORDER_INSTRUCTIONS_LEFT='mod_poodlltime_recorder_instr_left';
const M_INSTRUCTIONS_CONTAINER='mod_poodlltime_instructions_cont';
const M_PASSAGE_CONTAINER='mod_poodlltime_passage_cont';
const M_MSV_MODE = 'mod_poodlltime_msvmode';
const M_QUICK_MODE = 'mod_poodlltime_spotcheckmode';
const M_GRADING_MODE = 'mod_poodlltime_gradingmode';
const M_QUIZ_CONTAINER='mod_poodlltime_quiz_cont';
const M_POSTATTEMPT= 'mod_poodlltime_postattempt';
const M_FEEDBACK_CONTAINER='mod_poodlltime_feedback_cont';
const M_ERROR_CONTAINER='mod_poodlltime_error_cont';
const M_GRADING_ERROR_CONTAINER='mod_poodlltime_grading_error_cont';
const M_GRADING_ERROR_IMG='mod_poodlltime_grading_error_img';
const M_GRADING_ERROR_SCORE='mod_poodlltime_grading_error_score';
const M_GRADING_WPM_CONTAINER='mod_poodlltime_grading_wpm_cont';
const M_GRADING_QUIZ_CONTAINER='mod_poodlltime_grading_quiz_cont';
const M_TWOCOL_CONTAINER='mod_poodlltime_twocol_cont';
const M_TWOCOL_WPM_CONTAINER='mod_poodlltime_twocol_wpm_cont';
const M_TWOCOL_QUIZ_CONTAINER='mod_poodlltime_twocol_quiz_cont';
const M_TWOCOL_PLAYER_CONTAINER='mod_poodlltime_twocol_player_cont';
const M_TWOCOL_PLAYER='mod_poodlltime_twocol_player';
const M_TWOCOL_LEFTCOL='mod_poodlltime_leftcol';
const M_TWOCOL_RIGHTCOL='mod_poodlltime_rightcol';
const M_GRADING_WPM_IMG='mod_poodlltime_grading_wpm_img';
const M_GRADING_WPM_SCORE='mod_poodlltime_grading_wpm_score';
const M_GRADING_QUIZ_SCORE='mod_poodlltime_grading_quiz_score';
const M_GRADING_ACCURACY_CONTAINER='mod_poodlltime_grading_accuracy_cont';
const M_GRADING_ACCURACY_IMG='mod_poodlltime_grading_accuracy_img';
const M_GRADING_ACCURACY_SCORE='mod_poodlltime_grading_accuracy_score';
const M_GRADING_SESSION_SCORE='mod_poodlltime_grading_session_score';
const M_GRADING_SESSIONSCORE_CONTAINER='mod_poodlltime_grading_sessionscore_cont';
const M_GRADING_ERRORRATE_SCORE='mod_poodlltime_grading_errorrate_score';
const M_GRADING_ERRORRATE_CONTAINER='mod_poodlltime_grading_errorrate_cont';
const M_GRADING_SCRATE_SCORE='mod_poodlltime_grading_scrate_score';
const M_GRADING_SCRATE_CONTAINER='mod_poodlltime_grading_scrate_cont';
const M_GRADING_SCORE='mod_poodlltime_grading_score';
const M_GRADING_PLAYER_CONTAINER='mod_poodlltime_grading_player_cont';
const M_GRADING_PLAYER='mod_poodlltime_grading_player';
const M_GRADING_ACTION_CONTAINER='mod_poodlltime_grading_action_cont';
const M_GRADING_FORM_SESSIONTIME='mod_poodlltime_grading_form_sessiontime';
const M_GRADING_FORM_SESSIONSCORE='mod_poodlltime_grading_form_sessionscore';
const M_GRADING_FORM_WPM='mod_poodlltime_grading_form_wpm';
const M_GRADING_FORM_ACCURACY='mod_poodlltime_grading_form_accuracy';
const M_GRADING_FORM_SESSIONENDWORD='mod_poodlltime_grading_form_sessionendword';
const M_GRADING_FORM_SESSIONERRORS='mod_poodlltime_grading_form_sessionerrors';
const M_GRADING_FORM_NOTES='mod_poodlltime_grading_form_notes';
const M_GRADING_FORM_SELFCORRECTIONS='mod_poodlltime_grading_form_selfcorrections';
const M_GRADESADMIN_CONTAINER='mod_poodlltime_gradesadmin_cont';
const M_HIDDEN_PLAYER='mod_poodlltime_hidden_player';
const M_HIDDEN_PLAYER_BUTTON='mod_poodlltime_hidden_player_button';
const M_HIDDEN_PLAYER_BUTTON_ACTIVE='mod_poodlltime_hidden_player_button_active';
const M_HIDDEN_PLAYER_BUTTON_PAUSED='mod_poodlltime_hidden_player_button_paused';
const M_HIDDEN_PLAYER_BUTTON_PLAYING='mod_poodlltime_hidden_player_button_playing';
const M_EVALUATED_MESSAGE='mod_poodlltime_evaluated_message';
const M_QR_PLAYER='mod_poodlltime_qr_player';

//languages
const M_LANG_ENUS = 'en-US';
const M_LANG_ENGB = 'en-GB';
const M_LANG_ENAU = 'en-AU';
const M_LANG_ENIN = 'en-IN';
const M_LANG_ESUS = 'es-US';
const M_LANG_ESES = 'es-ES';
const M_LANG_FRCA = 'fr-CA';
const M_LANG_FRFR = 'fr-FR';
const M_LANG_DEDE = 'de-DE';
const M_LANG_ITIT = 'it-IT';
const M_LANG_PTBR = 'pt-BR';

const M_LANG_DADK = 'da-DK';

const M_LANG_KOKR = 'ko-KR';
const M_LANG_HIIN = 'hi-IN';
const M_LANG_ARAE ='ar-AE';
const M_LANG_ARSA ='ar-SA';
const M_LANG_ZHCN ='zh-CN';
const M_LANG_NLNL ='nl-NL';
const M_LANG_ENIE ='en-IE';
const M_LANG_ENWL ='en-WL';
const M_LANG_ENAB ='en-AB';
const M_LANG_FAIR ='fa-IR';
const M_LANG_DECH ='de-CH';
const M_LANG_HEIL ='he-IL';
const M_LANG_IDID ='id-ID';
const M_LANG_JAJP ='ja-JP';
const M_LANG_MSMY ='ms-MY';
const M_LANG_PTPT ='pt-PT';
const M_LANG_RURU ='ru-RU';
const M_LANG_TAIN ='ta-IN';
const M_LANG_TEIN ='te-IN';
const M_LANG_TRTR ='tr-TR';

const TRANSCRIBER_NONE = 0;
const TRANSCRIBER_AMAZONTRANSCRIBE = 1;
const TRANSCRIBER_GOOGLECLOUDSPEECH = 2;
const TRANSCRIBER_GOOGLECHROME = 3;


const M_PUSH_NONE =0;
const M_PUSH_PASSAGE =1;
const M_PUSH_ALTERNATIVES =2;
const M_PUSH_QUESTIONS =3;
const M_PUSH_LEVEL =4;

}