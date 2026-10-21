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
 * German strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Audio-Chat';
$string['audiochat_silenceduration'] = 'Pause, bevor die KI antwortet';
$string['audiochat_silenceduration_details'] = 'Wie lange die Lernenden mit dem Sprechen aufhören müssen (in Millisekunden), bevor die KI ihren Beitrag als beendet ansieht und antwortet. Erhöhe den Wert, damit die Lernenden mitten im Satz mehr Zeit zum Innehalten und Nachdenken haben. Verringere ihn für ein schnelleres, gesprächsähnlicheres Hin und Her. Standard ist 3500 (3,5 Sekunden). Werte außerhalb des Bereichs 500 bis 30000 fallen auf den Standard zurück. Gilt nur, nachdem die Lernenden gesprochen haben, und nur wenn für das Element „Automatisch senden“ aktiviert ist.';
$string['cantchat_ningxia'] = 'Der Chat ist NICHT verfügbar. Um diesen Elementtyp zu nutzen, muss in den Administratoreinstellungen der MiniLesson-Aktivität ein gültiger {$a}-Schlüssel hinterlegt sein. Der Chat ist außerdem in China (Region Ningxia) nicht verfügbar';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini-Live-Modell';
$string['geminilivemodel_details'] = 'Das Gemini-Live-Modell, das verwendet wird, wenn der Anbieter Gemini ist. Gemini 3.8 Live ist Googles aktueller Standard für Sprache mit geringer Latenz. Die 3.1-Vorschau ist das ältere Modell, von dem Google den Umstieg empfiehlt. Gilt für die Anbieter Gemini und Cloud Poodll, nicht für OpenAI.';
$string['gradingfailed'] = 'Die KI konnte diesen Chat nicht bewerten, daher wurde er mit 0 gewertet. Das Transkript wurde gespeichert.';
$string['item_desc'] = 'Das Element Audio-Chat richtet ein Sprachgespräch mit einem KI-Partner ein. Es kann verwendet werden, um ein Thema zu besprechen oder Fragen zu einem Bild zu stellen. Die Eingaben (Prompts) für den KI-Bot sind Freitext und bearbeitbar.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Audio-Chat';
$string['provider'] = 'Anbieter';
$string['provider_details'] = 'Wähle einen KI-Anbieter für das Element Audio-Chat. Cloud Poodll sollte sofort funktionieren, allerdings nicht in der Region Ningxia. Bei den anderen musst du in den BYOK-Einstellungen der minilesson-Administration einen API-Schlüssel angeben.';
$string['unknown'] = 'Unbekannt';
