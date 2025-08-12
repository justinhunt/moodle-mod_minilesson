<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 2018/06/16
 * Time: 19:31
 */

namespace mod_minilesson;

defined('MOODLE_INTERNAL') || die();

class constants
{
    //component name, db tables, things that define app
    const M_COMPONENT = 'mod_minilesson';

    const M_DEFAULT_CLOUDPOODLL = "cloud.poodll.com";
    const M_FILEAREA_SUBMISSIONS = 'submission';
    const M_TABLE = 'minilesson';
    const M_ATTEMPTSTABLE = 'minilesson_attempt';
    const M_AITABLE = 'minilesson_ai_result';
    const M_QTABLE = 'minilesson_rsquestions';
    const M_AUTHTABLE = 'minilesson_auth';
    const M_CORRECTPHONES_TABLE = 'minilesson_correctphones';
    const M_MODNAME = 'minilesson';
    const M_URL = '/mod/minilesson';
    const M_PATH = '/mod/minilesson';
    const M_CLASS = 'mod_minilesson';
    const M_PLUGINSETTINGS = '/admin/settings.php?section=modsettingminilesson';
    const M_STATE_COMPLETE = 1;
    const M_STATE_INCOMPLETE = 0;

    const M_NOITEMS_CONT = 'mod_minilesson_noitems_cont';
    const M_ITEMS_CONT = 'mod_minilesson_items_cont';
    const M_ITEMS_TABLE = 'mod_minilesson_qpanel';

    const M_USE_DATATABLES = 0;
    const M_USE_PAGEDTABLES = 1;

    const M_NEURALVOICES = array(
        "Amy",
        "Emma",
        "Brian",
        "Arthur",
        "Olivia",
        "Aria",
        "Ayanda",
        "Ivy",
        "Joanna",
        "Kendra",
        "Kimberly",
        "Salli",
        "Joey",
        "Justin",
        "Kevin",
        "Matthew",
        "Camila",
        "Lupe",
        "Pedro",
        "Gabrielle",
        "Vicki",
        "Seoyeon",
        "Takumi",
        "Lucia",
        "Lea",
        "Remi",
        "Bianca",
        "Laura",
        "Kajal",
        "Suvi",
        "Liam",
        "Daniel",
        "Hannah",
        "Camila",
        "Ida",
        "Kazuha",
        "Tomoko",
        "Elin",
        "Hala",
        "Zayd",
        "Lisa"
    );


    //grading options
    const M_GRADEHIGHEST = 0;
    const M_GRADELOWEST = 1;
    const M_GRADELATEST = 2;
    const M_GRADEAVERAGE = 3;
    const M_GRADENONE = 4;
    //accuracy adjustment method options
    const ACCMETHOD_NONE = 0;
    const ACCMETHOD_AUTO = 1;
    const ACCMETHOD_FIXED = 2;
    const ACCMETHOD_NOERRORS = 3;
    //what to display to user when reviewing activity options
    const POSTATTEMPT_NONE = 0;
    const POSTATTEMPT_EVAL = 1;
    const POSTATTEMPT_EVALERRORS = 2;
    const RELEVANCE = "customint2";
    const RELEVANCETYPE_NONE = 0;
    const RELEVANCETYPE_QUESTION = 1;
    const RELEVANCETYPE_MODELANSWER = 2;
    //Constants for RS Questions
    const NONE = 0;
    const TYPE_TEXTPROMPT_LONG = 'multichoicelong';

    const TYPE_MULTIAUDIO = 'multiaudio';
    const TYPE_MULTICHOICE = 'multichoice';
    const TYPE_PAGE = 'page';
    const TYPE_DICTATIONCHAT = 'dictationchat';
    const TYPE_LGAPFILL = 'listeninggapfill';
    const TYPE_TGAPFILL = 'typinggapfill';
    const TYPE_SGAPFILL = 'speakinggapfill';
    const TYPE_PGAPFILL = 'passagegapfill';
    const TYPE_COMPQUIZ = 'comprehensionquiz';
    const TYPE_H5P = 'h5p';
    const TYPE_DICTATION = 'dictation';
    const TYPE_SPEECHCARDS = 'speechcards';
    const TYPE_LISTENREPEAT = 'listenrepeat';
    const TYPE_SMARTFRAME = 'smartframe';
    const TYPE_SHORTANSWER = 'shortanswer';
    const TYPE_SPACEGAME = 'spacegame';
    const TYPE_FREEWRITING = 'freewriting';
    const TYPE_FREESPEAKING = 'freespeaking';
    const TYPE_FLUENCY = 'fluency';
    const TYPE_PASSAGEREADING = 'passagereading';
    const TYPE_CONVERSATION = 'conversation';
    const TYPE_AUDIOCHAT = 'audiochat';
    const TYPE_WORDSHUFFLE = 'wordshuffle';
    const TYPE_SCATTER = 'scatter';

