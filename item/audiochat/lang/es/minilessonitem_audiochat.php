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
 * Spanish strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Chat de voz';
$string['audiochat_silenceduration'] = 'Pausa antes de que responda la IA';
$string['audiochat_silenceduration_details'] = 'Cuánto tiempo debe dejar de hablar el estudiante, en milisegundos, antes de que la IA dé por terminado su turno y responda. Auméntalo para dar a los estudiantes más margen para hacer pausas y pensar a mitad de frase. Redúcelo para un intercambio más rápido y conversacional. El valor predeterminado es 3500 (3,5 segundos). Los valores fuera del rango de 500 a 30000 vuelven al valor predeterminado. Solo se aplica después de que el estudiante haya hablado, y solo cuando el envío automático está activado para el elemento.';
$string['cantchat_ningxia'] = 'El chat NO está disponible. Para usar este tipo de elemento necesitas una clave {$a} válida configurada en los ajustes de administración de la actividad MiniLesson. El chat tampoco está disponible en China (región de Ningxia).';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Modelo Gemini Live';
$string['geminilivemodel_details'] = 'El modelo Gemini Live que se usa cuando el proveedor es Gemini. Gemini 3.8 Live es el valor predeterminado actual de Google para voz de baja latencia. La versión preliminar 3.1 es el modelo anterior y Google recomienda dejar de usarlo. Se aplica a los proveedores Gemini y Cloud Poodll, no a OpenAI.';
$string['gradingfailed'] = 'La IA no pudo calificar este chat, por lo que obtuvo 0. La transcripción se ha guardado.';
$string['item_desc'] = 'El elemento Chat de voz configura una conversación hablada con un compañero de IA. Puede usarse para debatir un tema o hacer preguntas sobre una imagen. Los enunciados para el bot de IA son texto libre y editable.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Chat de voz';
$string['provider'] = 'Proveedor';
$string['provider_details'] = 'Selecciona un proveedor de IA para el elemento Chat de voz. Cloud Poodll debería funcionar directamente, aunque no en la región de Ningxia. Para los demás, debes proporcionar una clave de API en los ajustes de administración BYOK de MiniLesson.';
$string['unknown'] = 'Desconocido';
