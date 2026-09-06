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
 * Finnish strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video-shadowing';
$string['aihelper_placeholder_shadow'] = 'esim. Lisää välimerkit ja korjaa oikeinkirjoitus- ja alkukirjainvirheet';
$string['enablesubtitlefetch'] = 'Ota käyttöön tekstitysten haku -painike';
$string['enablesubtitlefetch_details'] = 'Näyttää elementtilomakkeella ”Hae tekstitykset” -painikkeen, joka lataa YouTube-videon tekstitykset tekstityseditoriin. Huom: tekstitysten haku ei aina toimi ja voi lakata toimimasta milloin tahansa. Se on aputyökalu, jonka jatkuvaa saatavuutta Poodll ei takaa.';
$string['error:badshadowlines'] = 'Shadowingiin valitut rivit on annettava muodossa * (kaikki rivit) tai pilkuin eroteltuna luettelona rivinumeroista, esim. 1,4,5,6.';
$string['error:badtimestamp'] = 'Leikkeen alku- ja loppuajan on oltava muodossa hh:mm:ss, esim. 00:01:30.';
$string['error:badvtt'] = 'Tekstityksiä ei voitu jäsentää. Anna kelvollista WebVTT:tä, jossa on vähintään yksi ajastettu tekstiosuus.';
$string['error:nocuesinclip'] = 'Yksikään tekstitysrivi ei mahdu kokonaan leikkeen alku- ja loppuajan sisään. Säädä aikoja tai tekstityksiä.';
$string['error:noshadowlines'] = 'Yksikään valituista rivinumeroista ei vastaa tekstitysriviä leikkeen alku- ja loppuajan sisällä.';
$string['error:novideoid'] = 'YouTube-videon tunnus tai osoite vaaditaan.';
$string['error:startafterend'] = 'Leikkeen loppuajan on oltava alkuajan jälkeen.';
$string['error:subtitlefetchdisabled'] = 'Tekstitysten haku on poistettu käytöstä tällä sivustolla.';
$string['fetchvtt'] = 'Hae tekstitykset';
$string['fetchvtt_disabled'] = 'Tekstitysten automaattinen haku on tällä hetkellä poistettu käytöstä';
$string['fetchvtt_failed'] = 'Tekstitysten haku YouTubesta epäonnistui.';
$string['fetchvtt_fetching'] = 'Haetaan …';
$string['fetchvtt_invalidurl'] = 'Anna ensin kelvollinen YouTube-osoite tai 11-merkkinen videotunnus.';
$string['fetchvtt_overwrite'] = 'Tämä korvaa editorissa nyt olevat tekstitykset. Jatketaanko?';
$string['fetchvtt_overwrite_title'] = 'Korvataanko tekstitykset?';
$string['item_desc'] = 'Video-shadowing-osio toistaa YouTube-leikkeen rivi riviltä. Opiskelijat tekevät jokaisesta tekstitysrivistä shadowingia puhumalla videon mukana samalla kun sanat korostuvat.';
$string['loopcount'] = 'Shadowing-toistoja riviä kohden';
$string['loopcount_desc'] = 'Kuinka monta kertaa kukin rivi toistetaan, jotta opiskelija voi tehdä shadowingia.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Seuraava';
$string['paste_empty'] = 'Liitä ensin YouTubesta kopioimasi tekstitys.';
$string['paste_failed'] = 'Tekstityksen muuntaminen epäonnistui.';
$string['paste_label'] = 'YouTubesta kopioitu tekstitys';
$string['paste_placeholder'] = '0:00
tekstityksen ensimmäinen rivi
0:03
tekstityksen toinen rivi';
$string['paste_step_copy'] = 'Valitse koko tekstitys, kopioi se ja liitä se alla olevaan kenttään';
$string['paste_step_open_novideo'] = 'Avaa video YouTubessa (anna videotunnus ensin lomakkeeseen, niin saat linkin tähän)';
$string['paste_step_showtranscript'] = 'Videon alta: laajenna kuvaus ja napsauta ”Näytä tekstitys”';
$string['paste_step_timestamps'] = 'Tekstityspaneelin ⋮-valikossa: varmista, että aikaleimat ovat käytössä';
$string['paste_success'] = 'Lisättiin {$a} tekstitysriviä.';
$string['paste_wordhighlightoff'] = 'Sanakohtainen korostus on poistettu käytöstä, koska liitetyssä tekstityksessä on ajoitukset vain kokonaisille riveille. Leike korostaa yhden rivin kerrallaan.';
$string['pastevtt'] = 'Liitä tekstitys';
$string['pastevtt_convert'] = 'Muunna tekstityksiksi';
$string['pastevtt_title'] = 'Liitä YouTube-tekstitys';
$string['pluginname'] = 'Video-shadowing';
$string['retry'] = 'Yritä uudelleen';
$string['rotatedevice'] = 'Käännä laite pystyasentoon jatkaaksesi.';
$string['shadow_instructions1'] = 'Katso video. Tee sitten jokaisesta rivistä shadowing: kuuntele ja puhu videon mukana, kun se toistetaan uudelleen.';
$string['shadowlines'] = 'Shadowingiin valitut rivit';
$string['shadowlines_desc'] = 'Shadowingiin valittavien tekstitysrivien numerot, laskettuna alla olevista tekstityksistä ykkösestä alkaen, esim. 1,4,5,6. Käytä *-merkkiä tehdäksesi shadowingin kaikista riveistä. Muut rivit näkyvät silti katselun aikana.';
$string['shadowpause'] = 'Tauko shadowing-toistojen välissä (sekuntia)';
$string['shadowvtt'] = 'Tekstitykset (WebVTT)';
$string['shadowvtt_desc'] = 'Liitä tai muokkaa leikkeen WebVTT-tekstityksiä alla olevaan kenttään.';
$string['startshadowing'] = 'Aloita shadowing';
$string['watchhint'] = 'Paina toista ja katso video. Kun se päättyy, paina ”Aloita shadowing”.';
$string['wordhighlight'] = 'Ota käyttöön sanakohtainen korostus';
$string['wordhighlight_details'] = 'Korosta jokainen sana sitä mukaa kun se sanotaan käyttäen tekstitysten sanakohtaisia aikaleimoja. YouTuben sana-ajoitukset ovat epäluotettavia joissakin videoissa – poista tämä käytöstä, niin korostetaan kokonaisia rivejä. Kun tämä on pois päältä, tekstitysten haku ohittaa myös sana-aikaleimojen haun.';
$string['ytclipdetails'] = 'YouTube-leike (tunnus/osoite, alku- ja loppusekunnit)';