    const AUDIOSTORYMETA = 'itemaudiofname';
    const AUDIOSTORYZOOMANDPAN = 'itemaudiostoryzoom';
    const ZOOMANDPAN_NONE = 0;
    const ZOOMANDPAN_LITE = 1;
    const ZOOMANDPAN_MEDIUM = 2;
    const ZOOMANDPAN_MORE = 3;
    const AUDIOPROMPT = 'audioitem';
    const AUDIOANSWER = 'audioanswer';
    const AUDIOMODEL = 'audiomodel';
    const CORRECTANSWER = 'correctanswer';
    const AUDIOPROMPT_FILEAREA = 'audioitem';
    const TEXTPROMPT_FILEAREA = 'textitem';
    const TEXTQUESTION = 'itemtext';
    const TEXTINSTRUCTIONS = 'iteminstructions';
    const TEXTQUESTION_FORMAT = 'itemtextformat';
    const TTSQUESTION = 'itemtts';
    const TTSQUESTIONVOICE = 'itemttsvoice';
    const TTSQUESTIONOPTION = 'itemttsoption';
    const TTSAUTOPLAY = 'itemttsautoplay';
    const TTSDIALOG = 'itemttsdialog';
    const TTSPASSAGE = 'itemttspassage';
    const AUDIOSTORY = 'itemaudiostory';
    const AUDIOSTORYTIMES = 'itemaudiostorytimes';
    const TTSDIALOGOPTS = 'itemttsdialogopts';
    const TTSPASSAGEOPTS = 'itemttspassageopts';

    const TTSDIALOGVOICEA = 'itemttsdialogvoicea';
    const TTSDIALOGVOICEB = 'itemttsdialogvoiceb';
    const TTSDIALOGVOICEC = 'itemttsdialogvoicec';
    const TTSDIALOGVISIBLE = 'itemttsdialogvisible';
    const TTSPASSAGEVOICE = 'itemttspassagevoice';
    const TTSPASSAGESPEED = 'itemttspassagespeed';

    const MEDIAQUESTION = 'itemmedia';
    const QUESTIONTEXTAREA = 'itemtextarea';
    const YTVIDEOID = 'itemytid';
    const YTVIDEOSTART = 'itemytstart';
    const YTVIDEOEND = 'itemytend';
    const MEDIAIFRAME = 'customdata5';
    const TEXTANSWER = 'customtext';
    const FILEANSWER = 'customfile';
    const H5PFILE = 'customfile1';
    const CUSTOMDATA = 'customdata';
    const CUSTOMINT = 'customint';
    const POLLYVOICE = 'customtext5';
    const POLLYOPTION = 'customint4';
    const CONFIRMCHOICE = 'customint3';
    const AIGRADE_INSTRUCTIONS = 'customtext6';

    const AIGRADE_FEEDBACK = 'customtext2';
    const AIGRADE_FEEDBACK_LANGUAGE = 'customtext4';
    const AIGRADE_MODELANSWER = 'customtext3';
    const AUDIOCHAT_INSTRUCTIONS = 'customtext6';
    const AUDIOCHAT_GRADEINSTRUCTIONS = 'customdata3';
    const AUDIOCHAT_ROLE = 'customtext2';
    const AUDIOCHAT_VOICE = 'customtext3';
    const AUDIOCHAT_NATIVE_LANGUAGE = 'customtext4';
    const AUDIOCHAT_TOPIC = 'customtext5';
    const AUDIOCHAT_AIDATA1  = 'customdata1';
    const AUDIOCHAT_AIDATA2  = 'customdata2';
    const AUDIOCHAT_AUTORESPONSE = 'customint4';

    const AUDIOCHAT_ALLOWRETRY  = 'customint5';
    const READINGPASSAGE = 'customtext1';
    const PASSAGEGAPFILL_PASSAGE = 'customtext1';
    const PASSAGEGAPFILL_HINTS = 'customint5';
    const PENALIZEHINTS = 'customint2';
    const ALTERNATES = 'customtext2';
    const TARGETWORDCOUNT = 'customint3';
    const TOTALMARKS = 'customint1';
    const TEXTQUESTION_FILEAREA = 'itemarea';
    const TEXTANSWER_FILEAREA = 'customtextfilearea';
    const PASSAGEPICTURE = 'passagepicture';
    const PASSAGEPICTURE_FILEAREA = 'passagepicture';
    const TIMELIMIT = 'timelimit';
    const GAPFILLALLOWRETRY = 'customint3';
    const FLUENCYCORRECTTHRESHOLD = 'customint3';
    const NOPASTING = 'customint4';
    const GAPFILLHIDESTARTPAGE = 'customint5';
    const SG_INCLUDEMATCHING = 'customint3';
    const SG_ALIENCOUNT_MULTICHOICE = 'customint1';
    const SG_ALIENCOUNT_MATCHING = 'customint2';
    const SG_ALLOWRETRY = 'customint4';

    const SCATTER_ALLOWRETRY = 'customint4';
    const MAXANSWERS = 4;
    const MAXCUSTOMTEXT = 7;
    const MAXCUSTOMDATA = 5;
    const MAXCUSTOMINT = 5;

    const ITEMTEXTAREA_EDOPTIONS = array('trusttext' => 0, 'noclean' => 1, 'maxfiles' => 0);
    const READSENTENCE = 'customint2';
    const IGNOREPUNCTUATION = 'customint2';
    const SHOWTEXTPROMPT = 'customint1';
    const TEXTPROMPT_WORDS = 1;
    const TEXTPROMPT_DOTS = 0;

    const LISTENORREAD = 'customint2';
    const LISTENORREAD_READ = 0;
    const LISTENORREAD_LISTEN = 1;
    const LISTENORREAD_LISTENANDREAD = 2;
    const LISTENORREAD_IMAGE = 3;

    const LAYOUT = 'layout';
    const LAYOUT_AUTO = 0;
    const LAYOUT_HORIZONTAL = 1;
    const LAYOUT_VERTICAL = 2;
    const LAYOUT_MAGAZINE = 3;

