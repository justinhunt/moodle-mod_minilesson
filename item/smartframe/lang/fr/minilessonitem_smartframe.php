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
 * French strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Crée un widget avec l\'IA';
$string['aiinstructions_copied'] = 'Instructions pour l\'IA copiées';
$string['aiinstructions_copy'] = 'Copier les instructions pour l\'IA';
$string['aiinstructions_download'] = 'Télécharger';
$string['aiinstructions_edit'] = 'Pour modifier un widget plus tard, télécharge-le depuis la zone de fichiers ci-dessous, joins-le à une nouvelle conversation avec ces instructions et dis ce qu\'il faut changer.';
$string['aiinstructions_intro'] = 'Crée le widget dans ChatGPT, Claude ou Gemini, où tu peux l\'essayer et demander des modifications :';
$string['aiinstructions_show'] = 'Afficher les instructions';
$string['aiinstructions_step1'] = 'Colle ces instructions dans une nouvelle conversation, ou télécharge-les et joins le fichier.';
$string['aiinstructions_step2'] = 'Décris l\'activité. Par exemple : « Un quiz sur le passé de 10 verbes courants, avec des consignes en anglais et en japonais, qui renvoie un score. »';
$string['aiinstructions_step3'] = 'Essaie-le dans la conversation et demande des modifications jusqu\'à ce qu\'il te convienne.';
$string['aiinstructions_step4'] = 'Écris « Export it for Poodll », télécharge le fichier qu\'il te donne et téléverse-le ci-dessous.';
$string['error:htmlencoding'] = 'Le fichier HTML doit être enregistré en texte UTF-8.';
$string['error:importbase64'] = 'Le fichier du widget {$a} n\'est pas un base64 valide. Encode les octets du fichier en base64 simple, sans préfixe « data: ».';
$string['error:importboth'] = 'Fournis le widget soit en widgethtml, soit en fichier dans customfile1, pas les deux.';
$string['error:importgradepolicy'] = 'gradepolicy doit valoir 0 (ignorer le score du widget), 1 (l\'enregistrer) ou 2 (le compter dans la note de la leçon).';
$string['error:importnofile'] = 'L\'élément n\'a pas de widget. Mets le HTML du widget dans son champ widgethtml.';
$string['error:importonefile'] = 'L\'élément a plus d\'un fichier dans sa zone de fichiers customfile1. Ajoute un seul fichier de widget (.html ou .zip).';
$string['error:manifestentry'] = 'Le « entry » du manifeste ({$a}) doit désigner un fichier HTML du ZIP.';
$string['error:manifestfield'] = 'Le « {$a} » du manifeste est absent ou non valide.';
$string['error:manifestjson'] = 'Le manifeste du widget n\'est pas un JSON valide.';
$string['error:manifestprotocol'] = 'Ce widget a besoin d\'un hôte de leçon plus récent. Cet hôte utilise la version de protocole {$a}.';
$string['error:manifestschema'] = 'Le « schemaVersion » du manifeste doit valoir {$a}.';
$string['error:nomanifest'] = 'Le ZIP n\'a pas de manifest.json à sa racine.';
$string['error:nomanifesthtml'] = 'Le fichier HTML n\'a pas de manifeste de widget. Il lui faut un bloc &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Demande à l\'outil d\'IA : « export it for Poodll ».';
$string['error:notazip'] = 'Le fichier n\'est pas un ZIP, ou il est endommagé.';
$string['error:notpublished'] = 'Ce widget n\'a pas pu être publié, les apprenants ne peuvent donc pas le voir. Enregistre de nouveau l\'élément pour réessayer.';
$string['error:onezip'] = 'Téléverse un seul fichier de widget. Supprime les autres.';
$string['error:publishfailed'] = 'Le widget n\'a pas pu être publié.';
$string['error:zipbadpath'] = 'Le ZIP contient un nom de fichier non autorisé : {$a}';
$string['error:zipduplicate'] = 'Le ZIP contient deux fichiers portant le même nom : {$a}';
$string['error:zipencrypted'] = 'Le ZIP est protégé par un mot de passe. Téléverse un ZIP sans mot de passe.';
$string['error:zipfiletoobig'] = 'Le fichier {$a->file} est trop volumineux. La taille maximale autorisée est {$a->size}.';
$string['error:zipfiletype'] = 'Ce type de fichier n\'est pas autorisé dans un widget : {$a}';
$string['error:zipread'] = 'Le fichier {$a} n\'a pas pu être lu dans le ZIP.';
$string['error:zipsymlink'] = 'Le ZIP contient un lien, ce qui n\'est pas autorisé : {$a}';
$string['error:ziptoobig'] = 'Le ZIP est trop volumineux. La taille maximale autorisée est {$a}.';
$string['error:ziptoomanyfiles'] = 'Le ZIP contient trop de fichiers. Le maximum autorisé est {$a}.';
$string['error:ziptotaltoobig'] = 'Les fichiers du ZIP sont trop volumineux au total. Le maximum autorisé est {$a}.';
$string['error:zipunreadable'] = 'Le ZIP n\'a pas pu être lu.';
$string['gradepolicy'] = 'Score du widget';
$string['gradepolicy_help'] = 'Certains widgets renvoient un score, par exemple 3 sur 4. Choisis ce que la leçon en fait.

