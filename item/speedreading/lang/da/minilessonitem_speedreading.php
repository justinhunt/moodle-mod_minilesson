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
 * Danish strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Hurtiglæsning';
$string['answercorrect'] = 'Rigtigt';
$string['answersneeded'] = 'Besvar alle spørgsmålene for at indsende. Du kan ændre et svar, indtil du indsender.';
$string['answerwrong'] = 'Forkert';
$string['cannotgoback'] = 'Du kan ikke gå tilbage til teksten.';
$string['charactercount'] = '{$a} tegn';
$string['charactersperminute'] = 'tegn i minuttet';
$string['correctansweris'] = 'Rigtigt svar:';
$string['defaultinstructions'] = 'Læs teksten så hurtigt, du kan. Svar derefter på spørgsmålene.';
$string['error:correctanswertoohigh'] = 'Nummeret på det rigtige svar er højere end antallet af svar.';
$string['error:needtwoanswers'] = 'Skriv mindst to svar, ét pr. linje.';
$string['error:passmark'] = 'Skriv et tal fra 0 til 100.';
$string['error:targetwpm'] = 'Skriv et tal større end 0.';
$string['error:wordcount'] = 'Skriv 0 eller mere. Lad feltet være tomt for at tælle automatisk.';
$string['finishedreading'] = 'Jeg er færdig med at læse';
$string['formexplainer'] = 'Eleverne ser fire skærmbilleder. Tiden starter, når de trykker på »Start læsning«, og stopper, når de trykker på »Jeg er færdig med at læse«. De kan ikke gå tilbage til teksten. Hvert afsnit nedenfor opsætter ét skærmbillede.';
$string['formexplainer_heading'] = 'Sådan fungerer dette element';
$string['formexplainer_step1'] = 'Titel, instruktioner, tips og et valgfrit billede. Eleverne trykker på »Start læsning«.';
$string['formexplainer_step2'] = 'Teksten og tiden. Færdig-knappen står i slutningen af teksten.';
$string['formexplainer_step3'] = 'Op til 5 flervalgsspørgsmål. Eleverne ser ikke, om de har svaret rigtigt, før resultaterne.';
$string['formexplainer_step4'] = 'Læsehastighed, hastighedspoint, quizscore, svarene med feedback og teksten.';
$string['gradeitem'] = 'Giv karakter for dette element';
$string['gradeitem_details'] = 'Karakteren er quizscoren. Læsehastigheden vises for eleven, men indgår ikke i karakteren.';
$string['instructions'] = 'Instruktioner';
$string['intronote'] = 'Tiden starter, når du trykker på »Start læsning«. Du kan ikke gå tilbage til teksten, når du er færdig.';
$string['introtext'] = 'Introduktionstekst';
$string['introtext_details'] = 'Instruktionerne er én kort linje under titlen. Introduktionsteksten kan indeholde tips, og billedet er valgfrit. En note om, at tiden starter, når eleven trykker på »Start læsning«, og at eleven ikke kan gå tilbage til teksten, tilføjes automatisk.';
$string['item_desc'] = 'Øvelse i hurtiglæsning. Eleven læser en tekst, mens tiden går, og svarer derefter på spørgsmål om den. Eleven ser sin læsehastighed i ord i minuttet.';
$string['minsec'] = '{$a->minutes} min. {$a->seconds} sek.';
$string['noanswer'] = 'Ikke besvaret';
$string['notread'] = 'Du blev ikke færdig med at læse, så der er ingen læsehastighed.';
$string['passage'] = 'Tekst';
$string['passage_details'] = 'Vælg en let tekst: eleverne bør kende næsten alle ordene. Hurtiglæsning øver i at læse hurtigt, ikke i at lære nye ord.';
$string['passagestatus'] = '{$a->count} ord. Med målhastigheden {$a->target} ord i minuttet tager det cirka {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} tegn. Med målhastigheden {$a->target} tegn i minuttet tager det cirka {$a->time}.';
$string['passmark'] = 'Grænse for at hastigheden tæller';
$string['passmark_details'] = 'Læsehastigheden tæller kun, når mindst denne procentdel af spørgsmålene er besvaret rigtigt.';
$string['passmarkstatus'] = 'Med {$a->questions} spørgsmål skal eleverne have {$a->needed} rigtige.';
$string['picture'] = 'Billede';
$string['pluginname'] = 'Hurtiglæsning';
$string['questionanswers'] = 'Svar (ét pr. linje)';
$string['questioncount'] = '{$a} spørgsmål';
$string['questionfeedback'] = 'Feedback (valgfrit)';
$string['questionno'] = 'Spørgsmål {$a}';
$string['questionofn'] = 'Spørgsmål {$a->number} af {$a->total}';
$string['questions'] = 'Spørgsmål';
$string['questions_details'] = 'Udfyld 1 til 5 spørgsmål. Eleverne ser ikke, om de har svaret rigtigt, før resultatskærmen. Feedback vises kun på resultatskærmen.';
$string['questiontext'] = 'Spørgsmålstekst';
$string['readingspeed'] = 'Din læsehastighed';
$string['readtime'] = '{$a->count} ord på {$a->minutes} min. {$a->seconds} sek.';
$string['readtime_cpm'] = '{$a->count} tegn på {$a->minutes} min. {$a->seconds} sek.';
$string['screen1'] = 'Skærmbillede 1: Introduktion';
$string['screen2'] = 'Skærmbillede 2: Læsetekst';
$string['screen3'] = 'Skærmbillede 3: Quiz';
$string['screen4'] = 'Skærmbillede 4: Resultater og karakter';
$string['showallquestions'] = 'Vis alle spørgsmål på én gang';
$string['showallquestions_details'] = 'Fra: ét spørgsmål ad gangen, og et tryk går videre. Til: alle spørgsmål på én side med en knap til at indsende.';
$string['showtimer'] = 'Vis tiden under læsningen';
$string['showtimer_details'] = 'Tiden registreres under alle omstændigheder. Skjul tiden, hvis den stresser eleverne.';
$string['speedcounts'] = 'Din hastighed tæller. Du skulle have {$a->needed} af {$a->total} rigtige, og du fik {$a->correct}.';
$string['speeddoesnotcount'] = 'Din hastighed tæller ikke denne gang. Du skulle have {$a->needed} af {$a->total} rigtige, og du fik {$a->correct}. Læs lidt langsommere næste gang, så du forstår mere.';
$string['speedpoints'] = 'Hastighedspoint';
$string['speedreading'] = 'Hurtiglæsning';
$string['startreading'] = 'Start læsning';
$string['submitanswers'] = 'Indsend svar';
$string['tapnext'] = 'Tryk på et svar for at gå til næste spørgsmål. Du ser dine resultater til sidst.';
$string['targetis'] = 'Mål: {$a}';
$string['targetwpm'] = 'Målhastighed';
$string['targetwpm_details'] = 'Denne hastighed eller hurtigere giver 10 hastighedspoint. Halvdelen af denne hastighed eller langsommere giver 0.';
$string['thepassage'] = 'Teksten';
$string['timerrunning'] = 'Tiden går';
$string['toofast'] = 'Det var for hurtigt til at være rigtig læsning, så din hastighed tæller ikke. Læs hele teksten næste gang.';
$string['wordcount'] = 'Antal ord';
$string['wordcount_details'] = 'Lad feltet være tomt for at tælle ordene automatisk. Udfyld det for at bruge et bestemt antal ord.<br>NB: For japansk, kinesisk, koreansk og thai tæller vi tegn (ikke ord).';
$string['wordcountshort'] = '{$a} ord';
$string['wordsperminute'] = 'ord i minuttet';
$string['youranswer'] = 'Dit svar:';
$string['youranswers'] = 'Dine svar';