    const TTS_NORMAL = 0;
    const TTS_SLOW = 1;
    const TTS_VERYSLOW = 2;
    const TTS_SSML = 3;

    const ALL_VOICES_NINGXIA = array(
        constants::M_LANG_ARAE => ['Hala' => 'Hala', 'Zayd' => 'Zayd'],
        constants::M_LANG_ARSA => ['Zeina' => 'Zeina', 'ar-XA-Wavenet-B' => 'Amir_g', 'ar-XA-Wavenet-A' => 'Salma_g', 'ar-MA-Azure-JamalNeural' => 'Jamal_a', 'ar-MA-Azure-MounaNeural' => 'Mouna_a'],
        constants::M_LANG_ZHCN => ['Zhiyu' => 'Zhiyu'],
        constants::M_LANG_DADK => ['Naja' => 'Naja', 'Mads' => 'Mads'],
        constants::M_LANG_NLNL => ["Ruben" => "Ruben", "Lotte" => "Lotte", "Laura" => "Laura"],
        constants::M_LANG_NLBE => ["Lisa" => "Lisa"],
        constants::M_LANG_ENUS => [
            'Joey' => 'Joey',
            'Justin' => 'Justin',
            'Matthew' => 'Matthew',
            'en-US-LemonFox-puck' => 'Puck',
            'Ivy' => 'Ivy',
            'Joanna' => 'Joanna',
            'Kendra' => 'Kendra',
            'Kimberly' => 'Kimberly',
            'Salli' => 'Salli',
            'en-US-LemonFox-nova' => 'Tiffany'
        ],
        constants::M_LANG_ENGB => ['Brian' => 'Brian', 'Amy' => 'Amy', 'Emma' => 'Emma'],
        constants::M_LANG_ENAU => ['Russell' => 'Russell', 'Nicole' => 'Nicole'],
        constants::M_LANG_ENIN => ['Aditi' => 'Aditi', 'Raveena' => 'Raveena'],
        constants::M_LANG_ENWL => ["Geraint" => "Geraint"],
        constants::M_LANG_FIFI => ['Suvi' => 'Suvi'],
        constants::M_LANG_FRCA => ['Chantal' => 'Chantal'],
        constants::M_LANG_FRFR => ['Mathieu' => 'Mathieu', 'Celine' => 'Celine', 'Lea' => 'Lea'],
        constants::M_LANG_DEDE => ['Hans' => 'Hans', 'Marlene' => 'Marlene', 'Vicki' => 'Vicki'],
        constants::M_LANG_DEAT => ['Hannah' => 'Hannah'],
        constants::M_LANG_HIIN => ["Aditi" => "Aditi"],
        constants::M_LANG_ISIS => ['Dora' => 'Dora', 'Karl' => 'Karl'],
        constants::M_LANG_ITIT => ['Carla' => 'Carla', 'Bianca' => 'Bianca', 'Giorgio' => 'Giorgio'],
        constants::M_LANG_JAJP => ['Takumi' => 'Takumi', 'Mizuki' => 'Mizuki'],
        constants::M_LANG_KOKR => ['Seoyeon' => 'Seoyeon'],
        constants::M_LANG_NONO => ['Liv' => 'Liv'],
        constants::M_LANG_PSAF => ['ps-AF-Azure-GulNawazNeural' => 'GulNawaz_a', 'ps-AF-Azure-LatifaNeural' => 'Latifa_a'],
        constants::M_LANG_PLPL => ['Ewa' => 'Ewa', 'Maja' => 'Maja', 'Jacek' => 'Jacek', 'Jan' => 'Jan'],
        constants::M_LANG_PTBR => ['Ricardo' => 'Ricardo', 'Vitoria' => 'Vitoria', 'Camila' => 'Camila'],
        constants::M_LANG_PTPT => ["Ines" => "Ines", 'Cristiano' => 'Cristiano'],
        constants::M_LANG_RORO => ['Carmen' => 'Carmen'],
        constants::M_LANG_RURU => ["Tatyana" => "Tatyana", "Maxim" => "Maxim"],
        constants::M_LANG_ESUS => ['Miguel' => 'Miguel', 'Penelope' => 'Penelope', 'Lupe' => 'Lupe', 'Pedro' => 'Pedro'],
        constants::M_LANG_ESES => ['Enrique' => 'Enrique', 'Conchita' => 'Conchita', 'Lucia' => 'Lucia'],
        constants::M_LANG_SVSE => ['Astrid' => 'Astrid'],
        constants::M_LANG_SOSO => ['so-SO-Azure-UbaxNeural' => 'Ubax_a', 'so-SO-Azure-MuuseNeural' => 'Muuse_a'],
        constants::M_LANG_TRTR => ['Filiz' => 'Filiz'],
    );

