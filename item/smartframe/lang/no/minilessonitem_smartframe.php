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
 * Norwegian strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Lag en widget med AI';
$string['aiinstructions_copied'] = 'AI-instruksjonene er kopiert';
$string['aiinstructions_copy'] = 'Kopier AI-instruksjonene';
$string['aiinstructions_download'] = 'Last ned';
$string['aiinstructions_edit'] = 'For å endre en widget senere: last den ned fra filboksen nedenfor, legg den ved i en ny samtale sammen med disse instruksjonene, og si hva som skal endres.';
$string['aiinstructions_intro'] = 'Lag widgeten i ChatGPT, Claude eller Gemini, der du kan prøve den og be om endringer:';
$string['aiinstructions_show'] = 'Vis instruksjonene';
$string['aiinstructions_step1'] = 'Lim inn disse instruksjonene i en ny samtale, eller last dem ned og legg ved filen.';
$string['aiinstructions_step2'] = 'Beskriv aktiviteten. For eksempel: «En quiz om preteritum av 10 vanlige verb, med instruksjoner på engelsk og japansk, som rapporterer en poengsum.»';
$string['aiinstructions_step3'] = 'Prøv den i samtalen, og be om endringer til du er fornøyd.';
$string['aiinstructions_step4'] = 'Skriv «Export it for Poodll», last ned filen du får, og last den opp nedenfor.';
$string['error:htmlencoding'] = 'HTML-filen må være lagret som UTF-8-tekst.';
$string['error:importbase64'] = 'Widgetfilen {$a} er ikke gyldig base64. Kod filens byte som ren base64, uten et «data:»-prefiks.';
$string['error:importboth'] = 'Oppgi widgeten enten som widgethtml eller som en fil i customfile1, ikke begge.';
$string['error:importgradepolicy'] = 'gradepolicy må være 0 (ignorer widgetens poengsum), 1 (lagre den) eller 2 (tell den med i leksjonskarakteren).';
$string['error:importnofile'] = 'Elementet har ingen widget. Legg widgetens HTML i feltet widgethtml.';
$string['error:importonefile'] = 'Elementet har mer enn én fil i filområdet customfile1. Legg til bare én widgetfil (.html eller .zip).';
$string['error:manifestentry'] = 'Manifestets «entry» ({$a}) må vise til en HTML-fil i ZIP-filen.';
$string['error:manifestfield'] = 'Manifestets «{$a}» mangler eller er ikke gyldig.';
$string['error:manifestjson'] = 'Widgetens manifest er ikke gyldig JSON.';
$string['error:manifestprotocol'] = 'Denne widgeten trenger en nyere leksjonsvert. Denne verten bruker protokollversjon {$a}.';
$string['error:manifestschema'] = 'Manifestets «schemaVersion» må være {$a}.';
$string['error:nomanifest'] = 'ZIP-filen har ingen manifest.json på øverste nivå.';
$string['error:nomanifesthtml'] = 'HTML-filen har ikke noe widgetmanifest. Den trenger en &lt;script type="application/json" id="poodll-widget-manifest"&gt;-blokk. Be AI-verktøyet om å «export it for Poodll».';
$string['error:notazip'] = 'Filen er ikke en ZIP-fil, eller den er skadet.';
$string['error:notpublished'] = 'Denne widgeten kunne ikke publiseres, så elevene kan ikke se den. Lagre elementet på nytt for å prøve igjen.';
$string['error:onezip'] = 'Last opp bare én widgetfil. Slett de andre.';
$string['error:publishfailed'] = 'Widgeten kunne ikke publiseres.';
$string['error:zipbadpath'] = 'ZIP-filen inneholder et filnavn som ikke er tillatt: {$a}';
$string['error:zipduplicate'] = 'ZIP-filen inneholder to filer med samme navn: {$a}';
$string['error:zipencrypted'] = 'ZIP-filen er passordbeskyttet. Last opp en ZIP-fil uten passord.';
$string['error:zipfiletoobig'] = 'Filen {$a->file} er for stor. Den største tillatte filen er {$a->size}.';
$string['error:zipfiletype'] = 'Denne filtypen er ikke tillatt i en widget: {$a}';
$string['error:zipread'] = 'Filen {$a} kunne ikke leses fra ZIP-filen.';
$string['error:zipsymlink'] = 'ZIP-filen inneholder en lenke, og det er ikke tillatt: {$a}';
$string['error:ziptoobig'] = 'ZIP-filen er for stor. Den største tillatte ZIP-filen er {$a}.';
$string['error:ziptoomanyfiles'] = 'ZIP-filen inneholder for mange filer. Høyst {$a} er tillatt.';
$string['error:ziptotaltoobig'] = 'Filene i ZIP-filen er for store til sammen. Høyst {$a} er tillatt.';
$string['error:zipunreadable'] = 'ZIP-filen kunne ikke leses.';
$string['gradepolicy'] = 'Widgetens poengsum';
$string['gradepolicy_help'] = 'Noen widgeter rapporterer en poengsum, for eksempel 3 av 4. Velg hva leksjonen skal gjøre med den.

* Ignorer: poengsummen brukes ikke.
* Lagre, men ikke vurder: poengsummen lagres med forsøket, men teller ikke med i leksjonskarakteren.
* Bruk som elementets karakter: poengsummen teller med i leksjonskarakteren, skalert til totalpoengene. En elev som går videre før hen er ferdig, får 0.

En poengsum som widgeten sender, regnes ut i elevens nettleser, så en elev kan endre den. Bruk den til øving, ikke til viktige prøver.';
$string['gradepolicy_ignore'] = 'Ignorer';
$string['gradepolicy_record'] = 'Lagre, men ikke vurder';
$string['gradepolicy_use'] = 'Bruk som elementets karakter';
$string['item_desc'] = 'Smart Frame laster inn en opplastet HTML-side i en ramme inne i Minilesson. Den er responsiv og sikker og kan gi en karakter. Den kan brukes til forklarende widgets, egne interaktive aktiviteter eller til og med spill.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Widgetspråk';
$string['uilanguage_auto'] = 'Automatisk';
$string['uilanguage_help'] = 'Språket for widgetens instruksjoner, knapper og tilbakemelding. Dette endrer ikke språket som læres.

Automatisk bruker elevens eget språk hvis widgeten har det, deretter leksjonens morsmål, deretter elevens Moodle-språk og til slutt widgetens standardspråk. Lagre elementet én gang for å kunne velge blant widgetens språk.';
$string['warning:importuilanguage'] = 'uilanguage «{$a->lang}» er ikke ett av widgetens grensesnittspråk ({$a->languages}), så elevene vil se standardspråket.';
$string['widget_instructions1'] = 'Fullfør aktiviteten nedenfor.';
$string['widgetnotavailable'] = 'Denne aktiviteten er ikke tilgjengelig akkurat nå.';
$string['widgetpackage'] = 'Widgetfil';
$string['widgetpackage_help'] = 'Widgeten: enten én HTML-fil (det AI-verktøy lager enklest), eller en ZIP-fil for en widget med lyd, video eller mange bilder.

En HTML-fil må ha widgetens manifest i en &lt;script type="application/json" id="poodll-widget-manifest"&gt;-blokk. En ZIP-fil må ha en manifest.json og en HTML-side på øverste nivå. Alt widgeten bruker, må ligge i filen eller ZIP-filen: en widget kan ikke laste filer fra andre nettsteder og kan ikke bruke nettleserlagring (for eksempel localStorage) eller informasjonskapsler.';
$string['widgetsummary'] = 'Gjeldende widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Språk: {$a->languages}<br>Rapporterer poengsum: {$a->grade}';
