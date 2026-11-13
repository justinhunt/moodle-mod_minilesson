<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language pack for Speed Reading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Speed Reading';
$string['answercorrect'] = 'Correct';
$string['answersneeded'] = 'Answer all the questions to submit. You can change an answer until you submit.';
$string['answerwrong'] = 'Wrong';
$string['cannotgoback'] = 'You cannot come back to the passage.';
$string['charactercount'] = '{$a} characters';
$string['charactersperminute'] = 'characters per minute';
$string['correctansweris'] = 'Correct answer:';
$string['defaultinstructions'] = 'Read the passage as fast as you can. Then answer the questions.';
$string['error:correctanswertoohigh'] = 'The correct answer number is higher than the number of answers.';
$string['error:needtwoanswers'] = 'Enter at least two answers, one per line.';
$string['error:passmark'] = 'Enter a number from 0 to 100.';
$string['error:targetwpm'] = 'Enter a number greater than 0.';
$string['error:wordcount'] = 'Enter 0 or more. Leave empty to count automatically.';
$string['finishedreading'] = 'I have finished reading';
$string['formexplainer'] = 'Students see four screens. The timer starts when they tap "Start reading" and stops when they tap "I have finished reading". They cannot go back to the passage. Each section below sets up one screen.';
$string['formexplainer_heading'] = 'How this item works';
$string['formexplainer_step1'] = 'The title, instructions, tips and an optional picture. Students tap "Start reading".';
$string['formexplainer_step2'] = 'The passage and the timer. The finish button is at the end of the passage.';
$string['formexplainer_step3'] = 'Up to 5 multichoice questions. Students do not see if they are right until the results.';
$string['formexplainer_step4'] = 'Reading speed, speed points, question score, answers with feedback, and the passage.';
$string['gradeitem'] = 'Grade this item';
$string['gradeitem_details'] = 'The grade is the question score. The reading speed is shown to the student, but it is not part of the grade.';
$string['instructions'] = 'Instructions';
$string['intronote'] = 'The timer starts when you tap "Start reading". You cannot go back to the passage after you finish.';
$string['introtext'] = 'Introduction text';
$string['introtext_details'] = 'The instructions are one short line under the title. The introduction text holds optional tips, and the picture is optional. A note that the timer starts when the student taps "Start reading", and that they cannot go back to the passage, is added for you.';
$string['item_desc'] = 'Speed reading practice. The student reads a passage while a timer runs, then answers questions about it. They see their reading speed in words per minute.';
$string['minsec'] = '{$a->minutes} min {$a->seconds} sec';
$string['noanswer'] = 'No answer';
$string['notread'] = 'You did not finish reading, so there is no reading speed.';
$string['passage'] = 'Passage';
$string['passage_details'] = 'Choose an easy text: students should know almost all the words. Speed reading practises reading fast, not learning new words.';
$string['passagestatus'] = '{$a->count} words. At the target speed of {$a->target} words per minute this takes about {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} characters. At the target speed of {$a->target} characters per minute this takes about {$a->time}.';
$string['passmark'] = 'Pass mark for speed';
$string['passmark_details'] = 'The reading speed only counts when at least this percentage of the questions are right.';
$string['passmarkstatus'] = 'With {$a->questions} questions, students need {$a->needed} correct.';
$string['picture'] = 'Picture';
$string['pluginname'] = 'Speed Reading';
$string['privacy:metadata'] = 'The Speed Reading plugin doesn\'t store any personal data.';
$string['questionanswers'] = 'Answers (one per line)';
$string['questioncount'] = '{$a} questions';
$string['questionfeedback'] = 'Feedback (optional)';
$string['questionno'] = 'Question {$a}';
$string['questionofn'] = 'Question {$a->number} of {$a->total}';
$string['questions'] = 'Questions';
$string['questions_details'] = 'Fill in 1 to 5 questions. Students do not see if they are right until the results screen. Feedback is shown on the results screen only.';
$string['questiontext'] = 'Question text';
$string['readingspeed'] = 'Your reading speed';
$string['readtime'] = '{$a->count} words in {$a->minutes} min {$a->seconds} sec';
$string['readtime_cpm'] = '{$a->count} characters in {$a->minutes} min {$a->seconds} sec';
$string['screen1'] = 'Screen 1: Introduction';
$string['screen2'] = 'Screen 2: Reading passage';
$string['screen3'] = 'Screen 3: Questions';
$string['screen4'] = 'Screen 4: Results and grading';
$string['showallquestions'] = 'Show all questions at once';
$string['showallquestions_details'] = 'Off: one question at a time, and a tap moves on. On: all the questions on one page, with a Submit button.';
$string['showtimer'] = 'Show timer while reading';
$string['showtimer_details'] = 'The time is recorded either way. Hide the timer if it makes students anxious.';
$string['speedcounts'] = 'Your speed counts. You needed {$a->needed} of {$a->total} correct and you got {$a->correct}.';
$string['speeddoesnotcount'] = 'Your speed does not count this time. You needed {$a->needed} of {$a->total} correct and you got {$a->correct}. Read a little more slowly next time, so you understand more.';
$string['speedpoints'] = 'Speed points';
$string['speedreading'] = 'Speed reading';
$string['startreading'] = 'Start reading';
$string['submitanswers'] = 'Submit answers';
$string['tapnext'] = 'Tap an answer to go to the next question. You will see your results at the end.';
$string['targetis'] = 'Target: {$a}';
$string['targetwpm'] = 'Target speed';
$string['targetwpm_details'] = 'This speed or faster gets 10 speed points. Half this speed or slower gets 0.';
$string['thepassage'] = 'The passage';
$string['timerrunning'] = 'Timer running';
$string['toofast'] = 'That was too fast to be real reading, so your speed does not count. Read the whole passage next time.';
$string['wordcount'] = 'Word count';
$string['wordcount_details'] = 'Leave empty to count the words automatically. Fill it in to match a textbook\'s word count. For Japanese, Chinese and Thai, characters are counted, not words.';
$string['wordcountshort'] = '{$a} words';
$string['wordsperminute'] = 'words per minute';
$string['youranswer'] = 'Your answer:';
$string['youranswers'] = 'Your answers';