    const ALL_VOICES = array(
        constants::M_LANG_ARAE => ['Hala' => 'Hala', 'Zayd' => 'Zayd'],
        constants::M_LANG_ARSA => ['Zeina' => 'Zeina', 'ar-MA-Azure-JamalNeural' => 'Jamal_a', 'ar-XA-Wavenet-B' => 'Amir_g', 'ar-XA-Wavenet-A' => 'Salma_g', 'ar-MA-Azure-MounaNeural' => 'Mouna_a'],
        constants::M_LANG_BGBG => ['bg-BG-Standard-A' => 'Mila_g'],//nikolai
        constants::M_LANG_HRHR => ['hr-HR-Whisper-alloy' => 'Marko', 'hr-HR-Whisper-shimmer' => 'Ivana'],
        constants::M_LANG_ZHCN => ['Zhiyu' => 'Zhiyu'],
        constants::M_LANG_CSCZ => ['cs-CZ-Wavenet-A' => 'Zuzana_g', 'cs-CZ-Standard-A' => 'Karolina_g'],
        constants::M_LANG_DADK => ['Naja' => 'Naja', 'Mads' => 'Mads'],
        constants::M_LANG_NLNL => ["Ruben" => "Ruben", "Lotte" => "Lotte", "Laura" => "Laura"],
        constants::M_LANG_NLBE => ["nl-BE-Wavenet-B" => "Marc_g", "nl-BE-Wavenet-A" => "Marie_g", "Lisa" => "Lisa"],
            //constants::M_LANG_DECH => [],
        constants::M_LANG_ENUS => [
            'Joey' => 'Joey',
            'Justin' => 'Justin',
            'Kevin' => 'Kevin',
            'Matthew' => 'Matthew',
            'en-US-LemonFox-puck' => 'Puck',
            'Ivy' => 'Ivy',
            'Joanna' => 'Joanna',
            'Kendra' => 'Kendra',
            'Kimberly' => 'Kimberly',
            'Salli' => 'Salli',
            'en-US-Whisper-alloy' => 'Ricky',
            'en-US-Whisper-onyx' => 'Ed',
            'en-US-Whisper-nova' => 'Tiffany',
            'en-US-Whisper-shimmer' => 'Tammy'
        ],
        constants::M_LANG_ENGB => ['Brian' => 'Brian', 'Amy' => 'Amy', 'Emma' => 'Emma', 'Arthur' => 'Arthur'],
        constants::M_LANG_ENAU => ['Russell' => 'Russell', 'Nicole' => 'Nicole', 'Olivia' => 'Olivia'],
        constants::M_LANG_ENNZ => ['Aria' => 'Aria'],
        constants::M_LANG_ENZA => ['Ayanda' => 'Ayanda'],
        constants::M_LANG_ENIN => ['Aditi' => 'Aditi', 'Raveena' => 'Raveena', 'Kajal' => 'Kajal'],
            // constants::M_LANG_ENIE => [],
        constants::M_LANG_ENWL => ["Geraint" => "Geraint"],
            // constants::M_LANG_ENAB => [],
        constants::M_LANG_FILPH => ['fil-PH-Wavenet-A' => 'Darna_g', 'fil-PH-Wavenet-B' => 'Reyna_g', 'fil-PH-Wavenet-C' => 'Bayani_g', 'fil-PH-Wavenet-D' => 'Ernesto_g'],
        constants::M_LANG_FIFI => ['Suvi' => 'Suvi', 'fi-FI-Wavenet-A' => 'Kaarina_g'],
        constants::M_LANG_FRCA => ['Chantal' => 'Chantal', 'Gabrielle' => 'Gabrielle', 'Liam' => 'Liam'],
        constants::M_LANG_FRFR => ['Mathieu' => 'Mathieu', 'Celine' => 'Celine', 'Lea' => 'Lea', 'Remi' => 'Remi'],
        constants::M_LANG_DEDE => ['Hans' => 'Hans', 'Marlene' => 'Marlene', 'Vicki' => 'Vicki', 'Daniel' => 'Daniel'],
        constants::M_LANG_DEAT => ['Hannah' => 'Hannah'],
        constants::M_LANG_ELGR => ['el-GR-Wavenet-A' => 'Sophia_g', 'el-GR-Standard-A' => 'Isabella_g'],
        constants::M_LANG_HIIN => ["Aditi" => "Aditi"],
        constants::M_LANG_HEIL => ['he-IL-Wavenet-A' => 'Sarah_g', 'he-IL-Wavenet-B' => 'Noah_g'],
        constants::M_LANG_HUHU => ['hu-HU-Wavenet-A' => 'Eszter_g'],

        constants::M_LANG_IDID => ['id-ID-Wavenet-A' => 'Guntur_g', 'id-ID-Wavenet-B' => 'Bhoomik_g'],
        constants::M_LANG_ISIS => ['Dora' => 'Dora', 'Karl' => 'Karl'],
        constants::M_LANG_ITIT => ['Carla' => 'Carla', 'Bianca' => 'Bianca', 'Giorgio' => 'Giorgio'],
        constants::M_LANG_JAJP => ['Takumi' => 'Takumi', 'Mizuki' => 'Mizuki', 'Kazuha' => 'Kazuha', 'Tomoko' => 'Tomoko'],
        constants::M_LANG_KOKR => ['Seoyeon' => 'Seoyeon'],
        constants::M_LANG_LVLV => ['lv-LV-Standard-A' => 'Janis_g'],
        constants::M_LANG_LTLT => ['lt-LT-Standard-A' => 'Matas_g'],
        constants::M_LANG_MINZ => ['mi-NZ-Whisper-alloy' => 'Tane', 'mi-NZ-Whisper-shimmer' => 'Aroha'],
        constants::M_LANG_MKMK => ['mk-MK-Whisper-alloy' => 'Trajko', 'mk-MK-Whisper-shimmer' => 'Marija'],
        constants::M_LANG_MSMY => ['ms-MY-Whisper-alloy' => 'Afsah', 'ms-MY-Whisper-shimmer' => 'Siti'],
        constants::M_LANG_NONO => ['Liv' => 'Liv', 'Ida' => 'Ida', 'nb-NO-Wavenet-B' => 'Lars_g', 'nb-NO-Wavenet-A' => 'Hedda_g', 'nb-NO-Wavenet-D' => 'Anders_g'],
        constants::M_LANG_PSAF => ['ps-AF-Azure-GulNawazNeural' => 'GulNawaz_a', 'ps-AF-Azure-LatifaNeural' => 'Latifa_a'],
        constants::M_LANG_FAIR => ['fa-IR-Azure-FaridNeural' => 'Farid_a', 'fa-IR-Azure-DilaraNeural' => 'Dilara_a'],
        constants::M_LANG_PLPL => ['Ewa' => 'Ewa', 'Maja' => 'Maja', 'Jacek' => 'Jacek', 'Jan' => 'Jan'],
        constants::M_LANG_PTBR => ['Ricardo' => 'Ricardo', 'Vitoria' => 'Vitoria', 'Camila' => 'Camila'],
        constants::M_LANG_PTPT => ["Ines" => "Ines", 'Cristiano' => 'Cristiano'],
        constants::M_LANG_RORO => ['Carmen' => 'Carmen', 'ro-RO-Wavenet-A' => 'Sorina_g'],
        constants::M_LANG_RURU => ["Tatyana" => "Tatyana", "Maxim" => "Maxim", "ru-RU-Azure-SvetlanaNeural" => 'Svetlana_a', "ru-RU-Azure-DmitryNeural" => "Dmitry_a", "ru-RU-Azure-DariyaNeural" => "Dariya_a"],
        constants::M_LANG_ESUS => ['Miguel' => 'Miguel', 'Penelope' => 'Penelope', 'Lupe' => 'Lupe', 'Pedro' => 'Pedro'],
        constants::M_LANG_ESES => ['Enrique' => 'Enrique', 'Conchita' => 'Conchita', 'Lucia' => 'Lucia'],
        constants::M_LANG_SVSE => ['Astrid' => 'Astrid', 'Elin' => 'Elin'],
        constants::M_LANG_SKSK => ['sk-SK-Wavenet-A' => 'Laura_g', 'sk-SK-Standard-A' => 'Natalia_g'],
        constants::M_LANG_SLSI => ['sl-SI-Whisper-alloy' => 'Vid', 'sl-SI-Whisper-shimmer' => 'Pia'],
        constants::M_LANG_SOSO => ['so-SO-Azure-UbaxNeural' => 'Ubax_a', 'so-SO-Azure-MuuseNeural' => 'Muuse_a'],
        constants::M_LANG_SRRS => ['sr-RS-Standard-A' => 'Milena_g'],
        constants::M_LANG_TAIN => ['ta-IN-Wavenet-A' => 'Dyuthi_g', 'ta-IN-Wavenet-B' => 'Bhoomik_g'],
        constants::M_LANG_TEIN => ['te-IN-Standard-A' => 'Anandi_g', 'te-IN-Standard-B' => 'Kai_g'],
        constants::M_LANG_TRTR => ['Filiz' => 'Filiz'],
        constants::M_LANG_UKUA => ['uk-UA-Wavenet-A' => 'Katya_g'],
        constants::M_LANG_VIVN => ['vi-VN-Wavenet-A' => 'Huyen_g', 'vi-VN-Wavenet-B' => 'Duy_g'],
    );

