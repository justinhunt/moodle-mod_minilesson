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
 * Finnish strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Keskustelu';
$string['check_auto'] = 'Automaattinen tarkistus (puheentunnistus)';
$string['check_self'] = 'Itsetarkistus (vain nauhoitus)';
$string['checking'] = 'Tarkistetaan…';
$string['checkmode'] = 'Tarkistus';
$string['checkmode_help'] = 'Automaattinen tarkistus: puheentunnistuksen on hyväksyttävä jokainen repliikki ennen kuin keskustelu jatkuu. Itsetarkistus: jokainen repliikki vain nauhoitetaan, ja se hyväksytään aina. Opiskelija voi kuunnella sen ja nauhoittaa sen uudelleen. Molemmat arvioidaan samalla tavalla: sen mukaan, kuinka monta omaa repliikkiään opiskelija sai tehtyä, ei puheentunnistuksen tuloksen mukaan.';
$string['choosepart'] = 'Minkä roolin haluat?';
$string['complete'] = 'Keskustelu valmis';
$string['conversation'] = 'Keskustelu';
$string['defaultinstructions'] = 'Sano repliikkisi, kun ne tulevat näkyviin. Kuuntele pariasi.';
$string['dialog'] = 'Keskustelu';
$string['dialog_help'] = 'Kirjoita yksi repliikki riville. Aloita jokainen rivi merkinnällä A) tai B), joka kertoo, kuka sen sanoo, esimerkiksi:

A) Hei! Mitä saisi olla tänään?
B) Saisinko ison latten?';
$string['error:dialogspeakers'] = 'Keskustelussa on oltava repliikkejä molemmille: A) ja B).';
$string['great'] = 'Hienoa!';
$string['hearit'] = 'Kuuntele';
$string['item_desc'] = 'Opiskelija puhuu valmiin keskustelun toisen roolin, ja tietokoneääni puhuu toisen.';
$string['labela'] = 'A:n nimi';
$string['labela_help'] = 'Valinnaiset nimet kahdelle roolille, esimerkiksi ”Tarjoilija” ja ”Asiakas”. Ne näkyvät keskustelussa ja aloitusnäytössä. Jos jätät ne tyhjiksi, käytetään nimiä A ja B.';
$string['labelb'] = 'B:n nimi';
$string['listen'] = 'Kuuntele pariasi…';
$string['look_dotted'] = 'Harmaa teksti katkoviivalaatikossa';
$string['look_grey'] = 'Vain harmaa teksti';
$string['movedon'] = 'Ohitettu';
$string['moveon'] = 'Ohita';
$string['myrecording'] = 'Oma nauhoitus';
$string['playall'] = 'Toista koko keskustelu';
$string['playingback'] = 'Toistetaan {$a->num}/{$a->total}';
$string['pluginname'] = 'Keskustelu';
$string['prompt_full'] = 'Koko repliikki';
$string['prompt_hidden'] = 'Piilotettu';
$string['prompt_initials'] = 'Vain alkukirjaimet';
$string['prompthelp'] = 'Vihje';
$string['prompthelp_help'] = 'Kuinka paljon opiskelijan seuraavasta repliikistä näytetään. Vaihtoehdoilla ”Vain alkukirjaimet” ja ”Piilotettu” opiskelijan on muistettava repliikki. Kokonaiset sanat näkyvät, kun repliikki on sanottu.';
$string['promptlook'] = 'Vihjeen ulkoasu';
$string['recorded'] = 'Nauhoitettu';
$string['recording'] = 'Nauhoitetaan… lopeta painamalla mikrofonia.';
$string['recordingreplaced'] = 'Nauhoitus korvattu.';
$string['redo'] = 'Nauhoita uudelleen';
$string['redohere'] = 'Nauhoita tämä repliikki uudelleen painamalla mikrofonia.';
$string['redowait'] = 'Odota, että parisi lopettaa, ja paina sitten ”Nauhoita uudelleen”.';
$string['replay'] = 'Toista uudelleen';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = 'Opiskelija valitsee';
$string['saidfirsttry'] = 'Sanottu ensimmäisellä yrityksellä';
$string['saidintries'] = 'Sanottu {$a} yrityksellä';
$string['scene'] = 'Tapahtumapaikan kuva';
$string['scene_help'] = 'Valinnainen kuva keskustelun taustalla kertomaan tapahtumapaikan: kahvila, luokkahuone, toimisto. Vain yhtä kuvaa käytetään. Jos kuvia on kaksi, käytetään ensin lisättyä.';
$string['startagain'] = 'Aloita alusta';
$string['startconversation'] = 'Aloita';
$string['stopplayback'] = 'Pysäytä';
$string['studentrole'] = 'Opiskelijan rooli';
$string['studentrole_help'] = 'Rooli, jonka opiskelija puhuu. Tietokoneääni puhuu toisen roolin.';
$string['summaryauto'] = '{$a->correct}/{$a->total} repliikkiä sanottu oikein';
$string['summaryself'] = 'Nauhoitettuja repliikkejä: {$a}. Voit nauhoittaa minkä tahansa repliikin uudelleen.';
$string['tryagain'] = 'Melkein. Sano repliikki uudelleen.';
$string['voicea'] = 'A:n ääni';
$string['voiceb'] = 'B:n ääni';
$string['youare'] = 'Roolisi: {$a}';
$string['yourline'] = 'Sinun repliikkisi';
$string['yourturn'] = 'Sinun vuorosi. Paina mikrofonia ja sano repliikkisi.';
