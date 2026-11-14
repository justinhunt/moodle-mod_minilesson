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
 * Spanish strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Crea un widget con IA';
$string['aiinstructions_copied'] = 'Instrucciones para la IA copiadas';
$string['aiinstructions_copy'] = 'Copiar las instrucciones para la IA';
$string['aiinstructions_download'] = 'Descargar';
$string['aiinstructions_edit'] = 'Para cambiar un widget más adelante, descárgalo del cuadro de archivos de abajo, adjúntalo a un chat nuevo junto con estas instrucciones y di qué quieres cambiar.';
$string['aiinstructions_intro'] = 'Crea el widget en ChatGPT, Claude o Gemini, donde puedes probarlo y pedir cambios:';
$string['aiinstructions_show'] = 'Mostrar las instrucciones';
$string['aiinstructions_step1'] = 'Pega estas instrucciones en un chat nuevo, o descárgalas y adjunta el archivo.';
$string['aiinstructions_step2'] = 'Describe la actividad. Por ejemplo: «Un cuestionario sobre el pasado de 10 verbos comunes, con instrucciones en inglés y japonés, que informe de una puntuación».';
$string['aiinstructions_step3'] = 'Pruébalo en el chat y pide cambios hasta que te guste.';
$string['aiinstructions_step4'] = 'Escribe «Export it for Poodll», descarga el archivo que te dé y súbelo abajo.';
$string['error:htmlencoding'] = 'El archivo HTML debe guardarse como texto UTF-8.';
$string['error:importbase64'] = 'El archivo del widget {$a} no es base64 válido. Codifica los bytes del archivo como base64 simple, sin el prefijo «data:».';
$string['error:importboth'] = 'Da el widget como widgethtml o como un archivo en customfile1, no ambos.';
$string['error:importgradepolicy'] = 'gradepolicy debe ser 0 (ignorar la puntuación del widget), 1 (guardarla) o 2 (contarla en la calificación de la lección).';
$string['error:importnofile'] = 'El elemento no tiene widget. Pon el HTML del widget en su campo widgethtml.';
$string['error:importonefile'] = 'El elemento tiene más de un archivo en su área de archivos customfile1. Añade un solo archivo de widget (.html o .zip).';
$string['error:manifestentry'] = 'El «entry» del manifiesto ({$a}) debe indicar un archivo HTML del ZIP.';
$string['error:manifestfield'] = 'Falta el «{$a}» del manifiesto o no es válido.';
$string['error:manifestjson'] = 'El manifiesto del widget no es JSON válido.';
$string['error:manifestprotocol'] = 'Este widget necesita un anfitrión de lecciones más reciente. Este anfitrión usa la versión de protocolo {$a}.';
$string['error:manifestschema'] = 'El «schemaVersion» del manifiesto debe ser {$a}.';
$string['error:nomanifest'] = 'El ZIP no tiene un manifest.json en su nivel superior.';
$string['error:nomanifesthtml'] = 'El archivo HTML no tiene manifiesto de widget. Necesita un bloque &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Pide a la herramienta de IA que haga «export it for Poodll».';
$string['error:notazip'] = 'El archivo no es un ZIP o está dañado.';
$string['error:notpublished'] = 'Este widget no se pudo publicar, así que los estudiantes no pueden verlo. Guarda el elemento otra vez para volver a intentarlo.';
$string['error:onezip'] = 'Sube un solo archivo de widget. Borra los demás.';
$string['error:publishfailed'] = 'No se pudo publicar el widget.';
$string['error:zipbadpath'] = 'El ZIP contiene un nombre de archivo no permitido: {$a}';
$string['error:zipduplicate'] = 'El ZIP contiene dos archivos con el mismo nombre: {$a}';
$string['error:zipencrypted'] = 'El ZIP está protegido con contraseña. Sube un ZIP sin contraseña.';
$string['error:zipfiletoobig'] = 'El archivo {$a->file} es demasiado grande. El tamaño máximo es {$a->size}.';
$string['error:zipfiletype'] = 'Este tipo de archivo no está permitido en un widget: {$a}';
$string['error:zipread'] = 'No se pudo leer el archivo {$a} del ZIP.';
$string['error:zipsymlink'] = 'El ZIP contiene un enlace, lo cual no está permitido: {$a}';
$string['error:ziptoobig'] = 'El ZIP es demasiado grande. El tamaño máximo del ZIP es {$a}.';
$string['error:ziptoomanyfiles'] = 'El ZIP contiene demasiados archivos. El máximo permitido es {$a}.';
$string['error:ziptotaltoobig'] = 'Los archivos del ZIP son demasiado grandes en total. El máximo permitido es {$a}.';
$string['error:zipunreadable'] = 'No se pudo leer el ZIP.';
$string['gradepolicy'] = 'Puntuación del widget';
$string['gradepolicy_help'] = 'Algunos widgets informan de una puntuación, como 3 de 4. Elige qué hace la lección con ella.

* Ignorar: la puntuación no se usa.
* Guardar, pero no calificar: la puntuación se guarda con el intento, pero no cuenta en la calificación de la lección.
* Usar como calificación del elemento: la puntuación cuenta en la calificación de la lección, ajustada a la puntuación total. Un estudiante que avanza antes de terminar obtiene 0.

La puntuación que envía el widget se calcula en el navegador del estudiante, así que un estudiante podría cambiarla. Úsala para practicar, no para pruebas importantes.';
$string['gradepolicy_ignore'] = 'Ignorar';
$string['gradepolicy_record'] = 'Guardar, pero no calificar';
$string['gradepolicy_use'] = 'Usar como calificación del elemento';
$string['item_desc'] = 'Smart Frame carga una página HTML subida en un marco dentro de la Minilesson. Es adaptable, seguro y puede generar una calificación. Se puede usar para widgets explicativos, actividades interactivas personalizadas o incluso juegos.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Idioma del widget';
$string['uilanguage_auto'] = 'Automático';
$string['uilanguage_help'] = 'El idioma de las instrucciones, los botones y la retroalimentación del widget. No cambia el idioma que se aprende.

Automático usa el idioma propio del estudiante si el widget lo ofrece; si no, la lengua materna de la lección; después, el idioma de Moodle del estudiante, y por último el idioma predeterminado del widget. Guarda el elemento una vez para poder elegir entre los idiomas del widget.';
$string['warning:importuilanguage'] = 'uilanguage «{$a->lang}» no es uno de los idiomas de la interfaz del widget ({$a->languages}), así que los estudiantes verán su idioma predeterminado.';
$string['widget_instructions1'] = 'Completa la actividad de abajo.';
$string['widgetnotavailable'] = 'Esta actividad no está disponible ahora mismo.';
$string['widgetpackage'] = 'Archivo del widget';
$string['widgetpackage_help'] = 'El widget: un solo archivo HTML (lo que las herramientas de IA hacen con más facilidad) o un ZIP para un widget con audio, vídeo o muchas imágenes.

Un archivo HTML debe incluir el manifiesto del widget en un bloque &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Un ZIP debe tener un manifest.json y una página HTML en su nivel superior. Todo lo que use el widget debe estar en el archivo o en el ZIP: un widget no puede cargar archivos de otros sitios ni usar el almacenamiento del navegador (como localStorage) ni cookies.';
$string['widgetsummary'] = 'Widget actual';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Idiomas: {$a->languages}<br>Informa de una puntuación: {$a->grade}';