    //CSS ids/classes
    const M_RECORD_BUTTON = 'mod_minilesson_record_button';
    const M_START_BUTTON = 'mod_minilesson_start_button';
    const M_READING_AUDIO_URL = 'mod_minilesson_readingaudiourl';
    const M_DRAFT_CONTROL = 'mod_minilesson_draft_control';
    const M_PROGRESS_CONTAINER = 'mod_minilesson_progress_cont';
    const M_HIDER = 'mod_minilesson_hider';
    const M_STOP_BUTTON = 'mod_minilesson_stop_button';
    const M_WHERETONEXT_CONTAINER = 'mod_minilesson_wheretonext_cont';
    const M_RECORD_BUTTON_CONTAINER = 'mod_minilesson_record_button_cont';
    const M_START_BUTTON_CONTAINER = 'mod_minilesson_start_button_cont';
    const M_STOP_BUTTON_CONTAINER = 'mod_minilesson_stop_button_cont';
    const M_RECORDERID = 'therecorderid';
    const M_RECORDING_CONTAINER = 'mod_minilesson_recording_cont';
    const M_RECORDER_CONTAINER = 'mod_minilesson_recorder_cont';
    const M_DUMMY_RECORDER = 'mod_minilesson_dummy_recorder';
    const M_RECORDER_INSTRUCTIONS_RIGHT = 'mod_minilesson_recorder_instr_right';
    const M_RECORDER_INSTRUCTIONS_LEFT = 'mod_minilesson_recorder_instr_left';
    const M_INSTRUCTIONS_CONTAINER = 'mod_minilesson_instructions_cont';
    const M_PASSAGE_CONTAINER = 'mod_minilesson_passage_cont';
    const M_MSV_MODE = 'mod_minilesson_msvmode';
    const M_QUICK_MODE = 'mod_minilesson_spotcheckmode';
    const M_GRADING_MODE = 'mod_minilesson_gradingmode';
    const M_QUIZ_CONTAINER = 'mod_minilesson_quiz_cont';
    const M_QUIZ_PLACEHOLDER = 'mod_minilesson_placeholder';
    const M_QUIZ_SKELETONBOX = 'mod_minilesson_skeleton_box';
    const M_POSTATTEMPT = 'mod_minilesson_postattempt';
    const M_FEEDBACK_CONTAINER = 'mod_minilesson_feedback_cont';
    const M_ERROR_CONTAINER = 'mod_minilesson_error_cont';
    const M_GRADING_ERROR_CONTAINER = 'mod_minilesson_grading_error_cont';
    const M_GRADING_ERROR_IMG = 'mod_minilesson_grading_error_img';
    const M_GRADING_ERROR_SCORE = 'mod_minilesson_grading_error_score';

