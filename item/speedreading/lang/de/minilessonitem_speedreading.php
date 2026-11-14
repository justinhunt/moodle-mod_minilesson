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

/**
 * German strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Schnelllesen';
$string['answercorrect'] = 'Richtig';
$string['answersneeded'] = 'Beantworte alle Fragen, um abzugeben. Bis zur Abgabe kannst du deine Antworten noch ändern.';
$string['answerwrong'] = 'Falsch';
$string['cannotgoback'] = 'Du kannst nicht zum Text zurück.';
$string['charactercount'] = '{$a} Zeichen';
$string['charactersperminute'] = 'Zeichen pro Minute';
$string['correctansweris'] = 'Richtige Antwort:';
$string['defaultinstructions'] = 'Lies den Text so schnell du kannst. Beantworte dann die Fragen.';
$string['error:correctanswertoohigh'] = 'Die Nummer der richtigen Antwort ist größer als die Anzahl der Antworten.';
$string['error:needtwoanswers'] = 'Gib mindestens zwei Antworten ein, eine pro Zeile.';
$string['error:passmark'] = 'Gib eine Zahl von 0 bis 100 ein.';
$string['error:targetwpm'] = 'Gib eine Zahl größer als 0 ein.';
$string['error:wordcount'] = 'Gib 0 oder mehr ein. Lass das Feld leer, um automatisch zu zählen.';
$string['finishedreading'] = 'Ich bin fertig mit Lesen';
$string['formexplainer'] = 'Die Lernenden sehen vier Bildschirme. Die Zeit startet, wenn sie auf „Lesen starten“ tippen, und stoppt, wenn sie auf „Ich bin fertig mit Lesen“ tippen. Sie können nicht zum Text zurück. Jeder Abschnitt unten richtet einen Bildschirm ein.';
$string['formexplainer_heading'] = 'So funktioniert dieses Element';
$string['formexplainer_step1'] = 'Titel, Anweisungen, Tipps und ein optionales Bild. Die Lernenden tippen auf „Lesen starten“.';
$string['formexplainer_step2'] = 'Der Text und die Zeitmessung. Die Fertig-Schaltfläche steht am Ende des Textes.';
$string['formexplainer_step3'] = 'Bis zu 5 Multiple-Choice-Fragen. Die Lernenden sehen erst bei den Ergebnissen, ob sie richtig liegen.';
$string['formexplainer_step4'] = 'Lesetempo, Tempopunkte, Quiz-Punktzahl, die Antworten mit Feedback und der Text.';
$string['gradeitem'] = 'Dieses Element bewerten';
$string['gradeitem_details'] = 'Die Bewertung ist die Quiz-Punktzahl. Das Lesetempo wird angezeigt, zählt aber nicht zur Bewertung.';
$string['instructions'] = 'Anweisungen';
$string['intronote'] = 'Die Zeit startet, wenn du auf „Lesen starten“ tippst. Danach kannst du nicht mehr zum Text zurück.';
$string['introtext'] = 'Einleitungstext';
$string['introtext_details'] = 'Die Anweisungen sind eine kurze Zeile unter dem Titel. Der Einleitungstext kann Tipps enthalten, das Bild ist optional. Ein Hinweis, dass die Zeit startet, wenn die Lernenden auf „Lesen starten“ tippen, und dass sie nicht zum Text zurückkönnen, wird automatisch hinzugefügt.';
$string['item_desc'] = 'Übung im Schnelllesen. Die Lernenden lesen einen Text, während die Zeit läuft, und beantworten dann Fragen dazu. Sie sehen ihr Lesetempo in Wörtern pro Minute.';
$string['minsec'] = '{$a->minutes} Min. {$a->seconds} Sek.';
$string['noanswer'] = 'Keine Antwort';
$string['notread'] = 'Du hast nicht fertig gelesen, deshalb gibt es kein Lesetempo.';
$string['passage'] = 'Text';
$string['passage_details'] = 'Wähle einen leichten Text: Die Lernenden sollten fast alle Wörter kennen. Schnelllesen übt schnelles Lesen, nicht das Lernen neuer Wörter.';
$string['passagestatus'] = '{$a->count} Wörter. Beim Zieltempo von {$a->target} Wörtern pro Minute dauert das etwa {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} Zeichen. Beim Zieltempo von {$a->target} Zeichen pro Minute dauert das etwa {$a->time}.';
$string['passmark'] = 'Mindestwert für das Tempo';
$string['passmark_details'] = 'Das Lesetempo zählt nur, wenn mindestens dieser Prozentsatz der Fragen richtig ist.';
$string['passmarkstatus'] = 'Bei {$a->questions} Fragen brauchen die Lernenden {$a->needed} richtige.';
$string['picture'] = 'Bild';
$string['pluginname'] = 'Schnelllesen';
$string['questionanswers'] = 'Antworten (eine pro Zeile)';
$string['questioncount'] = '{$a} Fragen';
$string['questionfeedback'] = 'Feedback (optional)';
$string['questionno'] = 'Frage {$a}';
$string['questionofn'] = 'Frage {$a->number} von {$a->total}';
$string['questions'] = 'Fragen';
$string['questions_details'] = 'Fülle 1 bis 5 Fragen aus. Die Lernenden sehen erst auf dem Ergebnisbildschirm, ob sie richtig liegen. Feedback erscheint nur auf dem Ergebnisbildschirm.';
$string['questiontext'] = 'Fragetext';
$string['readingspeed'] = 'Dein Lesetempo';
$string['readtime'] = '{$a->count} Wörter in {$a->minutes} Min. {$a->seconds} Sek.';
$string['readtime_cpm'] = '{$a->count} Zeichen in {$a->minutes} Min. {$a->seconds} Sek.';
$string['screen1'] = 'Bildschirm 1: Einleitung';
$string['screen2'] = 'Bildschirm 2: Lesetext';
$string['screen3'] = 'Bildschirm 3: Quiz';
$string['screen4'] = 'Bildschirm 4: Ergebnisse und Bewertung';
$string['showallquestions'] = 'Alle Fragen auf einmal anzeigen';
$string['showallquestions_details'] = 'Aus: eine Frage nach der anderen, ein Tippen geht weiter. An: alle Fragen auf einer Seite, mit einer Schaltfläche zum Abgeben.';
$string['showtimer'] = 'Timer beim Lesen anzeigen';
$string['showtimer_details'] = 'Die Zeit wird in jedem Fall erfasst. Blende den Timer aus, wenn er die Lernenden nervös macht.';
$string['speedcounts'] = 'Dein Tempo zählt. Du brauchtest {$a->needed} von {$a->total} richtig und hattest {$a->correct}.';
$string['speeddoesnotcount'] = 'Dein Tempo zählt diesmal nicht. Du brauchtest {$a->needed} von {$a->total} richtig und hattest {$a->correct}. Lies nächstes Mal etwas langsamer, damit du mehr verstehst.';
$string['speedpoints'] = 'Tempopunkte';
$string['speedreading'] = 'Schnelllesen';
$string['startreading'] = 'Lesen starten';
$string['submitanswers'] = 'Antworten abgeben';
$string['tapnext'] = 'Tippe auf eine Antwort, um zur nächsten Frage zu gehen. Deine Ergebnisse siehst du am Ende.';
$string['targetis'] = 'Ziel: {$a}';
$string['targetwpm'] = 'Zieltempo';
$string['targetwpm_details'] = 'Dieses Tempo oder schneller gibt 10 Tempopunkte. Die Hälfte dieses Tempos oder langsamer gibt 0.';
$string['thepassage'] = 'Der Text';
$string['timerrunning'] = 'Die Zeit läuft';
$string['toofast'] = 'Das war zu schnell für echtes Lesen, deshalb zählt dein Tempo nicht. Lies nächstes Mal den ganzen Text.';
$string['wordcount'] = 'Wortanzahl';
$string['wordcount_details'] = 'Leer lassen, um die Wörter automatisch zu zählen. Ausfüllen, um eine bestimmte Wortanzahl festzulegen.<br>Hinweis: Bei Japanisch, Chinesisch, Koreanisch und Thai zählen wir Zeichen (keine Wörter).';
$string['wordcountshort'] = '{$a} Wörter';
$string['wordsperminute'] = 'Wörter pro Minute';
$string['youranswer'] = 'Deine Antwort:';
$string['youranswers'] = 'Deine Antworten';
