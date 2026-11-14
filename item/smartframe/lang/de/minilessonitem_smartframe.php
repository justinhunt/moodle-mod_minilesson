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
 * German strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Ein Widget mit KI erstellen';
$string['aiinstructions_copied'] = 'KI-Anweisungen kopiert';
$string['aiinstructions_copy'] = 'KI-Anweisungen kopieren';
$string['aiinstructions_download'] = 'Herunterladen';
$string['aiinstructions_edit'] = 'Um ein Widget später zu ändern, lade es aus dem Dateifeld unten herunter, hänge es zusammen mit diesen Anweisungen an einen neuen Chat an und sag, was geändert werden soll.';
$string['aiinstructions_intro'] = 'Erstelle das Widget in ChatGPT, Claude oder Gemini, wo du es ausprobieren und Änderungen anfordern kannst:';
$string['aiinstructions_show'] = 'Anweisungen anzeigen';
$string['aiinstructions_step1'] = 'Füge diese Anweisungen in einen neuen Chat ein, oder lade sie herunter und hänge die Datei an.';
$string['aiinstructions_step2'] = 'Beschreib die Aktivität. Zum Beispiel: „Ein Quiz zur Vergangenheitsform von 10 häufigen Verben, mit Anweisungen auf Englisch und Japanisch, das eine Punktzahl meldet.“';
$string['aiinstructions_step3'] = 'Probier es im Chat aus und fordere Änderungen an, bis du zufrieden bist.';
$string['aiinstructions_step4'] = 'Schreib „Export it for Poodll“, lade die Datei herunter, die du bekommst, und lade sie unten hoch.';
$string['error:htmlencoding'] = 'Die HTML-Datei muss als UTF-8-Text gespeichert sein.';
$string['error:importbase64'] = 'Die Widget-Datei {$a} ist kein gültiges Base64. Kodiere die Bytes der Datei als einfaches Base64, ohne „data:“-Präfix.';
$string['error:importboth'] = 'Gib das Widget entweder als widgethtml oder als Datei in customfile1 an, nicht beides.';
$string['error:importgradepolicy'] = 'gradepolicy muss 0 (Punktzahl des Widgets ignorieren), 1 (speichern) oder 2 (in die Lektionsbewertung einrechnen) sein.';
$string['error:importnofile'] = 'Das Element hat kein Widget. Füge das HTML des Widgets in sein Feld widgethtml ein.';
$string['error:importonefile'] = 'Das Element hat mehr als eine Datei im Dateibereich customfile1. Füge nur eine Widget-Datei hinzu (.html oder .zip).';
$string['error:manifestentry'] = 'Der „entry“ des Manifests ({$a}) muss eine HTML-Datei im ZIP angeben.';
$string['error:manifestfield'] = 'Der Wert „{$a}“ im Manifest fehlt oder ist ungültig.';
$string['error:manifestjson'] = 'Das Manifest des Widgets ist kein gültiges JSON.';
$string['error:manifestprotocol'] = 'Dieses Widget braucht einen neueren Lektions-Host. Dieser Host verwendet Protokollversion {$a}.';
$string['error:manifestschema'] = 'Die „schemaVersion“ des Manifests muss {$a} sein.';
$string['error:nomanifest'] = 'Das ZIP hat keine manifest.json auf der obersten Ebene.';
$string['error:nomanifesthtml'] = 'Die HTML-Datei hat kein Widget-Manifest. Sie braucht einen &lt;script type="application/json" id="poodll-widget-manifest"&gt;-Block. Bitte das KI-Tool: „export it for Poodll“.';
$string['error:notazip'] = 'Die Datei ist keine ZIP-Datei oder sie ist beschädigt.';
$string['error:notpublished'] = 'Dieses Widget konnte nicht veröffentlicht werden, daher sehen die Lernenden es nicht. Speichere das Element erneut, um es noch einmal zu versuchen.';
$string['error:onezip'] = 'Lade nur eine Widget-Datei hoch. Lösche die anderen.';
$string['error:publishfailed'] = 'Das Widget konnte nicht veröffentlicht werden.';
$string['error:zipbadpath'] = 'Das ZIP enthält einen nicht erlaubten Dateinamen: {$a}';
$string['error:zipduplicate'] = 'Das ZIP enthält zwei Dateien mit demselben Namen: {$a}';
$string['error:zipencrypted'] = 'Das ZIP ist passwortgeschützt. Lade ein ZIP ohne Passwort hoch.';
$string['error:zipfiletoobig'] = 'Die Datei {$a->file} ist zu groß. Die größte erlaubte Datei ist {$a->size}.';
$string['error:zipfiletype'] = 'Dieser Dateityp ist in einem Widget nicht erlaubt: {$a}';
$string['error:zipread'] = 'Die Datei {$a} konnte nicht aus dem ZIP gelesen werden.';
$string['error:zipsymlink'] = 'Das ZIP enthält einen Link, was nicht erlaubt ist: {$a}';
$string['error:ziptoobig'] = 'Das ZIP ist zu groß. Das größte erlaubte ZIP ist {$a}.';
$string['error:ziptoomanyfiles'] = 'Das ZIP enthält zu viele Dateien. Erlaubt sind höchstens {$a}.';
$string['error:ziptotaltoobig'] = 'Die Dateien im ZIP sind insgesamt zu groß. Erlaubt sind höchstens {$a}.';
$string['error:zipunreadable'] = 'Das ZIP konnte nicht gelesen werden.';
$string['gradepolicy'] = 'Punktzahl des Widgets';
$string['gradepolicy_help'] = 'Manche Widgets melden eine Punktzahl, zum Beispiel 3 von 4. Wähle, was die Lektion damit macht.

