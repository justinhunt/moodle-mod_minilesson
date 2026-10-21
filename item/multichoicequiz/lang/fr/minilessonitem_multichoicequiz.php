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
 * French strings for minilessonitem_multichoicequiz
 *
 * @package    minilessonitem_multichoicequiz
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Quiz à choix multiple';
$string['allowretry_details'] = 'En cas de mauvaise réponse, permet à l\'apprenant de continuer à choisir jusqu\'à trouver la bonne réponse. La question reste comptée comme incorrecte.';
$string['error:correctanswertoohigh'] = 'Le numéro de la bonne réponse est supérieur au nombre de réponses.';
$string['error:needtwoanswers'] = 'Saisis au moins deux réponses, une par ligne.';
$string['hideanswertext_detail'] = 'Pour les quiz d\'écoute où les options de réponse sont énoncées dans l\'audio et désignées par A, B, C ou D, choisis « Afficher A, B, C, D à la place du texte » pour afficher ces lettres sur les boutons de réponse. Le texte de la réponse reste affiché sur les écrans de résultats. Les réponses ne sont pas mélangées dans ce mode, de sorte que les lettres correspondent toujours à l\'audio.';
$string['item_desc'] = 'Un ensemble de questions à choix multiple portant sur une même ressource, par exemple un texte à lire. Les questions s\'affichent une par une et la ressource reste à l\'écran.';
$string['multichoicequiz_instructions1'] = 'Lis le passage et réponds aux questions.';
$string['pluginname'] = 'Quiz à choix multiple';
$string['questionanswers'] = 'Réponses (une par ligne)';
$string['questionfeedback'] = 'Feedback (facultatif)';
$string['questionfeedback_details'] = 'Feedback expliquant la bonne réponse. Il s\'affiche dès que la question a reçu une réponse, puis à nouveau dans les résultats. Si une question de ce quiz a un feedback, le quiz attend que l\'apprenant appuie sur « Continuer » après chaque question, au lieu d\'avancer tout seul. N\'oublie pas que dans le mode « Afficher A, B, C, D à la place du texte », le feedback est visible alors que le texte de la réponse ne l\'est pas ; fais donc attention à ne pas donner la réponse.';
$string['questionno'] = 'Question {$a}';
$string['questiontext'] = 'Texte de la question';
$string['showallquestions'] = 'Afficher toutes les questions en même temps';
$string['showallquestions_details'] = 'Affiche toutes les questions de la page en même temps, au lieu d\'une à la fois. Chaque question est toujours vérifiée dès qu\'elle reçoit une réponse. L\'option « Confirmer le choix » ne s\'applique pas dans ce mode.';
