<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_minilesson\output;

use core_collator;
use html_writer;
use mod_minilesson\aigen_contextform;
use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\comprehensiontest;
use stdClass;

/**
 * renderer class for mod_minilesson
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Returns the header for the module
     *
     * @param object $moduleinstance
     * @param object $cm
     * @param string $currenttab current tab that is shown.
     * @param int $itemid of the anything that needs to be displayed.
     * @param string $extrapagetitle String to append to the page title.
     * @return string
     */
    public function header($moduleinstance, $cm, $currenttab = '', $itemid = null, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname . ": " . $activityname;
        } else {
            $title = $this->page->course->shortname . ": " . $activityname . ": " . $extrapagetitle;
        }

        // Build the buttons.
        $context = \context_module::instance($cm->id);

        // Header setup.
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        $output = $this->output->header();

        // Show (or not) title.
        $output .= $this->fetch_title($moduleinstance, $activityname);

        if (has_capability('mod/minilesson:evaluate', $context)) {
            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot . '/mod/minilesson/tabs.php');
                $output .= ob_get_contents();
                ob_end_clean();
            }
        }

        return $output;
    }


    /**
     * Fetches the title if we are to show it
     * @param object $moduleinstance
     * @param string $title
     * @return string
     */
    public function fetch_title($moduleinstance, $title) {
        $displaytext = '';
        // Dont show the heading in an iframe, it will be outside this anyway.
        if (!$moduleinstance->foriframe && $moduleinstance->pagelayout !== 'embedded') {
            $thetitle = $this->output->heading($title, 3, 'main bold');
            $displaytext = \html_writer::div($thetitle, constants::M_CLASS . '_center');
        }
        return $displaytext;
    }

    /**
     * Returns the header for the module
     * @param object $moduleinstance
     * @param object $cm
     * @param string|null $extrapagetitle
     * @return string
     */
    public function simpleheader($moduleinstance, $cm, $extrapagetitle = null) {
        global $CFG;

        $activityname = format_string($moduleinstance->name, true, $moduleinstance->course);
        if (empty($extrapagetitle)) {
            $title = $this->page->course->shortname . ": " . $activityname;
        } else {
            $title = $this->page->course->shortname . ": " . $activityname . ": " . $extrapagetitle;
        }

        // Build the buttons.
        $context = \context_module::instance($cm->id);

        // Header setup.
        $this->page->set_title($title);
        $this->page->set_heading($this->page->course->fullname);
        $output = $this->output->header();

        // Show (or not) title.
        $output .= $this->fetch_title($moduleinstance, $activityname);

        return $output;
    }

    /**
     * Return HTML to display limited header
     * @param object $moduleinstance
     * @return string
     */
    public function notabsheader($moduleinstance) {
        $output = $this->output->header();
        // Show (or not) title -  we dont need this do we?.
        return $output;
    }
    /**
     * Return a message that something is not right
     * @param string $message
     * @return string
     */
    public function thatsnotright($message) {

        $ret = $this->output->heading(get_string('thatsnotright', constants::M_COMPONENT), 3);
        $ret .= \html_writer::div($message, constants::M_CLASS . '_thatsnotright_message');
        return $ret;
    }

    /**
     * back to top button
     * @param int $courseid
     * @return string
     */
    public function backtotopbutton($courseid) {
        $button = $this->output->single_button(new \moodle_url(
            '/course/view.php',
            ['id' => $courseid]
        ), get_string('backtotop', constants::M_COMPONENT));

        $ret = \html_writer::div($button, constants::M_CLASS . '_backtotop_cont');
        return $ret;
    }

    /**
     * back to import button
     * @param object $cm
     * @return string
     */
    public function back_to_import_button($cm) {
        // Url https:// vbox.poodll.com/moodle/mod/minilesson/import.php?id=2081.
        $button = $this->output->single_button(new \moodle_url(
            constants::M_PATH . '/import.php',
            ['id' => $cm->id]
        ), get_string('backtoimport', constants::M_COMPONENT));

        $ret = \html_writer::div($button, constants::M_CLASS . '_backtoimport_cont');
        return $ret;
    }


    /**
     * reattempt button
     * @param object $moduleinstance
     * @return string
     */
    public function reattemptbutton($moduleinstance) {

        $button = $this->output->single_button(new \moodle_url(
            constants::M_URL . '/view.php',
            ['n' => $moduleinstance->id, 'retake' => 1]
        ), get_string('reattempt', constants::M_COMPONENT));

        $ret = \html_writer::div($button, constants::M_CLASS . '_afterattempt_cont');
        return $ret;
    }

    /**
     * show where to next
     * @param object $moduleinstance
     * @return string
     */
    public function show_wheretonext($moduleinstance) {
        $nextactivity = utils::fetch_next_activity($moduleinstance->activitylink);
        // Show activity link if we are up to it.
        if ($nextactivity->url) {
            $button = $this->output->single_button($nextactivity->url, $nextactivity->label);
            // Else lets show a back to top link.
        } else {
            $button = $this->output->single_button(new \moodle_url(
                constants::M_URL . '/view.php',
                ['n' => $moduleinstance->id]
            ), get_string('backtotop', constants::M_COMPONENT));
        }
        $ret = \html_writer::div($button, constants::M_WHERETONEXT_CONTAINER);
        return $ret;
    }



    /**
     * exceeded attempts message
     * @param object $moduleinstance
     * @return string
     */
    public function exceededattempts($moduleinstance) {
        $message = get_string("exceededattempts", constants::M_COMPONENT, $moduleinstance->maxattempts);
        $ret = \html_writer::div($message, constants::M_CLASS . '_afterattempt_cont');
        return $ret;
    }

    /**
     *  Show instructions/welcome
     * @param string $showtext
     * @param string $showtitle
     * @return string
     */
    public function show_welcome($showtext, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle, constants::M_CLASS . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($showtext, constants::M_CLASS . '_center');
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_INSTRUCTIONS_CONTAINER, ['id' => constants::M_INSTRUCTIONS_CONTAINER]);
        return $ret;
    }

    /**
     * Show the introduction text is as set in the activity description
     * @param object $minilesson
     * @param object $cm
     * @return string
     */
    public function show_intro($minilesson, $cm) {
        $ret = "";
        if (trim(strip_tags($minilesson->intro))) {
            $ret .= $this->output->box_start('mod_introbox');
            $ret .= format_module_intro('minilesson', $minilesson, $cm->id);
            $ret .= $this->output->box_end();
        }
        return $ret;
    }

    /**
     * Show error (but when?)
     * @param object $cm
     * @param bool $showadditemlinks
     * @return string
     */
    public function show_no_items($cm, $showadditemlinks) {
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('noitems', constants::M_COMPONENT), 3, 'main');
        if ($showadditemlinks) {
            $displaytext .= \html_writer::div(get_string('letsadditems', constants::M_COMPONENT), '', []);
            $displaytext .= $this->output->single_button(new \moodle_url(
                constants::M_URL . '/rsquestion/rsquestions.php',
                ['id' => $cm->id]
            ), get_string('additems', constants::M_COMPONENT));
        }
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_NOITEMS_MSG, ['id' => constants::M_NOITEMS_MSG]);
        return $ret;
    }

    /**
     *  Finished View
     * @param comprehensiontest $comptest
     * @param object $latestattempt
     * @param object $cm
     * @param bool $canattempt
     * @param int $embed
     * @param bool $teacherreport
     * @return string
     */
    public function show_finished_results($comptest, $latestattempt, $cm, $canattempt, $embed, $teacherreport = false) {
        global $CFG, $DB;
        $ans = [];
        // Quiz data.
        $quizdata = $comptest->fetch_test_data_for_js();

        // Config.
        $config = get_config(constants::M_COMPONENT);
        $course = $DB->get_record('course', ['id' => $latestattempt->courseid]);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Steps data.
        $steps = json_decode($latestattempt->sessiondata)->steps;

        // Prepare results for display.
        if (!is_array($steps)) {
            $steps = utils::remake_steps_as_array($steps);
        }
        $results = array_filter($steps, function ($step) {
            return $step->hasgrade;
        });
        $useresults = [];
        foreach ($results as $result) {
            // If the quiz items have been altered since the attempt, and the item does not exist, skip.
            // .. TO DO prevent adding/ removing items after an attempt has been made.
            if (!array_key_exists($result->index, $quizdata)) {
                continue;
            }

            $items = $DB->get_record(constants::M_QTABLE, ['id' => $quizdata[$result->index]->id]);
            $result->title = $items->name;

            // Question Text.
            $itemtext = file_rewrite_pluginfile_urls(
                $items->{constants::TEXTQUESTION},
                'pluginfile.php',
                $context->id,
                constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA,
                $items->id
            );
            $itemtext = format_text($itemtext, FORMAT_MOODLE, ['context' => $context]);

            // We need to replace within itemtext for these items too.
            $search = ['{topic}', '{ai data1}', '{ai data2}'];
            $replace = [];
            switch ($items->type) {
                case constants::TYPE_FREEWRITING:
                    $replace = [
                        $items->{constants::FREEWRITING_TOPIC},
                        $items->{constants::FREEWRITING_AIDATA1},
                        $items->{constants::FREEWRITING_AIDATA2},
                    ];
                    break;
                case constants::TYPE_FREESPEAKING:
                    $replace = [
                        $items->{constants::FREESPEAKING_TOPIC},
                        $items->{constants::FREESPEAKING_AIDATA1},
                        $items->{constants::FREESPEAKING_AIDATA2},
                    ];
                    break;
                case constants::TYPE_AUDIOCHAT:
                    $replace = [
                        $items->{constants::AUDIOCHAT_TOPIC},
                        $items->{constants::AUDIOCHAT_AIDATA1},
                        $items->{constants::AUDIOCHAT_AIDATA2},
                    ];
                    break;
            }
            if (!empty($replace)) {
                $itemtext = str_replace($search, $replace, $itemtext);
            }
            $result->questext = $itemtext;

            $result->itemtype = $quizdata[$result->index]->type;
            $result->resultstemplate = $result->itemtype . 'results';

            // Correct answer.
            switch ($result->itemtype) {
                case constants::TYPE_DICTATION:
                case constants::TYPE_DICTATIONCHAT:
                case constants::TYPE_LISTENREPEAT:
                case constants::TYPE_SPEECHCARDS:
                case constants::TYPE_SHORTANSWER:
                case constants::TYPE_LGAPFILL:
                case constants::TYPE_TGAPFILL:
                case constants::TYPE_SGAPFILL:
                    $result->hascorrectanswer = true;
                    $result->correctans = $quizdata[$result->index]->sentences;
                    $result->hasanswerdetails = false;
                    break;

                case constants::TYPE_MULTIAUDIO:
                case constants::TYPE_MULTICHOICE:
                case constants::TYPE_COMPQUIZ:
                    $result->hascorrectanswer = true;
                    $result->hasincorrectanswer = true;
                    if (!empty($quizdata[$result->index]->correctfeedback)) {
                        $result->hasanswerdetails = true;
                        $result->resultsdatajson = json_encode(['correctfeedback' => $quizdata[$result->index]->correctfeedback]);
                        $result->resultstemplate = 'multichoiceresults';
                    } else {
                        $result->hasanswerdetails = false;
                    }
                    $correctanswers = [];
                    $incorrectanswers = [];
                    $correctindex = $quizdata[$result->index]->correctanswer;

                    foreach ($quizdata[$result->index]->sentences as $sentance) {
                        if ($correctindex == $sentance->indexplusone) {
                            $correctanswers[] = $sentance->sentence;
                        } else {
                            $incorrectanswers[] = $sentance->sentence;
                        }
                    }

                    if (count($correctanswers) == 0) {
                        $result->hascorrectanswer = false;
                    }
                    if (count($incorrectanswers) == 0) {
                        $result->hasincorrectanswer = false;
                    }

                    $result->correctans = ['sentence' => join(' ', $correctanswers)];
                    $result->incorrectans = ['sentence' => join('<br> ', $incorrectanswers)];
                    break;

                case constants::TYPE_PGAPFILL:
                    $resultsdata = isset($result->resultsdata) ? $result->resultsdata : null;
                    $hasitems = $resultsdata && isset($resultsdata->items) && $resultsdata->items && $resultsdata->items > 0;
                    if (!$hasitems || !$resultsdata) {
                        $result->correctans = [];
                        $result->incorrectans = [];
                        break;
                    }
                    $result->hascorrectanswer = $hasitems;
                    $result->hasincorrectanswer = $hasitems;
                    $result->hasanswerdetails = false;
                    $resultsdata = isset($result->resultsdata) ? $result->resultsdata : null;
                    $correctanswers = [];
                    $incorrectanswers = [];

                    $items = $resultsdata->items;
                    for ($i = 0; $i < count($items); $i++) {
                        $theitem = $resultsdata->items[$i];
                        if (!$theitem) {
                            continue;
                        }
                        if ($theitem->correct) {
                            $correctanswers[] = ['sentence' => $theitem->text];
                        } else {
                            $incorrectanswers[] = ['sentence' => $theitem->text];
                        }
                    }
                    $result->correctans = $correctanswers;
                    $result->incorrectans = $incorrectanswers;
                    break;

                case constants::TYPE_H5P:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    $result->hasanswerdetails = false;
                    break;

                case constants::TYPE_PASSAGEREADING:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    if (
                        isset($result->resultsdata)
                        && isset($result->resultsdata->read)
                        && ($result->resultsdata->read + $result->resultsdata->unreached) > 0
                    ) {
                        $result->hasanswerdetails = true;
                        $result->resultstemplate = 'passagereadingreviewresults';
                        $result->resultsdata->passagehtml = \mod_minilesson\aitranscriptutils::render_passage(
                            $items->{constants::READINGPASSAGE}
                        );
                        $result->resultsdatajson = json_encode(
                            $result->resultsdata,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    } else {
                        $result->hasanswerdetails = false;
                    }
                    break;

                case constants::TYPE_FLUENCY:
                    $result->hascorrectanswer = true;
                    $result->correctans = $quizdata[$result->index]->sentences;
                    if (isset($result->resultsdata)) {
                        $result->hasanswerdetails = true;
                        $result->resultsdatajson = json_encode(
                            $result->resultsdata,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                        // For now ...
                        $result->resultstemplate = 'listitemresults';
                    } else {
                        $result->hasanswerdetails = false;
                    }
                    break;

                case constants::TYPE_FREEWRITING:
                case constants::TYPE_FREESPEAKING:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    if (isset($result->resultsdata)) {
                        $result->hasanswerdetails = true;
                        // The free writing and reading both need to be told to show no reattempt button.
                        $result->resultsdata->noreattempt = true;
                        $result->resultsdatajson = json_encode(
                            $result->resultsdata,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    } else {
                        $result->hasanswerdetails = false;
                    }
                    break;
                case constants::TYPE_AUDIOCHAT:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    if (isset($result->resultsdata)) {
                        $result->hasanswerdetails = true;
                        $result->resultsdatajson = json_encode(
                            $result->resultsdata,
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    } else {
                        $result->hasanswerdetails = false;
                    }
                    break;
                case constants::TYPE_WORDSHUFFLE:  // TO DO how to handle this?
                case constants::TYPE_SCATTER:  // TO DO how to handle this?
                case constants::TYPE_SPACEGAME: // TO DO how to handle this?
                default:
                    $result->hascorrectanswer = false;
                    $result->hasincorrectanswer = false;
                    $result->hasanswerdetails = false;
                    $result->correctans = [];
                    $result->incorrectans = [];
            }

            $result->index++;
            // Every item stars.
            if ($result->grade == 0) {
                $ystarcnt = 0;
            } else if ($result->grade < 19) {
                $ystarcnt = 1;
            } else if ($result->grade < 39) {
                $ystarcnt = 2;
            } else if ($result->grade < 59) {
                $ystarcnt = 3;
            } else if ($result->grade < 79) {
                $ystarcnt = 4;
            } else {
                $ystarcnt = 5;
            }
            $result->yellowstars = array_fill(0, $ystarcnt, true);
            $gstarcnt = 5 - $ystarcnt;
            $result->graystars = array_fill(0, $gstarcnt, true);

            $useresults[] = $result;
        }

        // Output results and back to course button.
        $tdata = new \stdClass();

        // Course name at top of page.
        $tdata->coursename = $course->fullname;
        // Item stars.
        if ($latestattempt->sessionscore == 0) {
            $ystarcnt = 0;
        } else if ($latestattempt->sessionscore < 19) {
            $ystarcnt = 1;
        } else if ($latestattempt->sessionscore < 39) {
            $ystarcnt = 2;
        } else if ($latestattempt->sessionscore < 59) {
            $ystarcnt = 3;
        } else if ($latestattempt->sessionscore < 79) {
            $ystarcnt = 4;
        } else {
            $ystarcnt = 5;
        }
        $tdata->yellowstars = array_fill(0, $ystarcnt, true);
        $gstarcnt = 5 - $ystarcnt;
        $tdata->graystars = array_fill(0, $gstarcnt, true);

        $tdata->total = $latestattempt->sessionscore;
        $tdata->courseurl = $CFG->wwwroot . '/course/view.php?id=' .
            $latestattempt->courseid . '#section-' . ($cm->section - 1);

        // Depending on finish screen settings and if its a teacher report.
        if ($teacherreport) {
            $tdata->teacherreport = true;
            $tdata->showfullresults = true;
            $tdata->results = $useresults;
        } else {
            switch ($moduleinstance->finishscreen) {
                case constants::FINISHSCREEN_FULL:
                case constants::FINISHSCREEN_CUSTOM:
                    $tdata->results = $useresults;
                    $tdata->showfullresults = true;
                    break;

                case constants::FINISHSCREEN_SIMPLE:
                default:
                    $tdata->results = [];
            }
        }

        // Output reattempt button.
        if ($canattempt && !$teacherreport) {
            $reattempturl = new \moodle_url(
                constants::M_URL . '/view.php',
                ['n' => $latestattempt->moduleid, 'retake' => 1, 'embed' => $embed]
            );
            $tdata->reattempturl = $reattempturl->out();
        }
        // Show back to course button if we are not in a tab or embedded.
        if (
            !$config->enablesetuptab && $embed == 0 && !$teacherreport &&
            $moduleinstance->pagelayout !== 'embedded' &&
            $moduleinstance->pagelayout !== 'popup'
        ) {
            $tdata->backtocourse = true;
        }

        if ($moduleinstance->finishscreen == constants::FINISHSCREEN_CUSTOM && !$teacherreport) {
            // Here we fetch the mustache engine, reset the loader to string loader
            // Render the custom finish screen, and restore the original loader.
            $mustache = $this->get_mustache();
            $oldloader = $mustache->getLoader();
            $mustache->setLoader(new \Mustache_Loader_StringLoader());
            $tpl = $mustache->loadTemplate($moduleinstance->finishscreencustom);
            $finishedcontents = $tpl->render($tdata);
            $mustache->setLoader($oldloader);
        } else {
            $finishedcontents = $this->render_from_template(constants::M_COMPONENT . '/quizfinished', $tdata);
        }

        // Put it all in a div and return it.
        $finisheddiv = \html_writer::div(
            $finishedcontents,
            constants::M_QUIZ_FINISHED,
            ['id' => constants::M_QUIZ_FINISHED, 'style' => 'display: block']
        );

        return $finisheddiv;
    }

    /**
     * Show quiz container
     * @param comprehensiontest $comptest
     * @param object $moduleinstance
     * @return string
     */
    public function show_quiz($comptest, $moduleinstance) {
        // Quiz data.
        $quizdata = $comptest->fetch_test_data_for_js();

        $itemshtml = [];
        foreach ($quizdata as $item) {
            $itemshtml[] = $this->render_from_template(
                constants::M_COMPONENT . '/' . $item->type,
                $item
            );
        }

        $finisheddiv = html_writer::div(
            "",
            constants::M_QUIZ_FINISHED,
            ['id' => constants::M_QUIZ_FINISHED]
        );

        $placeholderdiv = html_writer::div(
            '',
            constants::M_QUIZ_PLACEHOLDER . ' ' . constants::M_QUIZ_SKELETONBOX,
            ['id' => constants::M_QUIZ_PLACEHOLDER]
        );

        $quizclass = constants::M_QUIZ_CONTAINER . ' ' . $moduleinstance->csskey . '
            ' . constants::M_COMPONENT . '_' . $moduleinstance->containerwidth;
        $quizattributes = ['id' => constants::M_QUIZ_CONTAINER];
        if (!empty($moduleinstance->lessonfont)) {
            $quizattributes['style'] = "font-family: '$moduleinstance->lessonfont', serif;";
        }
        $quizdiv = html_writer::div($finisheddiv . implode('', $itemshtml), $quizclass, $quizattributes);

        $ret = $placeholderdiv . $quizdiv;
        return $ret;
    }

    /**
     * Show quiz container
     * @param comprehensiontest $comptest
     * @param int $qid
     * @return string
     */
    public function show_quiz_preview($comptest, $qid) {

        // Quiz data.
        $quizdata = $comptest->fetch_test_data_for_js();
        $itemshtml = [];
        foreach ($quizdata as $item) {
            if ($item->id == $qid) {
                $itemshtml[] = $this->render_from_template(constants::M_COMPONENT . '/' . $item->type, $item);
            }
        }

        $quizdiv = html_writer::div(
            implode('', $itemshtml),
            constants::M_QUIZ_CONTAINER,
            ['id' => constants::M_QUIZ_CONTAINER]
        );

        $ret = $quizdiv;
        return $ret;
    }

    /**
     * Show a progress circle overlay while uploading
     * @param object $minilesson
     * @param object $cm
     * @return string
     */
    public function show_progress($minilesson, $cm) {
        $hider = html_writer::div('', constants::M_HIDER, ['id' => constants::M_HIDER]);
        $message = html_writer::tag('h4', get_string('processing', constants::M_COMPONENT), []);
        $spinner = html_writer::tag('i', '', ['class' => 'fa fa-spinner fa-5x fa-spin']);
        $progressdiv = html_writer::div(
            $message . $spinner,
            constants::M_PROGRESS_CONTAINER,
            ['id' => constants::M_PROGRESS_CONTAINER]
        );
        $ret = $hider . $progressdiv;
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     * @param object $minilesson
     * @param string $showtitle
     * @return string
     */
    public function show_feedback($minilesson, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle, constants::M_CLASS . '_center');
        $displaytext .= $this->output->box_start();
        $displaytext .= \html_writer::div($minilesson->feedback, constants::M_CLASS . '_center');
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_FEEDBACK_CONTAINER, ['id' => constants::M_FEEDBACK_CONTAINER]);
        return $ret;
    }

    /**
     * Show the feedback set in the activity settings
     * @param object $minilesson
     * @param string $showtitle
     * @return string
     */
    public function show_title_postattempt($minilesson, $showtitle) {
        $thetitle = $this->output->heading($showtitle, 3, 'main');
        $displaytext = \html_writer::div($thetitle, constants::M_CLASS . '_center');
        $ret = \html_writer::div(
            $displaytext,
            constants::M_FEEDBACK_CONTAINER . ' ' . constants::M_POSTATTEMPT,
            ['id' => constants::M_FEEDBACK_CONTAINER]
        );
        return $ret;
    }

    /**
     * Show error (but when?)
     * @param object $minilesson
     * @param object $cm
     * @return string
     */
    public function show_error($minilesson, $cm) {
        $displaytext = $this->output->box_start();
        $displaytext .= $this->output->heading(get_string('errorheader', constants::M_COMPONENT), 3, 'main');
        $displaytext .= \html_writer::div('error message here', '', []);
        $displaytext .= $this->output->box_end();
        $ret = \html_writer::div($displaytext, constants::M_ERROR_CONTAINER, ['id' => constants::M_ERROR_CONTAINER]);
        return $ret;
    }


    /**
     * Fetches the activity html and sets up the javascript
     * @param comprehensiontest $comptest
     * @param stdClass $cm
     * @param stdClass $moduleinstance
     * @param int $previewquestionid
     * @param bool $canreattempt
     * @param int $embed
     * @return string
     */
    public function fetch_activity_amd($comptest, $cm, $moduleinstance, $previewquestionid = 0, $canreattempt = false, $embed = 0) {
        global $CFG, $USER;
        // Any html we want to return to be sent to the page.
        $rethtml = '';

        // Here we set up any info we need to pass into javascript.

        $recopts = [];
        // Recorder html ids.
        $recopts['recorderid'] = constants::M_RECORDERID;
        $recopts['recordingcontainer'] = constants::M_RECORDING_CONTAINER;
        $recopts['recordercontainer'] = constants::M_RECORDER_CONTAINER;

        // Activity html ids.
        $recopts['passagecontainer'] = constants::M_PASSAGE_CONTAINER;
        $recopts['instructionscontainer'] = constants::M_INSTRUCTIONS_CONTAINER;
        $recopts['recordbuttoncontainer'] = constants::M_RECORD_BUTTON_CONTAINER;
        $recopts['startbuttoncontainer'] = constants::M_START_BUTTON_CONTAINER;
        $recopts['hider'] = constants::M_HIDER;
        $recopts['progresscontainer'] = constants::M_PROGRESS_CONTAINER;
        $recopts['feedbackcontainer'] = constants::M_FEEDBACK_CONTAINER;
        $recopts['wheretonextcontainer'] = constants::M_WHERETONEXT_CONTAINER;
        $recopts['quizcontainer'] = constants::M_QUIZ_CONTAINER;
        $recopts['errorcontainer'] = constants::M_ERROR_CONTAINER;

        // First confirm we are authorised before we try to get the token.
        $config = get_config(constants::M_COMPONENT);
        if (empty($config->apiuser) || empty($config->apisecret)) {
            $errormessage = get_string(
                'nocredentials',
                constants::M_COMPONENT,
                $CFG->wwwroot . constants::M_PLUGINSETTINGS
            );
            return $this->show_problembox($errormessage);
        } else {
            // Fetch token.
            $token = utils::fetch_token($config->apiuser, $config->apisecret);

            // Check token authenticated and no errors in it.
            $errormessage = utils::fetch_token_error($token);
            if (!empty($errormessage)) {
                return $this->show_problembox($errormessage);
            }
        }
        $recopts['token'] = $token;
        $recopts['owner'] = hash('md5', $USER->username);
        $recopts['region'] = $moduleinstance->region;
        $recopts['cloudpoodllurl'] = utils::get_cloud_poodll_server();
        $recopts['ttslanguage'] = $moduleinstance->ttslanguage;
        $recopts['stt_guided'] = $moduleinstance->transcriber == constants::TRANSCRIBER_POODLL;

        $recopts['courseurl'] = $CFG->wwwroot . '/course/view.php?id=' .
            $moduleinstance->course . '#section-' . ($cm->section - 1);

        $recopts['useanimatecss'] = $config->animations == constants::M_ANIM_FANCY;

        // To show a post item results panel.
        $recopts['showitemreview'] = $moduleinstance->showitemreview ? true : false;

        // The activity URL for returning to on finished.
        $activityurl = new \moodle_url(
            constants::M_URL . '/view.php',
            ['n' => $moduleinstance->id]
        );

        // Add embedding url param if we are embedded.
        if ($embed > 0) {
            $activityurl->param('embed', $embed);
        }
        // Set the activity url.
        $recopts['activityurl'] = $activityurl->out();

        // The reattempturl if its ok.
        $recopts['reattempturl'] = "";
        if ($canreattempt) {
            $activityurl->param('retake', '1');
            $recopts['reattempturl'] = $activityurl->out();
        }

        // Show back to course button if we are not in an iframe.
        if (
            $config->enablesetuptab ||
            $moduleinstance->pagelayout == 'embedded' ||
            $moduleinstance->pagelayout == 'popup' ||
            $embed > 0
        ) {
            $recopts['backtocourse'] = '';
        } else {
            $recopts['backtocourse'] = true;
        }

        // Quiz data.
        $quizdata = $comptest->fetch_test_data_for_js($this);
        if ($previewquestionid) {
            foreach ($quizdata as $item) {
                if ($item->id == $previewquestionid) {
                    $item->preview = true;
                    $recopts['quizdata'] = [$item];
                    break;
                }
            }
        } else {
            $recopts['quizdata'] = $quizdata;
        }

        // This inits the M.mod_minilesson thingy, after the page has loaded.
        // we put the opts in html on the page because moodle/AMD doesn't like lots of opts in js
        // convert opts to json.
        $jsonstring = json_encode($recopts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (($jsonstring) === false) {
            $err = json_last_error();
        }
        $widgetid = constants::M_RECORDERID . '_opts_9999';
        $optshtml = \html_writer::tag('input', '', ['id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);

        // The recorder div.
        $rethtml = $rethtml . $optshtml;

        $opts = ['cmid' => $cm->id, 'widgetid' => $widgetid];
        $this->page->requires->js_call_amd("mod_minilesson/activitycontroller", 'init', [$opts]);

        // These need to be returned and echo'ed to the page.
        return $rethtml;
    }

    /**
     * Return HTML to embed a minilesson
     * @param int $cmid course module id
     * @param string $token api token
     * @return string HTML to embed the minilesson
     */
    public function embed_minilesson($cmid, $token) {
        global $DB;
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $cmid, 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
        $comptest = new comprehensiontest($cm);
        $previewid = 0;
        $embed = 1;
        $canattempt = true;
        $ret = $this->show_quiz($comptest, $moduleinstance);
        $ret .= $this->fetch_activity_amd($comptest, $cm, $moduleinstance, $previewid, $canattempt, $embed);
        return $ret;
    }

    /**
     * Return HTML to display message about problem
     * @param string $msg the message to display
     * @return string HTML to display the message
     */
    public function show_problembox($msg) {
        $output = '';
        $output .= $this->output->box_start(constants::M_COMPONENT . '_problembox');
        $output .= $this->notification($msg, 'warning');
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Show open and close dates
     * @param object $moduleinstance
     * @return string HTML to display the open and close dates
     */
    public function show_open_close_dates($moduleinstance) {
        $tdata = [];
        if ($moduleinstance->viewstart > 0) {
            $tdata['opendate'] = $moduleinstance->viewstart;
        }
        if ($moduleinstance->viewend > 0) {
            $tdata['closedate'] = $moduleinstance->viewend;
        }
        $ret = $this->output->render_from_template(constants::M_COMPONENT . '/openclosedates', $tdata);
        return $ret;
    }

    /**
     * AIGen buttons menu
     * @param object $cm course module object
     * @param string $tableuniqueid unique id for the table
     * @param array $filtertags tags to filter by
     * @return string HTML to display the menu
     */
    public function aigen_buttons_menu($cm, $tableuniqueid, $filtertags = []) {

        $withlabels = true;
        $tags = aigentemplates::get_alltags($withlabels);

        $renderable = new aigentemplates($cm, $filtertags);

        $templatecontext = [
            'tableuniqueid' => $tableuniqueid,
            'tags' => $tags,
            'aigentemplates' => $renderable->export_for_template($this),
        ];

        $ret = $this->output->render_from_template(constants::M_COMPONENT . '/aigenbuttonsmenu', $templatecontext);

        return $ret;
    }

    /**
     * AIGen complete view
     * @param object $cm course module object
     * @param int $doneitems number of completed items
     * @return string HTML to display the menu
     */
    public function aigen_complete($cm, $doneitems) {
        $ret = '';
        $ret .= $this->output->heading(get_string('aigenpage_done', constants::M_COMPONENT, $doneitems), 3, 'main');
        $thebutton = new \single_button(new \moodle_url(
            constants::M_URL . '/view.php',
            ['id' => $cm->id]
        ), get_string('aigenviewresult', constants::M_COMPONENT));
        $ret = $this->render($thebutton);
        return $ret;
    }

    /**
     * Push buttons menu
     * @param object $cm course module object
     * @param int $clonecount number of cloned activities
     * @param int $scope push scope constant
     * @return string HTML to display the menu
     */
    public function push_buttons_menu($cm, $clonecount, $scope) {
        $templateitems = [];
        $pushthings = [
            'maxattempts',
            'transcriber',
            'showitemreview',
            'region',
            'containerwidth',
            'csskey',
            'lessonfont',
            'finishscreen',
            'finishscreencustom',
        ];

        if ($scope == constants::PUSHMODE_MODULENAME) {
            $pushthings[] = 'pushitems';
        }

        foreach ($pushthings as $pushthing) {
            switch ($pushthing) {
                case 'transcriber':
                    $action = constants::M_PUSH_TRANSCRIBER;
                    break;
                case 'showitemreview':
                    $action = constants::M_PUSH_SHOWITEMREVIEW;
                    break;
                case 'maxattempts':
                    $action = constants::M_PUSH_MAXATTEMPTS;
                    break;
                case 'region':
                    $action = constants::M_PUSH_REGION;
                    break;
                case 'containerwidth':
                    $action = constants::M_PUSH_CONTAINERWIDTH;
                    break;
                case 'csskey':
                    $action = constants::M_PUSH_CSSKEY;
                    break;
                case 'lessonfont':
                    $action = constants::M_PUSH_LESSONFONT;
                    break;
                case 'finishscreen':
                    $action = constants::M_PUSH_FINISHSCREEN;
                    break;
                case 'finishscreencustom':
                    $action = constants::M_PUSH_FINISHSCREENCUSTOM;
                    break;
                case 'pushitems':
                    $action = constants::M_PUSH_ITEMS;
                    break;
            }
            $thepushbutton = new \single_button(new \moodle_url(
                constants::M_URL . '/push.php',
                ['id' => $cm->id, 'action' => $action, 'scope' => $scope]
            ), get_string('push', constants::M_COMPONENT));
            $thepushbutton->add_confirm_action(
                get_string('pushconfirm', constants::M_COMPONENT, ['pushthing' => $pushthing, 'clonecount' => $clonecount])
            );

            $templateitems[] = [
                'title' => get_string($pushthing, constants::M_COMPONENT),
                'description' => get_string($pushthing . '_details', constants::M_COMPONENT),
                'content' => $this->render($thepushbutton),
            ];
        }

        // Generate and return menu.
        $ret = $this->output->render_from_template(constants::M_COMPONENT . '/manybuttonsmenu', ['items' => $templateitems]);
        return $ret;
    }
}