* Ignorieren: Die Punktzahl wird nicht verwendet.
* Speichern, aber nicht bewerten: Die Punktzahl wird mit dem Versuch gespeichert, zählt aber nicht zur Lektionsbewertung.
* Als Bewertung des Elements verwenden: Die Punktzahl zählt zur Lektionsbewertung, umgerechnet auf die Gesamtpunktzahl. Wer weitergeht, bevor er fertig ist, bekommt 0.

Eine vom Widget gesendete Punktzahl wird im Browser der Lernenden berechnet, sie könnten sie also ändern. Nutze sie zum Üben, nicht für wichtige Prüfungen.';
$string['gradepolicy_ignore'] = 'Ignorieren';
$string['gradepolicy_record'] = 'Speichern, aber nicht bewerten';
$string['gradepolicy_use'] = 'Als Bewertung des Elements verwenden';
$string['item_desc'] = 'Smart Frame lädt eine hochgeladene HTML-Seite in einen Rahmen innerhalb der Minilesson. Es ist responsiv und sicher und kann eine Bewertung erzeugen. Es eignet sich für Erklär-Widgets, eigene interaktive Aktivitäten oder sogar Spiele.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Sprache des Widgets';
$string['uilanguage_auto'] = 'Automatisch';
$string['uilanguage_help'] = 'Die Sprache der Anweisungen, Buttons und Rückmeldungen des Widgets. Die Lernsprache ändert sich dadurch nicht.

Automatisch verwendet die eigene Sprache der Lernenden, wenn das Widget sie anbietet, dann die Muttersprache der Lektion, dann die Moodle-Sprache der Lernenden und zuletzt die Standardsprache des Widgets. Speichere das Element einmal, um aus den Sprachen des Widgets wählen zu können.';
$string['warning:importuilanguage'] = 'uilanguage „{$a->lang}“ ist keine der Oberflächensprachen des Widgets ({$a->languages}), daher sehen die Lernenden seine Standardsprache.';
$string['widget_instructions1'] = 'Erledige die Aktivität unten.';
$string['widgetnotavailable'] = 'Diese Aktivität ist gerade nicht verfügbar.';
$string['widgetpackage'] = 'Widget-Datei';
$string['widgetpackage_help'] = 'Das Widget: entweder eine HTML-Datei (das, was KI-Tools am leichtesten erstellen) oder ein ZIP für ein Widget mit Audio, Video oder vielen Bildern.

Eine HTML-Datei muss das Manifest des Widgets in einem &lt;script type="application/json" id="poodll-widget-manifest"&gt;-Block enthalten. Ein ZIP muss auf der obersten Ebene eine manifest.json und eine HTML-Seite haben. Alles, was das Widget nutzt, muss in der Datei oder im ZIP sein: Ein Widget kann keine Dateien von anderen Websites laden und keinen Browserspeicher (wie localStorage) oder Cookies verwenden.';
$string['widgetsummary'] = 'Aktuelles Widget';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Sprachen: {$a->languages}<br>Meldet eine Punktzahl: {$a->grade}';
