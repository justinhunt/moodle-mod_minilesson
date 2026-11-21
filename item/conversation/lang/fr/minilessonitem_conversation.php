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
 * French strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Conversation';
$string['check_auto'] = 'Vérification automatique (reconnaissance vocale)';
$string['check_self'] = 'Auto-vérification (enregistrement seul)';
$string['checking'] = 'Vérification…';
$string['checkmode'] = 'Vérification';
$string['checkmode_help'] = 'Vérification automatique : la reconnaissance vocale doit valider chaque réplique avant que la conversation continue. Auto-vérification : chaque réplique est seulement enregistrée et toujours acceptée. L\'apprenant peut la réécouter et la refaire. Les deux sont notées de la même façon : selon le nombre de ses répliques que l\'apprenant a terminées, et non selon le résultat de la reconnaissance vocale.';
$string['choosepart'] = 'Quel rôle veux-tu jouer ?';
$string['complete'] = 'Conversation terminée';
$string['conversation'] = 'Conversation';
$string['defaultinstructions'] = 'Dis tes répliques quand elles apparaissent. Écoute ton partenaire.';
$string['dialog'] = 'Conversation';
$string['dialog_help'] = 'Écris une réplique par ligne. Commence chaque ligne par A) ou B) pour indiquer qui la dit, par exemple :

A) Bonjour ! Qu\'est-ce que je vous sers aujourd\'hui ?
B) Je voudrais un grand latte, s\'il vous plaît.';
$string['error:dialogspeakers'] = 'La conversation doit avoir des répliques pour A) et pour B).';
$string['great'] = 'Bravo !';
$string['hearit'] = 'Écouter';
$string['item_desc'] = 'L\'apprenant dit l\'un des rôles d\'une conversation déjà écrite, et une voix de synthèse dit l\'autre.';
$string['labela'] = 'Nom de A';
$string['labela_help'] = 'Noms facultatifs pour les deux rôles, par exemple « Serveur » et « Client ». Ils s\'affichent dans le chat et sur l\'écran de démarrage. Si tu les laisses vides, A et B sont utilisés.';
$string['labelb'] = 'Nom de B';
$string['listen'] = 'Écoute ton partenaire…';
$string['look_dotted'] = 'Texte gris dans un cadre en pointillés';
$string['look_grey'] = 'Texte gris seul';
$string['movedon'] = 'Passée';
$string['moveon'] = 'Passer';
$string['myrecording'] = 'Mon enregistrement';
$string['playall'] = 'Écouter toute la conversation';
$string['playingback'] = 'Lecture {$a->num} sur {$a->total}';
$string['pluginname'] = 'Conversation';
$string['prompt_full'] = 'Réplique entière';
$string['prompt_hidden'] = 'Masquée';
$string['prompt_initials'] = 'Premières lettres seulement';
$string['prompthelp'] = 'Aide-mémoire';
$string['prompthelp_help'] = 'Quelle partie de la prochaine réplique de l\'apprenant afficher. Avec « Premières lettres seulement » ou « Masquée », l\'apprenant doit se souvenir de la réplique. Les mots complets s\'affichent une fois la réplique dite.';
$string['promptlook'] = 'Apparence de l\'aide-mémoire';
$string['recorded'] = 'Enregistrée';
$string['recording'] = 'Enregistrement… appuie sur le micro pour arrêter.';
$string['recordingreplaced'] = 'Enregistrement remplacé.';
$string['redo'] = 'Refaire';
$string['redohere'] = 'Appuie sur le micro pour réenregistrer cette réplique.';
$string['redowait'] = 'Attends que ton partenaire ait fini, puis appuie sur « Refaire ».';
$string['replay'] = 'Réécouter';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = 'L\'apprenant choisit';
$string['saidfirsttry'] = 'Dite du premier coup';
$string['saidintries'] = 'Dite en {$a} essais';
$string['scene'] = 'Image du décor';
$string['scene_help'] = 'Une image facultative affichée derrière le chat pour planter le décor : un café, une salle de classe, un bureau. Une seule image est utilisée. S\'il y en a deux, c\'est la première ajoutée qui est utilisée.';
$string['startagain'] = 'Recommencer';
$string['startconversation'] = 'Commencer';
$string['stopplayback'] = 'Arrêter';
$string['studentrole'] = 'Rôle de l\'apprenant';
$string['studentrole_help'] = 'Le rôle que dit l\'apprenant. Une voix de synthèse dit l\'autre rôle.';
$string['summaryauto'] = 'Répliques dites correctement : {$a->correct} sur {$a->total}';
$string['summaryself'] = 'Répliques enregistrées : {$a}. Tu peux refaire n\'importe quelle réplique.';
$string['tryagain'] = 'Presque. Redis la réplique.';
$string['voicea'] = 'Voix de A';
$string['voiceb'] = 'Voix de B';
$string['youare'] = 'Ton rôle : {$a}';
$string['yourline'] = 'Ta réplique';
$string['yourturn'] = 'À toi. Appuie sur le micro et dis ta réplique.';
