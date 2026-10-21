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
 * French strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Chat audio';
$string['audiochat_silenceduration'] = 'Pause avant la réponse de l\'IA';
$string['audiochat_silenceduration_details'] = 'Durée pendant laquelle l\'apprenant doit cesser de parler, en millisecondes, avant que l\'IA considère son tour comme terminé et réponde. Augmente-la pour laisser plus de temps aux apprenants de marquer une pause et de réfléchir au milieu d\'une phrase. Diminue-la pour des échanges plus rapides et plus conversationnels. La valeur par défaut est 3500 (3,5 secondes). Les valeurs en dehors de la plage 500 à 30000 reviennent à la valeur par défaut. Cela ne s\'applique qu\'après que l\'apprenant a parlé, et uniquement lorsque l\'option Envoi automatique est activée pour l\'élément.';
$string['cantchat_ningxia'] = 'Le chat n\'est PAS disponible. Pour utiliser ce type d\'élément, une clé {$a} valide doit être définie dans les paramètres d\'administration de l\'activité MiniLesson. Le chat n\'est pas non plus disponible en Chine (région de Ningxia)';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Modèle Gemini Live';
$string['geminilivemodel_details'] = 'Le modèle Gemini Live utilisé lorsque le fournisseur est Gemini. Gemini 3.8 Live est la valeur par défaut actuelle de Google pour la voix à faible latence. La préversion 3.1 est l\'ancien modèle et Google recommande de l\'abandonner. Cela s\'applique aux fournisseurs Gemini et Cloud Poodll, pas à OpenAI.';
$string['gradingfailed'] = 'L\'IA n\'a pas pu noter ce chat, il obtient donc 0. La transcription a été enregistrée.';
$string['item_desc'] = 'L\'élément Chat audio met en place une conversation orale avec un partenaire IA. Il peut servir à discuter d\'un sujet ou à poser des questions sur une image. Les consignes données au robot IA sont du texte libre et modifiable.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Chat audio';
$string['provider'] = 'Fournisseur';
$string['provider_details'] = 'Sélectionne un fournisseur d\'IA pour l\'élément Chat audio. Cloud Poodll devrait fonctionner immédiatement, mais pas dans la région de Ningxia. Pour les autres, tu dois fournir une clé API dans les paramètres d\'administration BYOK de minilesson.';
$string['unknown'] = 'Inconnu';
