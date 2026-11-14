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
 * Norwegian strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Hurtiglesing';
$string['answercorrect'] = 'Riktig';
$string['answersneeded'] = 'Svar på alle spørsmålene for å sende inn. Du kan endre et svar helt til du sender inn.';
$string['answerwrong'] = 'Feil';
$string['cannotgoback'] = 'Du kan ikke gå tilbake til teksten.';
$string['charactercount'] = '{$a} tegn';
$string['charactersperminute'] = 'tegn per minutt';
$string['correctansweris'] = 'Riktig svar:';
$string['defaultinstructions'] = 'Les teksten så fort du kan. Svar så på spørsmålene.';
$string['error:correctanswertoohigh'] = 'Nummeret på riktig svar er høyere enn antall svar.';
$string['error:needtwoanswers'] = 'Skriv inn minst to svar, ett per linje.';
$string['error:passmark'] = 'Skriv inn et tall fra 0 til 100.';
$string['error:targetwpm'] = 'Skriv inn et tall større enn 0.';
$string['error:wordcount'] = 'Skriv inn 0 eller mer. La feltet stå tomt for å telle automatisk.';
$string['finishedreading'] = 'Jeg er ferdig med å lese';
$string['formexplainer'] = 'Elevene ser fire skjermbilder. Tidtakingen starter når de trykker på «Start lesing», og stopper når de trykker på «Jeg er ferdig med å lese». De kan ikke gå tilbake til teksten. Hver del nedenfor setter opp ett skjermbilde.';
$string['formexplainer_heading'] = 'Slik fungerer dette elementet';
$string['formexplainer_step1'] = 'Tittel, instruksjoner, tips og et valgfritt bilde. Elevene trykker på «Start lesing».';
$string['formexplainer_step2'] = 'Teksten og tidtakingen. Ferdig-knappen står på slutten av teksten.';
$string['formexplainer_step3'] = 'Opptil 5 flervalgsspørsmål. Elevene ser ikke om de har svart riktig før resultatene.';
$string['formexplainer_step4'] = 'Lesefart, fartspoeng, poengsum på testen, svarene med tilbakemelding og teksten.';
$string['gradeitem'] = 'Gi karakter for dette elementet';
$string['gradeitem_details'] = 'Karakteren er poengsummen på testen. Lesefarten vises for eleven, men er ikke en del av karakteren.';
$string['instructions'] = 'Instruksjoner';
$string['intronote'] = 'Tidtakingen starter når du trykker på «Start lesing». Du kan ikke gå tilbake til teksten når du er ferdig.';
$string['introtext'] = 'Introduksjonstekst';
$string['introtext_details'] = 'Instruksjonene er én kort linje under tittelen. Introduksjonsteksten kan inneholde tips, og bildet er valgfritt. En merknad om at tidtakingen starter når eleven trykker på «Start lesing», og at eleven ikke kan gå tilbake til teksten, legges til automatisk.';
$string['item_desc'] = 'Øving i hurtiglesing. Eleven leser en tekst mens tiden går, og svarer deretter på spørsmål om den. Eleven ser lesefarten sin i ord per minutt.';
$string['minsec'] = '{$a->minutes} min {$a->seconds} sek';
$string['noanswer'] = 'Ikke besvart';
$string['notread'] = 'Du ble ikke ferdig med å lese, så det er ingen lesefart.';
$string['passage'] = 'Tekst';
$string['passage_details'] = 'Velg en lett tekst: elevene bør kunne nesten alle ordene. Hurtiglesing øver på å lese fort, ikke på å lære nye ord.';
$string['passagestatus'] = '{$a->count} ord. Med målfarten {$a->target} ord per minutt tar dette omtrent {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} tegn. Med målfarten {$a->target} tegn per minutt tar dette omtrent {$a->time}.';
$string['passmark'] = 'Grense for at farten teller';
$string['passmark_details'] = 'Lesefarten teller bare når minst denne prosentandelen av spørsmålene er riktig besvart.';
$string['passmarkstatus'] = 'Med {$a->questions} spørsmål må elevene ha {$a->needed} riktige.';
$string['picture'] = 'Bilde';
$string['pluginname'] = 'Hurtiglesing';
$string['questionanswers'] = 'Svar (ett per linje)';
$string['questioncount'] = '{$a} spørsmål';
$string['questionfeedback'] = 'Tilbakemelding (valgfritt)';
$string['questionno'] = 'Spørsmål {$a}';
$string['questionofn'] = 'Spørsmål {$a->number} av {$a->total}';
$string['questions'] = 'Spørsmål';
$string['questions_details'] = 'Fyll inn 1 til 5 spørsmål. Elevene ser ikke om de har svart riktig før resultatskjermen. Tilbakemeldingen vises bare på resultatskjermen.';
$string['questiontext'] = 'Spørsmålstekst';
$string['readingspeed'] = 'Lesefarten din';
$string['readtime'] = '{$a->count} ord på {$a->minutes} min {$a->seconds} sek';
$string['readtime_cpm'] = '{$a->count} tegn på {$a->minutes} min {$a->seconds} sek';
$string['screen1'] = 'Skjermbilde 1: Introduksjon';
$string['screen2'] = 'Skjermbilde 2: Lesetekst';
$string['screen3'] = 'Skjermbilde 3: Test';
$string['screen4'] = 'Skjermbilde 4: Resultater og karakter';
$string['showallquestions'] = 'Vis alle spørsmål samtidig';
$string['showallquestions_details'] = 'Av: ett spørsmål om gangen, og et trykk går videre. På: alle spørsmålene på én side, med en knapp for å sende inn.';
$string['showtimer'] = 'Vis tidtakeren under lesingen';
$string['showtimer_details'] = 'Tiden registreres uansett. Skjul tidtakeren hvis den gjør elevene stresset.';
$string['speedcounts'] = 'Farten din teller. Du trengte {$a->needed} av {$a->total} riktige og fikk {$a->correct}.';
$string['speeddoesnotcount'] = 'Farten din teller ikke denne gangen. Du trengte {$a->needed} av {$a->total} riktige og fikk {$a->correct}. Les litt saktere neste gang, så du forstår mer.';
$string['speedpoints'] = 'Fartspoeng';
$string['speedreading'] = 'Hurtiglesing';
$string['startreading'] = 'Start lesing';
$string['submitanswers'] = 'Send inn svarene';
$string['tapnext'] = 'Trykk på et svar for å gå til neste spørsmål. Du ser resultatene til slutt.';
$string['targetis'] = 'Mål: {$a}';
$string['targetwpm'] = 'Målfart';
$string['targetwpm_details'] = 'Denne farten eller raskere gir 10 fartspoeng. Halvparten av denne farten eller saktere gir 0.';
$string['thepassage'] = 'Teksten';
$string['timerrunning'] = 'Tidtakingen går';
$string['toofast'] = 'Det var for fort til å være ekte lesing, så farten din teller ikke. Les hele teksten neste gang.';
$string['wordcount'] = 'Antall ord';
$string['wordcount_details'] = 'La feltet stå tomt for å telle ordene automatisk. Fyll det inn for å bruke et bestemt antall ord.<br>NB! For japansk, kinesisk, koreansk og thai teller vi tegn (ikke ord).';
$string['wordcountshort'] = '{$a} ord';
$string['wordsperminute'] = 'ord per minutt';
$string['youranswer'] = 'Ditt svar:';
$string['youranswers'] = 'Dine svar';
