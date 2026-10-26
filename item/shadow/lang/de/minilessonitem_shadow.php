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
 * German strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video-Shadowing';
$string['aihelper_placeholder_shadow'] = 'z. B. Ergänze Satzzeichen und korrigiere alle Rechtschreib- und Groß-/Kleinschreibfehler';
$string['autoforward'] = 'Automatisch weiter';
$string['autoforward_details'] = 'Nach dem letzten Shadowing einer Zeile geht es von selbst zur nächsten Zeile, statt auf OK / Weiter zu warten. In diesem Modus gibt es keine Schaltfläche Wiederholen.';
$string['enablesubtitlefetch'] = 'Schaltfläche zum Abrufen von Untertiteln aktivieren';
$string['enablesubtitlefetch_details'] = 'Zeigt im Elementformular eine Schaltfläche „Untertitel abrufen“ an, die die Untertitel eines YouTube-Videos in den Untertitel-Editor herunterlädt. Hinweis: Das Abrufen von Untertiteln funktioniert nicht immer und kann jederzeit aufhören zu funktionieren. Es ist ein Hilfswerkzeug, dessen dauerhafte Verfügbarkeit Poodll nicht garantiert.';
$string['error:badshadowlines'] = 'Die Zeilen für das Shadowing müssen * (alle Zeilen) oder eine kommagetrennte Liste von Zeilennummern sein, z. B. 1,4,5,6.';
$string['error:badtimestamp'] = 'Start- und Endzeit des Clips müssen im Format hh:mm:ss angegeben werden, z. B. 00:01:30.';
$string['error:badvtt'] = 'Die Untertitel konnten nicht ausgewertet werden. Gib gültiges WebVTT mit mindestens einem Zeitabschnitt ein.';
$string['error:nocuesinclip'] = 'Keine Untertitelzeile liegt vollständig innerhalb von Start- und Endzeit des Clips. Passe die Zeiten oder die Untertitel an.';
$string['error:noshadowlines'] = 'Keine der ausgewählten Zeilennummern entspricht einer Untertitelzeile innerhalb von Start- und Endzeit des Clips.';
$string['error:novideoid'] = 'Eine YouTube-Video-ID oder -URL ist erforderlich.';
$string['error:startafterend'] = 'Die Endzeit des Clips muss nach der Startzeit liegen.';
$string['error:subtitlefetchdisabled'] = 'Das Abrufen von Untertiteln ist auf dieser Website deaktiviert.';
$string['fetchvtt'] = 'Untertitel abrufen';
$string['fetchvtt_disabled'] = 'Das automatische Abrufen von Untertiteln ist derzeit deaktiviert';
$string['fetchvtt_failed'] = 'Die Untertitel konnten nicht von YouTube abgerufen werden.';
$string['fetchvtt_fetching'] = 'Wird abgerufen...';
$string['fetchvtt_invalidurl'] = 'Gib zuerst eine gültige YouTube-URL oder eine 11-stellige Video-ID ein.';
$string['fetchvtt_overwrite'] = 'Dadurch werden die Untertitel im Editor ersetzt. Fortfahren?';
$string['fetchvtt_overwrite_title'] = 'Untertitel ersetzen?';
$string['item_desc'] = 'Das Element Video-Shadowing spielt einen YouTube-Clip Zeile für Zeile ab. Die Lernenden sprechen jede Untertitelzeile im „Shadowing“ mit, während die Wörter hervorgehoben werden.';
$string['loopcount'] = 'Shadowing-Anzahl pro Zeile';
$string['loopcount_desc'] = 'Wie oft jede Zeile wiederholt wird, damit die Lernenden Shadowing machen.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Weiter';
$string['paste_empty'] = 'Füge zuerst das von YouTube kopierte Transkript ein.';
$string['paste_failed'] = 'Das Transkript konnte nicht konvertiert werden.';
$string['paste_label'] = 'Von YouTube kopiertes Transkript';
$string['paste_placeholder'] = '0:00
die erste Zeile des Transkripts
0:03
die zweite Zeile des Transkripts';
$string['paste_step_copy'] = 'Markiere das gesamte Transkript, kopiere es und füge es in das Feld unten ein';
$string['paste_step_open_novideo'] = 'Öffne das Video auf YouTube (gib zuerst die Video-ID im Formular ein, um hier einen Link zu erhalten)';
$string['paste_step_showtranscript'] = 'Klappe unter dem Video die Beschreibung auf und klicke auf „Transkript anzeigen“';
$string['paste_step_timestamps'] = 'Prüfe im ⋮-Menü des Transkript-Bereichs, dass die Zeitstempel eingeschaltet sind';
$string['paste_success'] = 'Hinzugefügte Untertitelzeilen: {$a}';
$string['paste_wordhighlightoff'] = 'Die wortweise Hervorhebung wurde ausgeschaltet, da ein eingefügtes Transkript nur Zeitangaben für ganze Zeilen enthält. Der Clip hebt jeweils eine Zeile hervor.';
$string['pastevtt'] = 'Transkript einfügen';
$string['pastevtt_convert'] = 'In Untertitel umwandeln';
$string['pastevtt_title'] = 'YouTube-Transkript einfügen';
$string['pluginname'] = 'Video-Shadowing';
$string['retry'] = 'Wiederholen';
$string['rotatedevice'] = 'Halte dein Gerät im Hochformat, um fortzufahren.';
$string['shadow_instructions1'] = 'Sieh dir das Video an. Mache dann bei jeder Zeile Shadowing: höre zu und sprich mit dem Video mit, während es erneut abgespielt wird.';
$string['shadowlines'] = 'Zeilen für das Shadowing';
$string['shadowlines_desc'] = 'Nummern der Untertitelzeilen für das Shadowing, ab 1 gezählt in den Untertiteln unten, z. B. 1,4,5,6. Mit * werden alle Zeilen einbezogen. Die übrigen Zeilen werden beim Ansehen weiterhin angezeigt.';
$string['shadowpause'] = 'Pause zwischen den Shadowings (Sekunden)';
$string['shadowvtt'] = 'Untertitel (WebVTT)';
$string['shadowvtt_desc'] = 'Füge die WebVTT-Untertitel für den Clip im Bereich unten ein oder bearbeite sie.';
$string['startshadowing'] = 'Shadowing starten';
$string['watchhint'] = 'Drücke auf Wiedergabe und sieh dir den Clip an. Wenn er zu Ende ist, drücke auf „Shadowing starten“.';
$string['wordhighlight'] = 'Wortweise Hervorhebung aktivieren';
$string['wordhighlight_details'] = 'Hebt jedes Wort beim Sprechen hervor, anhand der Wort-Zeitstempel in den Untertiteln. Die Wort-Zeitangaben von YouTube sind bei manchen Videos unzuverlässig – schalte dies aus, um stattdessen ganze Zeilen hervorzuheben. Ist die Option aus, werden beim Abrufen der Untertitel auch keine Wort-Zeitstempel abgerufen.';
$string['ytclipdetails'] = 'YouTube-Clip (ID/URL, Start- und Endsekunden)';
