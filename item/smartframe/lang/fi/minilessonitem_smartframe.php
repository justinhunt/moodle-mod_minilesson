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
 * Finnish strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Tee widget tekoälyllä';
$string['aiinstructions_copied'] = 'Tekoälyohjeet kopioitu';
$string['aiinstructions_copy'] = 'Kopioi tekoälyohjeet';
$string['aiinstructions_download'] = 'Lataa';
$string['aiinstructions_edit'] = 'Jos haluat muuttaa widgetiä myöhemmin, lataa se alla olevasta tiedostoruudusta, liitä se uuteen keskusteluun näiden ohjeiden kanssa ja kerro, mitä muutetaan.';
$string['aiinstructions_intro'] = 'Tee widget ChatGPT:ssä, Claudessa tai Geminissä, joissa voit kokeilla sitä ja pyytää muutoksia:';
$string['aiinstructions_show'] = 'Näytä ohjeet';
$string['aiinstructions_step1'] = 'Liitä nämä ohjeet uuteen keskusteluun, tai lataa ne ja liitä tiedosto.';
$string['aiinstructions_step2'] = 'Kuvaile aktiviteetti. Esimerkiksi: ”Tietovisa 10 yleisen verbin menneestä aikamuodosta, ohjeet englanniksi ja japaniksi, ja se ilmoittaa pistemäärän.”';
$string['aiinstructions_step3'] = 'Kokeile sitä keskustelussa ja pyydä muutoksia, kunnes olet tyytyväinen.';
$string['aiinstructions_step4'] = 'Kirjoita ”Export it for Poodll”, lataa saamasi tiedosto ja lähetä se alla.';
$string['error:htmlencoding'] = 'HTML-tiedosto täytyy tallentaa UTF-8-tekstinä.';
$string['error:importbase64'] = 'Widget-tiedosto {$a} ei ole kelvollista base64:ää. Koodaa tiedoston tavut pelkkänä base64:nä ilman ”data:”-etuliitettä.';
$string['error:importboth'] = 'Anna widget joko widgethtml-kentässä tai tiedostona customfile1:ssä, ei molempia.';
$string['error:importgradepolicy'] = 'gradepolicy-arvon täytyy olla 0 (ohita widgetin pistemäärä), 1 (tallenna se) tai 2 (laske se oppitunnin arvosanaan).';
$string['error:importnofile'] = 'Osiossa ei ole widgetiä. Lisää widgetin HTML sen widgethtml-kenttään.';
$string['error:importonefile'] = 'Osion customfile1-tiedostoalueella on useampi kuin yksi tiedosto. Lisää vain yksi widget-tiedosto (.html tai .zip).';
$string['error:manifestentry'] = 'Manifestin ”entry” ({$a}) täytyy olla ZIP-tiedostossa oleva HTML-tiedosto.';
$string['error:manifestfield'] = 'Manifestin ”{$a}” puuttuu tai ei ole kelvollinen.';
$string['error:manifestjson'] = 'Widgetin manifesti ei ole kelvollista JSONia.';
$string['error:manifestprotocol'] = 'Tämä widget tarvitsee uudemman oppituntialustan. Tämä alusta käyttää protokollaversiota {$a}.';
$string['error:manifestschema'] = 'Manifestin ”schemaVersion” täytyy olla {$a}.';
$string['error:nomanifest'] = 'ZIP-tiedoston ylimmällä tasolla ei ole manifest.json-tiedostoa.';
$string['error:nomanifesthtml'] = 'HTML-tiedostossa ei ole widget-manifestia. Siinä täytyy olla &lt;script type="application/json" id="poodll-widget-manifest"&gt;-lohko. Pyydä tekoälytyökalua: ”export it for Poodll”.';
$string['error:notazip'] = 'Tiedosto ei ole ZIP-tiedosto, tai se on vioittunut.';
$string['error:notpublished'] = 'Tätä widgetiä ei voitu julkaista, joten opiskelijat eivät näe sitä. Yritä uudelleen tallentamalla osio uudestaan.';
$string['error:onezip'] = 'Lähetä vain yksi widget-tiedosto. Poista muut.';
$string['error:publishfailed'] = 'Widgetiä ei voitu julkaista.';
$string['error:zipbadpath'] = 'ZIP-tiedostossa on tiedostonimi, joka ei ole sallittu: {$a}';
$string['error:zipduplicate'] = 'ZIP-tiedostossa on kaksi samannimistä tiedostoa: {$a}';
$string['error:zipencrypted'] = 'ZIP-tiedosto on suojattu salasanalla. Lähetä ZIP-tiedosto ilman salasanaa.';
$string['error:zipfiletoobig'] = 'Tiedosto {$a->file} on liian suuri. Suurin sallittu tiedosto on {$a->size}.';
$string['error:zipfiletype'] = 'Tämä tiedostotyyppi ei ole sallittu widgetissä: {$a}';
$string['error:zipread'] = 'Tiedostoa {$a} ei voitu lukea ZIP-tiedostosta.';
$string['error:zipsymlink'] = 'ZIP-tiedostossa on linkki, mikä ei ole sallittua: {$a}';
$string['error:ziptoobig'] = 'ZIP-tiedosto on liian suuri. Suurin sallittu ZIP-tiedosto on {$a}.';
$string['error:ziptoomanyfiles'] = 'ZIP-tiedostossa on liikaa tiedostoja. Enintään {$a} on sallittu.';
$string['error:ziptotaltoobig'] = 'ZIP-tiedoston tiedostot ovat yhteensä liian suuria. Enintään {$a} on sallittu.';
$string['error:zipunreadable'] = 'ZIP-tiedostoa ei voitu lukea.';
$string['gradepolicy'] = 'Widgetin pistemäärä';
$string['gradepolicy_help'] = 'Jotkin widgetit ilmoittavat pistemäärän, esimerkiksi 3/4. Valitse, mitä oppitunti tekee sillä.