    const M_GRADING_QUIZ_CONTAINER = 'mod_minilesson_grading_quiz_cont';
    const M_TWOCOL_CONTAINER = 'mod_minilesson_twocol_cont';
    const M_TWOCOL_QUIZ_CONTAINER = 'mod_minilesson_twocol_quiz_cont';
    const M_TWOCOL_PLAYER_CONTAINER = 'mod_minilesson_twocol_player_cont';
    const M_TWOCOL_PLAYER = 'mod_minilesson_twocol_player';
    const M_TWOCOL_LEFTCOL = 'mod_minilesson_leftcol';
    const M_TWOCOL_RIGHTCOL = 'mod_minilesson_rightcol';
    const M_GRADING_QUIZ_SCORE = 'mod_minilesson_grading_quiz_score';
    const M_GRADING_ACCURACY_CONTAINER = 'mod_minilesson_grading_accuracy_cont';
    const M_GRADING_ACCURACY_IMG = 'mod_minilesson_grading_accuracy_img';
    const M_GRADING_ACCURACY_SCORE = 'mod_minilesson_grading_accuracy_score';
    const M_GRADING_SESSION_SCORE = 'mod_minilesson_grading_session_score';
    const M_GRADING_SESSIONSCORE_CONTAINER = 'mod_minilesson_grading_sessionscore_cont';
    const M_GRADING_ERRORRATE_SCORE = 'mod_minilesson_grading_errorrate_score';
    const M_GRADING_ERRORRATE_CONTAINER = 'mod_minilesson_grading_errorrate_cont';
    const M_GRADING_SCRATE_SCORE = 'mod_minilesson_grading_scrate_score';
    const M_GRADING_SCRATE_CONTAINER = 'mod_minilesson_grading_scrate_cont';
    const M_GRADING_SCORE = 'mod_minilesson_grading_score';
    const M_GRADING_PLAYER_CONTAINER = 'mod_minilesson_grading_player_cont';
    const M_GRADING_PLAYER = 'mod_minilesson_grading_player';
    const M_GRADING_ACTION_CONTAINER = 'mod_minilesson_grading_action_cont';
    const M_GRADING_FORM_SESSIONTIME = 'mod_minilesson_grading_form_sessiontime';
    const M_GRADING_FORM_SESSIONSCORE = 'mod_minilesson_grading_form_sessionscore';
    const M_GRADING_FORM_SESSIONENDWORD = 'mod_minilesson_grading_form_sessionendword';
    const M_GRADING_FORM_SESSIONERRORS = 'mod_minilesson_grading_form_sessionerrors';
    const M_GRADING_FORM_NOTES = 'mod_minilesson_grading_form_notes';
    const M_HIDDEN_PLAYER = 'mod_minilesson_hidden_player';
    const M_HIDDEN_PLAYER_BUTTON = 'mod_minilesson_hidden_player_button';
    const M_HIDDEN_PLAYER_BUTTON_ACTIVE = 'mod_minilesson_hidden_player_button_active';
    const M_HIDDEN_PLAYER_BUTTON_PAUSED = 'mod_minilesson_hidden_player_button_paused';
    const M_HIDDEN_PLAYER_BUTTON_PLAYING = 'mod_minilesson_hidden_player_button_playing';
    const M_EVALUATED_MESSAGE = 'mod_minilesson_evaluated_message';
    const M_QR_PLAYER = 'mod_minilesson_qr_player';
    const M_LINK_BOX = 'mod_minilesson_link_box';
    const M_LINK_BOX_TITLE = 'mod_minilesson_link_box_title';
    const M_NOITEMS_MSG = 'mod_minilesson_noitems_msg';


    //languages
    const M_LANG_ENUS = 'en-US';
    const M_LANG_ENGB = 'en-GB';
    const M_LANG_ENAU = 'en-AU';
    const M_LANG_ENNZ = 'en-NZ';
    const M_LANG_ENZA = 'en-ZA';
    const M_LANG_ENIN = 'en-IN';
    const M_LANG_ESUS = 'es-US';
    const M_LANG_ESES = 'es-ES';
    const M_LANG_FRCA = 'fr-CA';
    const M_LANG_FRFR = 'fr-FR';
    const M_LANG_DEDE = 'de-DE';
    const M_LANG_DEAT = 'de-AT';
    const M_LANG_ITIT = 'it-IT';
    const M_LANG_PTBR = 'pt-BR';

    const M_LANG_DADK = 'da-DK';
    const M_LANG_FILPH = 'fil-PH';

