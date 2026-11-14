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
 * Spanish strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Lectura rápida';
$string['answercorrect'] = 'Correcto';
$string['answersneeded'] = 'Responde todas las preguntas para enviar. Puedes cambiar una respuesta hasta que envíes.';
$string['answerwrong'] = 'Incorrecto';
$string['cannotgoback'] = 'No puedes volver al texto.';
$string['charactercount'] = '{$a} caracteres';
$string['charactersperminute'] = 'caracteres por minuto';
$string['correctansweris'] = 'Respuesta correcta:';
$string['defaultinstructions'] = 'Lee el texto lo más rápido que puedas. Después responde las preguntas.';
$string['error:correctanswertoohigh'] = 'El número de la respuesta correcta es mayor que el número de respuestas.';
$string['error:needtwoanswers'] = 'Escribe al menos dos respuestas, una por línea.';
$string['error:passmark'] = 'Escribe un número del 0 al 100.';
$string['error:targetwpm'] = 'Escribe un número mayor que 0.';
$string['error:wordcount'] = 'Escribe 0 o más. Déjalo vacío para contar automáticamente.';
$string['finishedreading'] = 'He terminado de leer';
$string['formexplainer'] = 'Los estudiantes ven cuatro pantallas. El cronómetro empieza cuando tocan «Empezar a leer» y se detiene cuando tocan «He terminado de leer». No pueden volver al texto. Cada sección de abajo configura una pantalla.';
$string['formexplainer_heading'] = 'Cómo funciona este elemento';
$string['formexplainer_step1'] = 'El título, las instrucciones, consejos y una imagen opcional. Los estudiantes tocan «Empezar a leer».';
$string['formexplainer_step2'] = 'El texto y el cronómetro. El botón para terminar está al final del texto.';
$string['formexplainer_step3'] = 'Hasta 5 preguntas de opción múltiple. Los estudiantes no ven si aciertan hasta los resultados.';
$string['formexplainer_step4'] = 'Velocidad de lectura, puntos de velocidad, puntuación del cuestionario, respuestas con retroalimentación y el texto.';
$string['gradeitem'] = 'Calificar este elemento';
$string['gradeitem_details'] = 'La calificación es la puntuación del cuestionario. La velocidad de lectura se muestra al estudiante, pero no forma parte de la calificación.';
$string['instructions'] = 'Instrucciones';
$string['intronote'] = 'El cronómetro empieza cuando tocas «Empezar a leer». Cuando termines, no podrás volver al texto.';
$string['introtext'] = 'Texto de introducción';
$string['introtext_details'] = 'Las instrucciones son una línea corta bajo el título. El texto de introducción puede tener consejos, y la imagen es opcional. Se añade automáticamente una nota que dice que el cronómetro empieza cuando el estudiante toca «Empezar a leer» y que no puede volver al texto.';
$string['item_desc'] = 'Práctica de lectura rápida. El estudiante lee un texto mientras corre el cronómetro y luego responde preguntas sobre él. Ve su velocidad de lectura en palabras por minuto.';
$string['minsec'] = '{$a->minutes} min {$a->seconds} s';
$string['noanswer'] = 'Sin respuesta';
$string['notread'] = 'No terminaste de leer, así que no hay velocidad de lectura.';
$string['passage'] = 'Texto';
$string['passage_details'] = 'Elige un texto fácil: los estudiantes deberían conocer casi todas las palabras. La lectura rápida practica leer deprisa, no aprender palabras nuevas.';
$string['passagestatus'] = '{$a->count} palabras. A la velocidad objetivo de {$a->target} palabras por minuto, se tarda unos {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} caracteres. A la velocidad objetivo de {$a->target} caracteres por minuto, se tarda unos {$a->time}.';
$string['passmark'] = 'Mínimo para que cuente la velocidad';
$string['passmark_details'] = 'La velocidad de lectura solo cuenta cuando al menos este porcentaje de las preguntas es correcto.';
$string['passmarkstatus'] = 'Con {$a->questions} preguntas, los estudiantes necesitan {$a->needed} correctas.';
$string['picture'] = 'Imagen';
$string['pluginname'] = 'Lectura rápida';
$string['questionanswers'] = 'Respuestas (una por línea)';
$string['questioncount'] = '{$a} preguntas';
$string['questionfeedback'] = 'Retroalimentación (opcional)';
$string['questionno'] = 'Pregunta {$a}';
$string['questionofn'] = 'Pregunta {$a->number} de {$a->total}';
$string['questions'] = 'Preguntas';
$string['questions_details'] = 'Rellena de 1 a 5 preguntas. Los estudiantes no ven si aciertan hasta la pantalla de resultados. La retroalimentación solo se muestra en la pantalla de resultados.';
$string['questiontext'] = 'Texto de la pregunta';
$string['readingspeed'] = 'Tu velocidad de lectura';
$string['readtime'] = '{$a->count} palabras en {$a->minutes} min {$a->seconds} s';
$string['readtime_cpm'] = '{$a->count} caracteres en {$a->minutes} min {$a->seconds} s';
$string['screen1'] = 'Pantalla 1: Introducción';
$string['screen2'] = 'Pantalla 2: Texto de lectura';
$string['screen3'] = 'Pantalla 3: Cuestionario';
$string['screen4'] = 'Pantalla 4: Resultados y calificación';
$string['showallquestions'] = 'Mostrar todas las preguntas a la vez';
$string['showallquestions_details'] = 'Desactivado: una pregunta cada vez, y un toque pasa a la siguiente. Activado: todas las preguntas en una página, con un botón para enviar.';
$string['showtimer'] = 'Mostrar el cronómetro durante la lectura';
$string['showtimer_details'] = 'El tiempo se registra de todos modos. Oculta el cronómetro si pone nerviosos a los estudiantes.';
$string['speedcounts'] = 'Tu velocidad cuenta. Necesitabas {$a->needed} de {$a->total} correctas y tuviste {$a->correct}.';
$string['speeddoesnotcount'] = 'Esta vez tu velocidad no cuenta. Necesitabas {$a->needed} de {$a->total} correctas y tuviste {$a->correct}. La próxima vez lee un poco más despacio para entender más.';
$string['speedpoints'] = 'Puntos de velocidad';
$string['speedreading'] = 'Lectura rápida';
$string['startreading'] = 'Empezar a leer';
$string['submitanswers'] = 'Enviar respuestas';
$string['tapnext'] = 'Toca una respuesta para pasar a la siguiente pregunta. Verás tus resultados al final.';
$string['targetis'] = 'Objetivo: {$a}';
$string['targetwpm'] = 'Velocidad objetivo';
$string['targetwpm_details'] = 'Esta velocidad o más rápido da 10 puntos de velocidad. La mitad de esta velocidad o más lento da 0.';
$string['thepassage'] = 'El texto';
$string['timerrunning'] = 'Cronómetro en marcha';
$string['toofast'] = 'Fue demasiado rápido para ser una lectura real, así que tu velocidad no cuenta. La próxima vez lee todo el texto.';
$string['wordcount'] = 'Número de palabras';
$string['wordcount_details'] = 'Déjalo vacío para contar las palabras automáticamente. Rellénalo para fijar un número de palabras concreto.<br>Nota: en japonés, chino, coreano y tailandés contamos caracteres (no palabras).';
$string['wordcountshort'] = '{$a} palabras';
$string['wordsperminute'] = 'palabras por minuto';
$string['youranswer'] = 'Tu respuesta:';
$string['youranswers'] = 'Tus respuestas';
