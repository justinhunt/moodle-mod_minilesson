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
 * Finnish strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Pikaluku';
$string['answercorrect'] = 'Oikein';
$string['answersneeded'] = 'Vastaa kaikkiin kysymyksiin, niin voit lähettää vastaukset. Voit muuttaa vastauksia lähettämiseen asti.';
$string['answerwrong'] = 'Väärin';
$string['cannotgoback'] = 'Et voi palata tekstiin.';
$string['charactercount'] = '{$a} merkkiä';
$string['charactersperminute'] = 'merkkiä minuutissa';
$string['correctansweris'] = 'Oikea vastaus:';
$string['defaultinstructions'] = 'Lue teksti niin nopeasti kuin pystyt. Vastaa sitten kysymyksiin.';
$string['error:correctanswertoohigh'] = 'Oikean vastauksen numero on suurempi kuin vastausten määrä.';
$string['error:needtwoanswers'] = 'Kirjoita vähintään kaksi vastausta, yksi riville.';
$string['error:passmark'] = 'Anna luku väliltä 0–100.';
$string['error:targetwpm'] = 'Anna nollaa suurempi luku.';
$string['error:wordcount'] = 'Anna 0 tai enemmän. Jätä tyhjäksi, niin määrä lasketaan automaattisesti.';
$string['finishedreading'] = 'Olen lukenut tekstin';
$string['formexplainer'] = 'Opiskelijat näkevät neljä näkymää. Ajanotto alkaa, kun he napauttavat ”Aloita lukeminen”, ja pysähtyy, kun he napauttavat ”Olen lukenut tekstin”. He eivät voi palata tekstiin. Kukin alla oleva osio määrittää yhden näkymän.';
$string['formexplainer_heading'] = 'Näin tämä osio toimii';
$string['formexplainer_step1'] = 'Otsikko, ohjeet, vinkit ja valinnainen kuva. Opiskelijat napauttavat ”Aloita lukeminen”.';
$string['formexplainer_step2'] = 'Teksti ja ajanotto. Valmis-painike on tekstin lopussa.';
$string['formexplainer_step3'] = 'Enintään 5 monivalintakysymystä. Opiskelijat näkevät vasta tuloksista, menivätkö vastaukset oikein.';
$string['formexplainer_step4'] = 'Lukunopeus, nopeuspisteet, testin pisteet, vastaukset palautteineen ja teksti.';
$string['gradeitem'] = 'Arvioi tämä osio';
$string['gradeitem_details'] = 'Arvosana on testin pistemäärä. Lukunopeus näytetään opiskelijalle, mutta se ei vaikuta arvosanaan.';
$string['instructions'] = 'Ohjeet';
$string['intronote'] = 'Ajanotto alkaa, kun napautat ”Aloita lukeminen”. Et voi palata tekstiin, kun olet lopettanut.';
$string['introtext'] = 'Johdantoteksti';
$string['introtext_details'] = 'Ohjeet ovat yksi lyhyt rivi otsikon alla. Johdantotekstiin voi kirjoittaa vinkkejä, ja kuva on valinnainen. Huomautus siitä, että ajanotto alkaa, kun opiskelija napauttaa ”Aloita lukeminen”, eikä tekstiin voi palata, lisätään automaattisesti.';
$string['item_desc'] = 'Pikaluvun harjoitus. Opiskelija lukee tekstin ajanoton käydessä ja vastaa sitten siitä kysymyksiin. Hän näkee lukunopeutensa sanoina minuutissa.';
$string['minsec'] = '{$a->minutes} min {$a->seconds} s';
$string['noanswer'] = 'Ei vastausta';
$string['notread'] = 'Et lukenut tekstiä loppuun, joten lukunopeutta ei ole.';
$string['passage'] = 'Teksti';
$string['passage_details'] = 'Valitse helppo teksti: opiskelijoiden pitäisi tuntea lähes kaikki sanat. Pikaluvussa harjoitellaan nopeaa lukemista, ei uusien sanojen oppimista.';
$string['passagestatus'] = '{$a->count} sanaa. Tavoitenopeudella {$a->target} sanaa minuutissa tähän menee noin {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} merkkiä. Tavoitenopeudella {$a->target} merkkiä minuutissa tähän menee noin {$a->time}.';
$string['passmark'] = 'Nopeuden hyväksymisraja';
$string['passmark_details'] = 'Lukunopeus lasketaan vain, kun vähintään tämä osuus kysymyksistä on oikein.';
$string['passmarkstatus'] = 'Kun kysymyksiä on {$a->questions}, opiskelijoiden on saatava {$a->needed} oikein.';
$string['picture'] = 'Kuva';
$string['pluginname'] = 'Pikaluku';
$string['questionanswers'] = 'Vastaukset (yksi riville)';
$string['questioncount'] = '{$a} kysymystä';
$string['questionfeedback'] = 'Palaute (valinnainen)';
$string['questionno'] = 'Kysymys {$a}';
$string['questionofn'] = 'Kysymys {$a->number}/{$a->total}';
$string['questions'] = 'Kysymykset';
$string['questions_details'] = 'Täytä 1–5 kysymystä. Opiskelijat näkevät vasta tulosnäkymässä, menivätkö vastaukset oikein. Palaute näytetään vain tulosnäkymässä.';
$string['questiontext'] = 'Kysymyksen teksti';
$string['readingspeed'] = 'Lukunopeutesi';
$string['readtime'] = '{$a->count} sanaa ajassa {$a->minutes} min {$a->seconds} s';
$string['readtime_cpm'] = '{$a->count} merkkiä ajassa {$a->minutes} min {$a->seconds} s';
$string['screen1'] = 'Näkymä 1: Johdanto';
$string['screen2'] = 'Näkymä 2: Luettava teksti';
$string['screen3'] = 'Näkymä 3: Testi';
$string['screen4'] = 'Näkymä 4: Tulokset ja arviointi';
$string['showallquestions'] = 'Näytä kaikki kysymykset kerralla';
$string['showallquestions_details'] = 'Pois: yksi kysymys kerrallaan, ja napautus siirtää eteenpäin. Päällä: kaikki kysymykset yhdellä sivulla ja lähetyspainike.';
$string['showtimer'] = 'Näytä ajastin lukemisen aikana';
$string['showtimer_details'] = 'Aika tallennetaan joka tapauksessa. Piilota ajastin, jos se jännittää opiskelijoita.';
$string['speedcounts'] = 'Nopeutesi lasketaan. Tarvitsit {$a->needed}/{$a->total} oikein, ja sait {$a->correct}.';
$string['speeddoesnotcount'] = 'Nopeuttasi ei lasketa tällä kertaa. Tarvitsit {$a->needed}/{$a->total} oikein, ja sait {$a->correct}. Lue ensi kerralla vähän hitaammin, niin ymmärrät enemmän.';
$string['speedpoints'] = 'Nopeuspisteet';
$string['speedreading'] = 'Pikaluku';
$string['startreading'] = 'Aloita lukeminen';
$string['submitanswers'] = 'Lähetä vastaukset';
$string['tapnext'] = 'Napauta vastausta siirtyäksesi seuraavaan kysymykseen. Näet tuloksesi lopussa.';
$string['targetis'] = 'Tavoite: {$a}';
$string['targetwpm'] = 'Tavoitenopeus';
$string['targetwpm_details'] = 'Tämä nopeus tai nopeampi antaa 10 nopeuspistettä. Puolet tästä nopeudesta tai hitaampi antaa 0.';
$string['thepassage'] = 'Teksti';
$string['timerrunning'] = 'Ajanotto käynnissä';
$string['toofast'] = 'Se oli liian nopeaa ollakseen oikeaa lukemista, joten nopeuttasi ei lasketa. Lue ensi kerralla koko teksti.';
$string['wordcount'] = 'Sanamäärä';
$string['wordcount_details'] = 'Jätä tyhjäksi, niin sanat lasketaan automaattisesti. Täytä, jos haluat käyttää tiettyä sanamäärää.<br>Huom. Japanissa, kiinassa, koreassa ja thaissa lasketaan merkit (ei sanoja).';
$string['wordcountshort'] = '{$a} sanaa';
$string['wordsperminute'] = 'sanaa minuutissa';
$string['youranswer'] = 'Vastauksesi:';
$string['youranswers'] = 'Vastauksesi';