    const M_LANG_KOKR = 'ko-KR';
    const M_LANG_HIIN = 'hi-IN';
    const M_LANG_ARAE = 'ar-AE';
    const M_LANG_ARSA = 'ar-SA';
    const M_LANG_ZHCN = 'zh-CN';
    const M_LANG_NLNL = 'nl-NL';
    const M_LANG_NLBE = 'nl-BE';
    const M_LANG_ENIE = 'en-IE';
    const M_LANG_ENWL = 'en-WL';
    const M_LANG_ENAB = 'en-AB';
    const M_LANG_FAIR = 'fa-IR';
    const M_LANG_DECH = 'de-CH';
    const M_LANG_HEIL = 'he-IL';
    const M_LANG_IDID = 'id-ID';
    const M_LANG_JAJP = 'ja-JP';
    const M_LANG_MSMY = 'ms-MY';
    const M_LANG_PTPT = 'pt-PT';
    const M_LANG_RURU = 'ru-RU';
    const M_LANG_TAIN = 'ta-IN';
    const M_LANG_TEIN = 'te-IN';
    const M_LANG_TRTR = 'tr-TR';
    const M_LANG_NONO = 'no-NO';
    const M_LANG_NBNO = 'nb-NO';
    const M_LANG_NNNO = 'nn-NO';
    const M_LANG_PSAF = 'ps-AF';
    const M_LANG_PLPL = 'pl-PL';
    const M_LANG_RORO = 'ro-RO';
    const M_LANG_SVSE = 'sv-SE';

    const M_LANG_UKUA = 'uk-UA';
    const M_LANG_EUES = 'eu-ES';
    const M_LANG_FIFI = 'fi-FI';
    const M_LANG_HUHU = 'hu-HU';

    const M_LANG_MINZ = 'mi-NZ';
    const M_LANG_BGBG = 'bg-BG';
    const M_LANG_CSCZ = 'cs-CZ';
    const M_LANG_ELGR = 'el-GR';
    const M_LANG_HRHR = 'hr-HR';
    const M_LANG_LTLT = 'lt-LT';
    const M_LANG_LVLV = 'lv-LV';
    const M_LANG_SKSK = 'sk-SK';
    const M_LANG_SLSI = 'sl-SI';
    const M_LANG_SOSO = 'so-SO';
    const M_LANG_ISIS = 'is-IS';
    const M_LANG_MKMK = 'mk-MK';
    const M_LANG_SRRS = 'sr-RS';
    const M_LANG_VIVN = 'vi-VN';

    const M_PROMPT_SEPARATE = 0;
    const M_PROMPT_RICHTEXT = 1;

    const TRANSCRIBER_NONE = 0;
    const TRANSCRIBER_AUTO = 1;
    const TRANSCRIBER_POODLL = 2;


    const M_PUSH_NONE = 0;
    const M_PUSH_PASSAGE = 1;
    const M_PUSH_ALTERNATIVES = 2;
    const M_PUSH_QUESTIONS = 3;
    const M_PUSH_LEVEL = 4;

    const M_QUIZ_FINISHED = "mod_minilesson_quiz_finished";
    const M_QUIZ_REATTEMPT = "mod_minilesson_quiz_reattempt";

    const M_ANIM_FANCY = 0;
    const M_ANIM_PLAIN = 1;

    const M_CONTWIDTH_COMPACT = 'compact';
    const M_CONTWIDTH_WIDE = 'wide';
    const M_CONTWIDTH_FULL = 'full';
    const M_STANDARD_FONTS = [
        "Arial",
        "Arial Black",
        "Verdana",
        "Tahoma",
        "Trebuchet MS",
        "Impact",
        "Times New Roman",
        "Didot",
        "Georgia",
        "American Typewriter",
        "Andalé Mono",
        "Courier",
        "Lucida Console",
        "Monaco",
        "Bradley Hand",
        "Brush Script MT",
        "Luminari",
        "Comic Sans MS"
    ];

    const M_GOOGLE_FONTS = ["Andika"];

    const M_ST_SRC_RECORD = 1;
    const M_ST_SRC_UPLOAD = 2;
    const M_ST_SRC_STT = 3;
    // Finish screen options.
    const FINISHSCREEN_SIMPLE = 1;
    const FINISHSCREEN_FULL = 0;
    const FINISHSCREEN_CUSTOM = 2;
    // Push Options.
    const M_PUSH_TRANSCRIBER = 1;
    const M_PUSH_SHOWITEMREVIEW = 2;
    const M_PUSH_MAXATTEMPTS = 4;
    const M_PUSH_REGION = 5;
    const M_PUSH_LESSONFONT = 6;
    const M_PUSH_FINISHSCREENCUSTOM = 7;
    const M_PUSH_FINISHSCREEN = 8;
    const M_PUSH_CSSKEY = 9;
    const M_PUSH_CONTAINERWIDTH = 10;
    const M_PUSH_ITEMS = 11;

    /**
     * No push mode selected.
     */
    const PUSHMODE_NONE = 0;

