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
 * French strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Lecture rapide';
$string['answercorrect'] = 'Correct';
$string['answersneeded'] = 'Réponds à toutes les questions pour envoyer. Tu peux modifier une réponse jusqu’à l’envoi.';
$string['answerwrong'] = 'Incorrect';
$string['cannotgoback'] = 'Tu ne peux pas revenir au texte.';
$string['charactercount'] = '{$a} caractères';
$string['charactersperminute'] = 'caractères par minute';
$string['correctansweris'] = 'Bonne réponse :';
$string['defaultinstructions'] = 'Lis le texte aussi vite que tu peux. Ensuite, réponds aux questions.';
$string['error:correctanswertoohigh'] = 'Le numéro de la bonne réponse dépasse le nombre de réponses.';
$string['error:needtwoanswers'] = 'Saisis au moins deux réponses, une par ligne.';
$string['error:passmark'] = 'Saisis un nombre entre 0 et 100.';
$string['error:targetwpm'] = 'Saisis un nombre supérieur à 0.';
$string['error:wordcount'] = 'Saisis 0 ou plus. Laisse vide pour compter automatiquement.';
$string['finishedreading'] = 'J’ai fini de lire';
$string['formexplainer'] = 'Les apprenants voient quatre écrans. Le chrono démarre quand ils touchent « Commencer la lecture » et s’arrête quand ils touchent « J’ai fini de lire ». Ils ne peuvent pas revenir au texte. Chaque section ci-dessous configure un écran.';
$string['formexplainer_heading'] = 'Comment fonctionne cet élément';
$string['formexplainer_step1'] = 'Le titre, les consignes, des conseils et une image facultative. Les apprenants touchent « Commencer la lecture ».';
$string['formexplainer_step2'] = 'Le texte et le chrono. Le bouton pour terminer se trouve à la fin du texte.';
$string['formexplainer_step3'] = 'Jusqu’à 5 questions à choix multiple. Les apprenants ne voient pas s’ils ont juste avant les résultats.';
$string['formexplainer_step4'] = 'Vitesse de lecture, points de vitesse, score au quiz, réponses avec feedback et le texte.';
$string['gradeitem'] = 'Noter cet élément';
$string['gradeitem_details'] = 'La note est le score au quiz. La vitesse de lecture est montrée à l’apprenant, mais elle ne compte pas dans la note.';
$string['instructions'] = 'Consignes';
$string['intronote'] = 'Le chrono démarre quand tu touches « Commencer la lecture ». Une fois fini, tu ne peux pas revenir au texte.';
$string['introtext'] = 'Texte d’introduction';
$string['introtext_details'] = 'Les consignes tiennent en une courte ligne sous le titre. Le texte d’introduction peut contenir des conseils, et l’image est facultative. Une note indiquant que le chrono démarre quand l’apprenant touche « Commencer la lecture » et qu’il ne peut pas revenir au texte est ajoutée automatiquement.';
$string['item_desc'] = 'Entraînement à la lecture rapide. L’apprenant lit un texte pendant que le chrono tourne, puis répond à des questions dessus. Il voit sa vitesse de lecture en mots par minute.';
$string['minsec'] = '{$a->minutes} min {$a->seconds} s';
$string['noanswer'] = 'Pas de réponse';
$string['notread'] = 'Tu n’as pas fini de lire, donc il n’y a pas de vitesse de lecture.';
$string['passage'] = 'Texte';
$string['passage_details'] = 'Choisis un texte facile : les apprenants doivent connaître presque tous les mots. La lecture rapide entraîne à lire vite, pas à apprendre de nouveaux mots.';
$string['passagestatus'] = '{$a->count} mots. À la vitesse cible de {$a->target} mots par minute, cela prend environ {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} caractères. À la vitesse cible de {$a->target} caractères par minute, cela prend environ {$a->time}.';
$string['passmark'] = 'Seuil pour que la vitesse compte';
$string['passmark_details'] = 'La vitesse de lecture ne compte que si au moins ce pourcentage des questions est juste.';
$string['passmarkstatus'] = 'Avec {$a->questions} questions, les apprenants doivent avoir {$a->needed} bonnes réponses.';
$string['picture'] = 'Image';
$string['pluginname'] = 'Lecture rapide';
$string['questionanswers'] = 'Réponses (une par ligne)';
$string['questioncount'] = '{$a} questions';
$string['questionfeedback'] = 'Feedback (facultatif)';
$string['questionno'] = 'Question {$a}';
$string['questionofn'] = 'Question {$a->number} sur {$a->total}';
$string['questions'] = 'Questions';
$string['questions_details'] = 'Remplis de 1 à 5 questions. Les apprenants ne voient pas s’ils ont juste avant l’écran des résultats. Le feedback n’est affiché que sur l’écran des résultats.';
$string['questiontext'] = 'Texte de la question';
$string['readingspeed'] = 'Ta vitesse de lecture';
$string['readtime'] = '{$a->count} mots en {$a->minutes} min {$a->seconds} s';
$string['readtime_cpm'] = '{$a->count} caractères en {$a->minutes} min {$a->seconds} s';
$string['screen1'] = 'Écran 1 : Introduction';
$string['screen2'] = 'Écran 2 : Texte à lire';
$string['screen3'] = 'Écran 3 : Quiz';
$string['screen4'] = 'Écran 4 : Résultats et notation';
$string['showallquestions'] = 'Afficher toutes les questions en même temps';
$string['showallquestions_details'] = 'Désactivé : une question à la fois, et un toucher fait avancer. Activé : toutes les questions sur une page, avec un bouton pour envoyer.';
$string['showtimer'] = 'Afficher le chrono pendant la lecture';
$string['showtimer_details'] = 'Le temps est enregistré dans tous les cas. Masque le chrono s’il stresse les apprenants.';
$string['speedcounts'] = 'Ta vitesse compte. Il te fallait {$a->needed} bonnes réponses sur {$a->total} et tu en as eu {$a->correct}.';
$string['speeddoesnotcount'] = 'Cette fois, ta vitesse ne compte pas. Il te fallait {$a->needed} bonnes réponses sur {$a->total} et tu en as eu {$a->correct}. La prochaine fois, lis un peu plus lentement pour mieux comprendre.';
$string['speedpoints'] = 'Points de vitesse';
$string['speedreading'] = 'Lecture rapide';
$string['startreading'] = 'Commencer la lecture';
$string['submitanswers'] = 'Envoyer les réponses';
$string['tapnext'] = 'Touche une réponse pour passer à la question suivante. Tu verras tes résultats à la fin.';
$string['targetis'] = 'Objectif : {$a}';
$string['targetwpm'] = 'Vitesse cible';
$string['targetwpm_details'] = 'Cette vitesse ou plus rapide donne 10 points de vitesse. La moitié de cette vitesse ou plus lent donne 0.';
$string['thepassage'] = 'Le texte';
$string['timerrunning'] = 'Chrono en cours';
$string['toofast'] = 'C’était trop rapide pour être une vraie lecture, donc ta vitesse ne compte pas. La prochaine fois, lis tout le texte.';
$string['wordcount'] = 'Nombre de mots';
$string['wordcount_details'] = 'Laisse vide pour compter les mots automatiquement. Remplis-le pour imposer un nombre de mots précis.<br>NB : pour le japonais, le chinois, le coréen et le thaï, on compte les caractères (pas les mots).';
$string['wordcountshort'] = '{$a} mots';
$string['wordsperminute'] = 'mots par minute';
$string['youranswer'] = 'Ta réponse :';
$string['youranswers'] = 'Tes réponses';
