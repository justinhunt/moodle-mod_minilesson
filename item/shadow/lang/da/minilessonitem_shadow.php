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
 * Danish strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video-shadowing';
$string['aihelper_placeholder_shadow'] = 'f.eks. Tilføj tegnsætning, og ret eventuelle fejl i stavning og store bogstaver';
$string['enablesubtitlefetch'] = 'Aktivér knap til hentning af undertekster';
$string['enablesubtitlefetch_details'] = 'Viser en ”Hent undertekster”-knap på elementformularen, der downloader en YouTube-videos undertekster ind i undertekst-editoren. Bemærk: hentning af undertekster virker ikke altid og kan holde op med at virke når som helst. Det er et hjælpeværktøj, som Poodll ikke garanterer altid vil være tilgængeligt.';
$string['error:badshadowlines'] = 'Linjer at shadowe skal være * (alle linjer) eller en kommasepareret liste med linjenumre, f.eks. 1,4,5,6.';
$string['error:badtimestamp'] = 'Klippets start- og sluttidspunkt skal være i formatet hh:mm:ss, f.eks. 00:01:30.';
$string['error:badvtt'] = 'Underteksterne kunne ikke fortolkes. Indtast gyldig WebVTT med mindst ét tidsbestemt tekststykke.';
$string['error:nocuesinclip'] = 'Ingen undertekstlinjer ligger helt inden for klippets start- og sluttidspunkter. Justér tidspunkterne eller underteksterne.';
$string['error:noshadowlines'] = 'Ingen af de valgte linjenumre matcher en undertekstlinje inden for klippets start- og sluttidspunkter.';
$string['error:novideoid'] = 'Der kræves et YouTube-video-id eller en URL.';
$string['error:startafterend'] = 'Klippets sluttidspunkt skal være efter starttidspunktet.';
$string['error:subtitlefetchdisabled'] = 'Hentning af undertekster er deaktiveret på dette websted.';
$string['fetchvtt'] = 'Hent undertekster';
$string['fetchvtt_disabled'] = 'Automatisk hentning af undertekster er i øjeblikket deaktiveret';
$string['fetchvtt_failed'] = 'Kunne ikke hente undertekster fra YouTube.';
$string['fetchvtt_fetching'] = 'Henter …';
$string['fetchvtt_invalidurl'] = 'Indtast først en gyldig YouTube-URL eller et video-id på 11 tegn.';
$string['fetchvtt_overwrite'] = 'Dette erstatter de undertekster, der er i editoren nu. Vil du fortsætte?';
$string['fetchvtt_overwrite_title'] = 'Erstat undertekster?';
$string['item_desc'] = 'Video-shadowing-elementet afspiller et YouTube-klip linje for linje. Eleverne ”shadower” hver undertekstlinje ved at tale sammen med videoen, mens ordene fremhæves.';
$string['loopcount'] = 'Antal shadowinger pr. linje';
$string['loopcount_desc'] = 'Hvor mange gange hver linje afspilles igen, så eleven kan shadowe den.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Næste';
$string['paste_empty'] = 'Indsæt først den udskrift, du kopierede fra YouTube.';
$string['paste_failed'] = 'Kunne ikke konvertere udskriften.';
$string['paste_label'] = 'Udskrift kopieret fra YouTube';
$string['paste_placeholder'] = '0:00
den første linje i udskriften
0:03
den anden linje i udskriften';
$string['paste_step_copy'] = 'Markér hele udskriften, kopiér den, og indsæt den i feltet nedenfor';
$string['paste_step_open_novideo'] = 'Åbn videoen på YouTube (indtast video-id\'et i formularen først for at få et link her)';
$string['paste_step_showtranscript'] = 'Under videoen: udvid beskrivelsen, og klik på ”Vis udskrift”';
$string['paste_step_timestamps'] = 'I udskriftspanelets ⋮-menu: sørg for, at tidsstempler er slået til';
$string['paste_success'] = 'Tilføjede {$a} undertekstlinjer.';
$string['paste_wordhighlightoff'] = 'Fremhævning af enkelte ord er slået fra, fordi en indsat udskrift kun har tidsangivelser for hele linjer. Klippet fremhæver én linje ad gangen.';
$string['pastevtt'] = 'Indsæt udskrift';
$string['pastevtt_convert'] = 'Konvertér til undertekster';
$string['pastevtt_title'] = 'Indsæt YouTube-udskrift';
$string['pluginname'] = 'Video-shadowing';
$string['retry'] = 'Prøv igen';
$string['rotatedevice'] = 'Drej din enhed til stående format for at fortsætte.';
$string['shadow_instructions1'] = 'Se videoen. Shadow derefter hver linje: lyt, og tal sammen med videoen, mens den afspilles igen.';
$string['shadowlines'] = 'Linjer at shadowe';
$string['shadowlines_desc'] = 'Numre på undertekstlinjer, der skal shadowes, talt fra 1 i underteksterne nedenfor, f.eks. 1,4,5,6. Brug * for at shadowe alle linjer. Andre linjer vises stadig, mens man ser med.';
$string['shadowpause'] = 'Pause mellem shadowinger (sekunder)';
$string['shadowvtt'] = 'Undertekster (WebVTT)';
$string['shadowvtt_desc'] = 'Indsæt eller redigér WebVTT-underteksterne for klippet i feltet nedenfor.';
$string['startshadowing'] = 'Start shadowing';
$string['watchhint'] = 'Tryk på afspil og se klippet. Når det er slut, trykker du på ”Start shadowing”.';
$string['wordhighlight'] = 'Aktivér fremhævning pr. ord';
$string['wordhighlight_details'] = 'Fremhæv hvert ord, efterhånden som det siges, ved hjælp af ordtidsstempler i underteksterne. YouTubes ordtidsangivelser er upålidelige på nogle videoer – slå dette fra for at fremhæve hele linjer i stedet. Når det er slået fra, springer hentning af undertekster også hentning af ordtidsstempler over.';
$string['ytclipdetails'] = 'YouTube-klip (ID/URL, start- og slutsekunder)';
