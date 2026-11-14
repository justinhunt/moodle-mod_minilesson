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
 * Danish strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Lav en widget med AI';
$string['aiinstructions_copied'] = 'AI-instruktionerne er kopieret';
$string['aiinstructions_copy'] = 'Kopiér AI-instruktionerne';
$string['aiinstructions_download'] = 'Download';
$string['aiinstructions_edit'] = 'For at ændre en widget senere: download den fra filboksen nedenfor, vedhæft den i en ny chat sammen med disse instruktioner, og skriv, hvad der skal ændres.';
$string['aiinstructions_intro'] = 'Lav widgetten i ChatGPT, Claude eller Gemini, hvor du kan prøve den og bede om ændringer:';
$string['aiinstructions_show'] = 'Vis instruktionerne';
$string['aiinstructions_step1'] = 'Indsæt disse instruktioner i en ny chat, eller download dem og vedhæft filen.';
$string['aiinstructions_step2'] = 'Beskriv aktiviteten. For eksempel: »En quiz om datid af 10 almindelige verber, med instruktioner på engelsk og japansk, som rapporterer en score.«';
$string['aiinstructions_step3'] = 'Prøv den i chatten, og bed om ændringer, indtil du er tilfreds.';
$string['aiinstructions_step4'] = 'Skriv »Export it for Poodll«, download den fil, du får, og upload den nedenfor.';
$string['error:htmlencoding'] = 'HTML-filen skal være gemt som UTF-8-tekst.';
$string['error:importbase64'] = 'Widgetfilen {$a} er ikke gyldig base64. Kod filens bytes som ren base64 uden et »data:«-præfiks.';
$string['error:importboth'] = 'Angiv widgetten enten som widgethtml eller som en fil i customfile1, ikke begge.';
$string['error:importgradepolicy'] = 'gradepolicy skal være 0 (ignorér widgettens score), 1 (gem den) eller 2 (medregn den i lektionens karakter).';
$string['error:importnofile'] = 'Elementet har ingen widget. Indsæt widgettens HTML i feltet widgethtml.';
$string['error:importonefile'] = 'Elementet har mere end én fil i filområdet customfile1. Tilføj kun én widgetfil (.html eller .zip).';
$string['error:manifestentry'] = 'Manifestets »entry« ({$a}) skal angive en HTML-fil i ZIP-filen.';
$string['error:manifestfield'] = 'Manifestets »{$a}« mangler eller er ugyldig.';
$string['error:manifestjson'] = 'Widgettens manifest er ikke gyldig JSON.';
$string['error:manifestprotocol'] = 'Denne widget kræver en nyere lektionsvært. Denne vært bruger protokolversion {$a}.';
$string['error:manifestschema'] = 'Manifestets »schemaVersion« skal være {$a}.';
$string['error:nomanifest'] = 'ZIP-filen har ingen manifest.json på øverste niveau.';
$string['error:nomanifesthtml'] = 'HTML-filen har intet widgetmanifest. Den skal have en &lt;script type="application/json" id="poodll-widget-manifest"&gt;-blok. Bed AI-værktøjet om at »export it for Poodll«.';
$string['error:notazip'] = 'Filen er ikke en ZIP-fil, eller den er beskadiget.';
$string['error:notpublished'] = 'Denne widget kunne ikke udgives, så eleverne kan ikke se den. Gem elementet igen for at prøve igen.';
$string['error:onezip'] = 'Upload kun én widgetfil. Slet de andre.';
$string['error:publishfailed'] = 'Widgetten kunne ikke udgives.';
$string['error:zipbadpath'] = 'ZIP-filen indeholder et filnavn, der ikke er tilladt: {$a}';
$string['error:zipduplicate'] = 'ZIP-filen indeholder to filer med samme navn: {$a}';
$string['error:zipencrypted'] = 'ZIP-filen er beskyttet med adgangskode. Upload en ZIP-fil uden adgangskode.';
$string['error:zipfiletoobig'] = 'Filen {$a->file} er for stor. Den største tilladte fil er {$a->size}.';
$string['error:zipfiletype'] = 'Denne filtype er ikke tilladt i en widget: {$a}';
$string['error:zipread'] = 'Filen {$a} kunne ikke læses fra ZIP-filen.';
$string['error:zipsymlink'] = 'ZIP-filen indeholder et link, hvilket ikke er tilladt: {$a}';
$string['error:ziptoobig'] = 'ZIP-filen er for stor. Den største tilladte ZIP-fil er {$a}.';
$string['error:ziptoomanyfiles'] = 'ZIP-filen indeholder for mange filer. Højst {$a} er tilladt.';
$string['error:ziptotaltoobig'] = 'Filerne i ZIP-filen fylder for meget tilsammen. Højst {$a} er tilladt.';
$string['error:zipunreadable'] = 'ZIP-filen kunne ikke læses.';
$string['gradepolicy'] = 'Widgettens score';
$string['gradepolicy_help'] = 'Nogle widgets rapporterer en score, fx 3 ud af 4. Vælg, hvad lektionen skal gøre med den.

* Ignorér: scoren bruges ikke.
* Gem, men bedøm ikke: scoren gemmes med forsøget, men tæller ikke med i lektionens karakter.
* Brug som elementets karakter: scoren tæller med i lektionens karakter, skaleret til de samlede point. En elev, der går videre, før de er færdige, får 0.

En score, som widgetten sender, beregnes i elevens browser, så en elev kan ændre den. Brug den til øvelse, ikke til vigtige prøver.';
$string['gradepolicy_ignore'] = 'Ignorér';
$string['gradepolicy_record'] = 'Gem, men bedøm ikke';
$string['gradepolicy_use'] = 'Brug som elementets karakter';
$string['item_desc'] = 'Smart Frame indlæser en uploadet HTML-side i en ramme i Minilesson. Den er responsiv og sikker og kan give en karakter. Den kan bruges til forklarende widgets, egne interaktive aktiviteter eller endda spil.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Widgetsprog';
$string['uilanguage_auto'] = 'Automatisk';
$string['uilanguage_help'] = 'Sproget for widgettens instruktioner, knapper og feedback. Det ændrer ikke det sprog, der læres.

Automatisk bruger elevens eget sprog, hvis widgetten har det, derefter lektionens modersmål, derefter elevens Moodle-sprog og til sidst widgettens standardsprog. Gem elementet én gang for at kunne vælge mellem widgettens sprog.';
$string['warning:importuilanguage'] = 'uilanguage »{$a->lang}« er ikke et af widgettens grænsefladesprog ({$a->languages}), så eleverne vil se standardsproget.';
$string['widget_instructions1'] = 'Gennemfør aktiviteten nedenfor.';
$string['widgetnotavailable'] = 'Denne aktivitet er ikke tilgængelig lige nu.';
$string['widgetpackage'] = 'Widgetfil';
$string['widgetpackage_help'] = 'Widgetten: enten én HTML-fil (det, AI-værktøjer nemmest laver) eller en ZIP-fil til en widget med lyd, video eller mange billeder.

En HTML-fil skal indeholde widgettens manifest i en &lt;script type="application/json" id="poodll-widget-manifest"&gt;-blok. En ZIP-fil skal have en manifest.json og en HTML-side på øverste niveau. Alt, hvad widgetten bruger, skal ligge i filen eller ZIP-filen: en widget kan ikke hente filer fra andre websteder og kan ikke bruge browserlagring (fx localStorage) eller cookies.';
$string['widgetsummary'] = 'Nuværende widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Sprog: {$a->languages}<br>Rapporterer score: {$a->grade}';