* Ohita: pistemäärää ei käytetä.
* Tallenna, mutta älä arvioi: pistemäärä tallennetaan suorituskerran mukana, mutta sitä ei lasketa oppitunnin arvosanaan.
* Käytä osion arvosanana: pistemäärä lasketaan oppitunnin arvosanaan kokonaispisteisiin skaalattuna. Jos opiskelija siirtyy eteenpäin ennen kuin on valmis, hän saa 0.

Widgetin lähettämä pistemäärä lasketaan opiskelijan selaimessa, joten opiskelija voi muuttaa sitä. Käytä sitä harjoitteluun, ei tärkeisiin kokeisiin.';
$string['gradepolicy_ignore'] = 'Ohita';
$string['gradepolicy_record'] = 'Tallenna, mutta älä arvioi';
$string['gradepolicy_use'] = 'Käytä osion arvosanana';
$string['item_desc'] = 'Smart Frame näyttää ladatun HTML-sivun kehyksessä Minilesson-oppitunnin sisällä. Se on responsiivinen ja turvallinen, ja se voi antaa arvosanan. Sitä voi käyttää selittäviin widgetteihin, omiin interaktiivisiin tehtäviin tai jopa peleihin.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Widgetin kieli';
$string['uilanguage_auto'] = 'Automaattinen';
$string['uilanguage_help'] = 'Widgetin ohjeiden, painikkeiden ja palautteen kieli. Tämä ei muuta opiskeltavaa kieltä.

Automaattinen käyttää opiskelijan omaa kieltä, jos widget tarjoaa sen, sitten oppitunnin äidinkieltä, sitten opiskelijan Moodle-kieltä ja lopuksi widgetin oletuskieltä. Tallenna osio kerran, niin voit valita widgetin kielistä.';
$string['warning:importuilanguage'] = 'uilanguage ”{$a->lang}” ei ole mikään widgetin käyttöliittymäkielistä ({$a->languages}), joten opiskelijat näkevät sen oletuskielen.';
$string['widget_instructions1'] = 'Tee alla oleva aktiviteetti.';
$string['widgetnotavailable'] = 'Tämä aktiviteetti ei ole juuri nyt saatavilla.';
$string['widgetpackage'] = 'Widget-tiedosto';
$string['widgetpackage_help'] = 'Widget: joko yksi HTML-tiedosto (jonka tekoälytyökalut tekevät helpoimmin) tai ZIP-tiedosto, jos widgetissä on ääntä, videota tai paljon kuvia.

HTML-tiedostossa täytyy olla widgetin manifesti &lt;script type="application/json" id="poodll-widget-manifest"&gt;-lohkossa. ZIP-tiedoston ylimmällä tasolla täytyy olla manifest.json ja HTML-sivu. Kaiken, mitä widget käyttää, täytyy olla tiedostossa tai ZIP-tiedostossa: widget ei voi ladata tiedostoja muilta sivustoilta eikä käyttää selaimen tallennustilaa (kuten localStoragea) tai evästeitä.';
$string['widgetsummary'] = 'Nykyinen widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Kielet: {$a->languages}<br>Ilmoittaa pistemäärän: {$a->grade}';