    /**
     * Push mode that matches on the module name.
     */
    const PUSHMODE_MODULENAME = 1;
    /**
     * Push mode to all minilessons in the course.
     */
    const PUSHMODE_COURSE = 2;
    /**
     * Push mode to all minilessons in the site.
     */
    const PUSHMODE_SITE = 3;
    const M_LANG_SAMPLES = [
        constants::M_LANG_ARAE => 'عندما يكون الطقس مشمسًا، دعنا نخرج للتنزه في الحديقة.',
        constants::M_LANG_ARSA => 'عندما يكون الطقس مشمسًا، دعنا نخرج للتنزه في الحديقة.',
        constants::M_LANG_EUES => 'Eguraldi eguzkitsua egiten duenean, goazen parkean paseatzera.',
        constants::M_LANG_BGBG => 'Когато времето е слънчево, нека се разходим в парка.',
        constants::M_LANG_HRHR => 'Kad je sunčano vrijeme, idemo u šetnju parkom.',
        constants::M_LANG_ZHCN => '天气晴朗的时候，我们去公园散步吧。',
        constants::M_LANG_CSCZ => 'Až bude slunečné počasí, pojďme se projít do parku.',
        constants::M_LANG_DADK => 'Når vejret er solrigt, lad os gå en tur i parken.',
        constants::M_LANG_NLNL => 'Als het zonnig is, gaan we een wandeling maken in het park.',
        constants::M_LANG_NLBE => 'Als het zonnig is, gaan we een wandeling maken in het park.',
        constants::M_LANG_ENUS => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENGB => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENAU => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENNZ => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENZA => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENIN => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENIE => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENWL => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_ENAB => 'When the weather is sunny, let\'s go for a walk in the park.',
        constants::M_LANG_FAIR => 'وقتی هوا آفتابی است، بیایید در پارک قدم بزنیم.',
        constants::M_LANG_FILPH => 'Kapag maaraw ang panahon, mamasyal tayo sa parke.',
        constants::M_LANG_FIFI => 'Kun on aurinkoista, mennään puistoon kävelylle.',
        constants::M_LANG_FRCA => 'Quand il fait beau, on va se promener dans le parc.',
        constants::M_LANG_FRFR => 'Quand il fait beau, allons nous promener dans le parc.',
        constants::M_LANG_DEDE => 'Wenn das Wetter sonnig ist, machen wir einen Spaziergang im Park.',
        constants::M_LANG_DEAT => 'Wenn das Wetter sonnig ist, machen wir einen Spaziergang im Park.',
        constants::M_LANG_DECH => 'Wenn das Wetter sonnig ist, machen wir einen Spaziergang im Park.',
        constants::M_LANG_HIIN => 'जब मौसम सुहाना हो तो चलो पार्क में टहलने चलें।.',
        constants::M_LANG_ELGR => 'Όταν ο καιρός είναι ηλιόλουστος, ας πάμε μια βόλτα στο πάρκο.',
        constants::M_LANG_HEIL => 'כשמזג ​​האוויר שמשי, בואו נצא לטייל בפארק.',
        constants::M_LANG_HUHU => 'Amikor süt az idő, menjünk sétálni a parkban.',
        constants::M_LANG_IDID => 'Jika cuaca cerah, mari kita berjalan-jalan di taman.',
        constants::M_LANG_ISIS => 'Þegar sólin skín, förum við í göngutúr í garðinum.',
        constants::M_LANG_ITIT => 'Quando il tempo è soleggiato, andiamo a fare una passeggiata al parco.',
        constants::M_LANG_JAJP => '天気が晴れたら公園に散歩に行きましょう。',
        constants::M_LANG_KOKR => '날씨가 맑으면 공원에 산책하러 가자.',
        constants::M_LANG_LTLT => 'Kai oras saulėtas, eikime pasivaikščioti po parką.',
        constants::M_LANG_LVLV => 'Kad laiks ir saulains, dosimies pastaigā pa parku.',
        constants::M_LANG_MINZ => 'Ka paki te rangi, me haere tatou ki te hīkoi i te papa.',
        constants::M_LANG_MSMY => 'Bila cuaca cerah, jom kita jalan-jalan di taman.',
        constants::M_LANG_MKMK => 'Кога времето е сончево, ајде да одиме на прошетка во паркот.',
        constants::M_LANG_NONO => 'Ora tin solo, ban kana un ratu den parke.',
        constants::M_LANG_PSAF => 'کله چې هوا لمر وي، راځئ چې په پارک کې ګرځو.',
        constants::M_LANG_PLPL => 'Gdy pogoda jest słoneczna, chodźmy na spacer do parku.',
        constants::M_LANG_PTBR => 'Quando o tempo estiver ensolarado, vamos dar uma volta no parque.',
        constants::M_LANG_PTPT => 'Quando o tempo estiver soalheiro, vamos dar uma volta ao parque.',
        constants::M_LANG_RORO => 'Când vremea e însorită, hai să mergem la o plimbare în parc.',
        constants::M_LANG_RURU => 'Когда погода солнечная, давайте прогуляемся в парке.',
        constants::M_LANG_SOSO => 'Marka cimiladu qoraxdu tahay, aan u soconno beerta dhexdeeda.',
        constants::M_LANG_ESUS => 'Cuando el clima esté soleado, salgamos a caminar por el parque.',
        constants::M_LANG_ESES => 'Cuando el clima esté soleado, salgamos a caminar por el parque.',
        constants::M_LANG_SKSK => 'Keď bude slnečné počasie, poďme sa prejsť do parku.',
        constants::M_LANG_SLSI => 'Ko bo sončno vreme, gremo na sprehod v park.',
        constants::M_LANG_SRRS => 'Кад је време сунчано, хајде да прошетамо парком.',
        constants::M_LANG_SVSE => 'När vädret är soligt, låt oss gå en promenad i parken.',
        constants::M_LANG_TAIN => 'வானிலை வெயிலாக இருக்கும்போது, ​​பூங்காவில் நடந்து செல்வோம்.',
        constants::M_LANG_TEIN => 'వాతావరణం ఎండగా ఉన్నప్పుడు, పార్కులో నడకకు వెళ్దాం.',
        constants::M_LANG_TRTR => 'Hava güneşli olunca parkta yürüyüşe çıkalım.',
        constants::M_LANG_UKUA => 'Коли погода сонячна, давайте погуляємо в парку.',
        constants::M_LANG_VIVN => 'Khi trời nắng, chúng ta hãy đi dạo trong công viên.',
    ];

}