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
 * Spanish strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Video-shadowing';
$string['aihelper_placeholder_shadow'] = 'p. ej. Añade la puntuación y corrige los errores de ortografía y mayúsculas';
$string['enablesubtitlefetch'] = 'Activar el botón de obtener subtítulos';
$string['enablesubtitlefetch_details'] = 'Muestra un botón «Obtener subtítulos» en el formulario del elemento que descarga los subtítulos de un vídeo de YouTube al editor de subtítulos. Nota: la obtención de subtítulos no siempre funciona y puede dejar de hacerlo en cualquier momento. Es una herramienta auxiliar que Poodll no garantiza que esté siempre disponible.';
$string['error:badshadowlines'] = 'Las líneas para hacer shadowing deben ser * (todas las líneas) o una lista de números de línea separados por comas, p. ej. 1,4,5,6.';
$string['error:badtimestamp'] = 'Las horas de inicio y fin del clip deben tener el formato hh:mm:ss, p. ej. 00:01:30.';
$string['error:badvtt'] = 'No se pudieron analizar los subtítulos. Introduce un WebVTT válido con al menos una entrada con marca de tiempo.';
$string['error:nocuesinclip'] = 'Ninguna línea de subtítulo queda completamente dentro de las horas de inicio y fin del clip. Ajusta las horas o los subtítulos.';
$string['error:noshadowlines'] = 'Ninguno de los números de línea seleccionados coincide con una línea de subtítulo dentro de las horas de inicio y fin del clip.';
$string['error:novideoid'] = 'Se requiere un ID o una URL de vídeo de YouTube.';
$string['error:startafterend'] = 'La hora de fin del clip debe ser posterior a la de inicio.';
$string['error:subtitlefetchdisabled'] = 'La obtención de subtítulos está desactivada en este sitio.';
$string['fetchvtt'] = 'Obtener subtítulos';
$string['fetchvtt_disabled'] = 'La obtención automática de subtítulos está desactivada actualmente';
$string['fetchvtt_failed'] = 'No se pudieron obtener los subtítulos de YouTube.';
$string['fetchvtt_fetching'] = 'Obteniendo…';
$string['fetchvtt_invalidurl'] = 'Introduce primero una URL de YouTube válida o un ID de vídeo de 11 caracteres.';
$string['fetchvtt_overwrite'] = 'Esto reemplazará los subtítulos que hay ahora en el editor. ¿Continuar?';
$string['fetchvtt_overwrite_title'] = '¿Reemplazar los subtítulos?';
$string['item_desc'] = 'El elemento Video-shadowing reproduce un clip de YouTube línea por línea. Los estudiantes hacen «shadowing» de cada línea de subtítulo, hablando junto con el vídeo mientras las palabras se resaltan.';
$string['loopcount'] = 'Repeticiones de shadowing por línea';
$string['loopcount_desc'] = 'Cuántas veces se reproduce cada línea para que el estudiante haga shadowing.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'Aceptar / Siguiente';
$string['paste_empty'] = 'Primero pega la transcripción que copiaste de YouTube.';
$string['paste_failed'] = 'No se pudo convertir la transcripción.';
$string['paste_label'] = 'Transcripción copiada de YouTube';
$string['paste_placeholder'] = '0:00
la primera línea de la transcripción
0:03
la segunda línea de la transcripción';
$string['paste_step_copy'] = 'Selecciona toda la transcripción, cópiala y pégala en el cuadro de abajo';
$string['paste_step_open_novideo'] = 'Abre el vídeo en YouTube (introduce primero el ID del vídeo en el formulario para obtener un enlace aquí)';
$string['paste_step_showtranscript'] = 'Debajo del vídeo, despliega la descripción y haz clic en «Mostrar transcripción»';
$string['paste_step_timestamps'] = 'En el menú ⋮ del panel de transcripción, asegúrate de que las marcas de tiempo están activadas';
$string['paste_success'] = 'Se añadieron {$a} líneas de subtítulos.';
$string['paste_wordhighlightoff'] = 'El resaltado palabra por palabra se ha desactivado, porque una transcripción pegada solo tiene tiempos para líneas completas. El clip resaltará una línea cada vez.';
$string['pastevtt'] = 'Pegar transcripción';
$string['pastevtt_convert'] = 'Convertir en subtítulos';
$string['pastevtt_title'] = 'Pegar transcripción de YouTube';
$string['pluginname'] = 'Video-shadowing';
$string['retry'] = 'Reintentar';
$string['rotatedevice'] = 'Gira el dispositivo a modo vertical para continuar.';
$string['shadow_instructions1'] = 'Mira el vídeo. Luego haz shadowing de cada línea: escucha y habla junto con el vídeo mientras se reproduce de nuevo.';
$string['shadowlines'] = 'Líneas para hacer shadowing';
$string['shadowlines_desc'] = 'Números de las líneas de subtítulo para hacer shadowing, contadas desde 1 en los subtítulos de abajo, p. ej. 1,4,5,6. Usa * para hacer shadowing de todas las líneas. Las demás líneas se siguen mostrando mientras se ve el vídeo.';
$string['shadowpause'] = 'Pausa entre repeticiones de shadowing (segundos)';
$string['shadowvtt'] = 'Subtítulos (WebVTT)';
$string['shadowvtt_desc'] = 'Pega o edita los subtítulos WebVTT del clip en el área de abajo.';
$string['startshadowing'] = 'Empezar el shadowing';
$string['watchhint'] = 'Pulsa reproducir y mira el clip. Cuando termine, pulsa «Empezar el shadowing».';
$string['wordhighlight'] = 'Activar el resaltado palabra por palabra';
$string['wordhighlight_details'] = 'Resalta cada palabra a medida que se dice, usando las marcas de tiempo por palabra de los subtítulos. Los tiempos por palabra de YouTube no son fiables en algunos vídeos: desactiva esto para resaltar líneas completas. Cuando está desactivado, la obtención de subtítulos también omite las marcas de tiempo por palabra.';
$string['ytclipdetails'] = 'Clip de YouTube (ID/URL, segundos de inicio y fin)';