* Ignorer : le score n\'est pas utilisé.
* Enregistrer sans noter : le score est enregistré avec la tentative, mais ne compte pas dans la note de la leçon.
* Utiliser comme note de l\'élément : le score compte dans la note de la leçon, ramené au total des points. Un apprenant qui passe à la suite avant d\'avoir fini obtient 0.

Un score envoyé par le widget est calculé dans le navigateur de l\'apprenant, qui pourrait donc le modifier. Utilise-le pour l\'entraînement, pas pour des évaluations importantes.';
$string['gradepolicy_ignore'] = 'Ignorer';
$string['gradepolicy_record'] = 'Enregistrer sans noter';
$string['gradepolicy_use'] = 'Utiliser comme note de l\'élément';
$string['item_desc'] = 'Smart Frame charge une page HTML déposée dans un cadre au sein de la Minilesson. Il est adaptatif, sécurisé et peut produire une note. Il peut servir pour des widgets explicatifs, des activités interactives sur mesure, ou même des jeux.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Langue du widget';
$string['uilanguage_auto'] = 'Automatique';
$string['uilanguage_help'] = 'La langue des consignes, des boutons et du feedback du widget. Elle ne change pas la langue apprise.

Automatique utilise la langue de l\'apprenant si le widget la propose, sinon la langue maternelle de la leçon, puis la langue Moodle de l\'apprenant, puis la langue par défaut du widget. Enregistre l\'élément une fois pour pouvoir choisir parmi les langues du widget.';
$string['warning:importuilanguage'] = 'uilanguage « {$a->lang} » ne fait pas partie des langues d\'interface du widget ({$a->languages}), les apprenants verront donc sa langue par défaut.';
$string['widget_instructions1'] = 'Termine l\'activité ci-dessous.';
$string['widgetnotavailable'] = 'Cette activité n\'est pas disponible pour le moment.';
$string['widgetpackage'] = 'Fichier du widget';
$string['widgetpackage_help'] = 'Le widget : soit un seul fichier HTML (ce que les outils d\'IA produisent le plus facilement), soit un ZIP pour un widget avec de l\'audio, de la vidéo ou beaucoup d\'images.

Un fichier HTML doit contenir le manifeste du widget dans un bloc &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Un ZIP doit avoir un manifest.json et une page HTML à sa racine. Tout ce qu\'utilise le widget doit se trouver dans le fichier ou le ZIP : un widget ne peut pas charger de fichiers depuis d\'autres sites, ni utiliser le stockage du navigateur (comme localStorage) ou les cookies.';
$string['widgetsummary'] = 'Widget actuel';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Langues : {$a->languages}<br>Renvoie un score : {$a->grade}';
