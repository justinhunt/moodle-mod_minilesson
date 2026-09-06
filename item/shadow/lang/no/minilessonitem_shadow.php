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
 * Norwegian strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video-shadowing';
$string['aihelper_placeholder_shadow'] = 'f.eks. Legg til tegnsetting og rett eventuelle feil i staving og stor forbokstav';
$string['enablesubtitlefetch'] = 'Aktiver knapp for henting av undertekster';
$string['enablesubtitlefetch_details'] = 'Viser en «Hent undertekster»-knapp på elementskjemaet som laster ned en YouTube-videos undertekster inn i underteksteditoren. Merk: henting av undertekster vil ikke alltid fungere, og kan slutte å fungere når som helst. Det er et hjelpeverktøy som Poodll ikke garanterer alltid vil være tilgjengelig.';
$string['error:badshadowlines'] = 'Linjer å shadowe må være * (alle linjer) eller en kommaseparert liste med linjenumre, f.eks. 1,4,5,6.';
$string['error:badtimestamp'] = 'Start- og sluttidspunkt for klippet må være i formatet hh:mm:ss, f.eks. 00:01:30.';
$string['error:badvtt'] = 'Undertekstene kunne ikke tolkes. Skriv inn gyldig WebVTT med minst én tidsbestemt tekstlinje.';
$string['error:nocuesinclip'] = 'Ingen undertekstlinjer ligger helt innenfor start- og sluttidspunktene for klippet. Juster tidspunktene eller undertekstene.';
$string['error:noshadowlines'] = 'Ingen av de valgte linjenumrene stemmer med en undertekstlinje innenfor start- og sluttidspunktene for klippet.';
$string['error:novideoid'] = 'Det kreves en YouTube-video-ID eller -URL.';
$string['error:startafterend'] = 'Sluttidspunktet for klippet må være etter starttidspunktet.';
$string['error:subtitlefetchdisabled'] = 'Henting av undertekster er deaktivert på dette nettstedet.';
$string['fetchvtt'] = 'Hent undertekster';
$string['fetchvtt_disabled'] = 'Automatisk henting av undertekster er for øyeblikket deaktivert';
$string['fetchvtt_failed'] = 'Kunne ikke hente undertekster fra YouTube.';
$string['fetchvtt_fetching'] = 'Henter …';
$string['fetchvtt_invalidurl'] = 'Skriv inn en gyldig YouTube-URL eller en video-ID på 11 tegn først.';
$string['fetchvtt_overwrite'] = 'Dette erstatter undertekstene som ligger i redigeringsfeltet nå. Vil du fortsette?';
$string['fetchvtt_overwrite_title'] = 'Erstatte undertekster?';
$string['item_desc'] = 'Video-shadowing-elementet spiller av et YouTube-klipp linje for linje. Elevene «shadower» hver undertekstlinje ved å snakke sammen med videoen mens ordene utheves.';
$string['loopcount'] = 'Antall shadowinger per linje';
$string['loopcount_desc'] = 'Hvor mange ganger hver linje spilles av på nytt for at eleven skal shadowe den.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Neste';
$string['paste_empty'] = 'Lim inn transkripsjonen du kopierte fra YouTube først.';
$string['paste_failed'] = 'Kunne ikke konvertere transkripsjonen.';
$string['paste_label'] = 'Transkripsjon kopiert fra YouTube';
$string['paste_placeholder'] = '0:00
den første linjen i transkripsjonen
0:03
den andre linjen i transkripsjonen';
$string['paste_step_copy'] = 'Merk hele transkripsjonen, kopier den, og lim den inn i feltet under';
$string['paste_step_open_novideo'] = 'Åpne videoen på YouTube (skriv inn video-ID-en i skjemaet først for å få en lenke her)';
$string['paste_step_showtranscript'] = 'Under videoen: utvid beskrivelsen og klikk på «Vis transkripsjon»';
$string['paste_step_timestamps'] = 'I ⋮-menyen i transkripsjonspanelet: kontroller at tidsstempler er slått på';
$string['paste_success'] = 'La til {$a} undertekstlinjer.';
$string['paste_wordhighlightoff'] = 'Uthevde ord er slått av, fordi en innlimt transkripsjon bare har tidspunkter for hele linjer. Klippet uthever én linje om gangen.';
$string['pastevtt'] = 'Lim inn transkripsjon';
$string['pastevtt_convert'] = 'Konverter til undertekster';
$string['pastevtt_title'] = 'Lim inn YouTube-transkripsjon';
$string['pluginname'] = 'Video-shadowing';
$string['retry'] = 'Prøv igjen';
$string['rotatedevice'] = 'Vri enheten til stående modus for å fortsette.';
$string['shadow_instructions1'] = 'Se videoen. Shadow deretter hver linje: lytt, og snakk sammen med videoen mens den spilles av på nytt.';
$string['shadowlines'] = 'Linjer å shadowe';
$string['shadowlines_desc'] = 'Numre på undertekstlinjer som skal shadowes, telt fra 1 i undertekstene nedenfor, f.eks. 1,4,5,6. Bruk * for å shadowe alle linjer. Andre linjer vises fortsatt mens man ser på.';
$string['shadowpause'] = 'Pause mellom shadowinger (sekunder)';
$string['shadowvtt'] = 'Undertekster (WebVTT)';
$string['shadowvtt_desc'] = 'Lim inn eller rediger WebVTT-undertekstene for klippet i feltet nedenfor.';
$string['startshadowing'] = 'Start shadowing';
$string['watchhint'] = 'Trykk på spill av og se klippet. Når det er ferdig, trykker du på «Start shadowing».';
$string['wordhighlight'] = 'Aktiver uthevelse per ord';
$string['wordhighlight_details'] = 'Uthev hvert ord etter hvert som det sies, ved hjelp av ordtidsstempler i undertekstene. YouTubes ordtidspunkter er upålitelige på enkelte videoer – slå dette av for å utheve hele linjer i stedet. Når det er av, hopper henting av undertekster også over henting av ordtidsstempler.';
$string['ytclipdetails'] = 'YouTube-klipp (ID/URL, start- og sluttsekunder)';
