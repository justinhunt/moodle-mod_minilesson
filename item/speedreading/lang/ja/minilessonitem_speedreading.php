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
 * Japanese strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '速読';
$string['answercorrect'] = '正解';
$string['answersneeded'] = 'すべての問題に答えると送信できます。送信するまでは答えを変えられます。';
$string['answerwrong'] = '不正解';
$string['cannotgoback'] = '文章には戻れません。';
$string['charactercount'] = '{$a} 文字';
$string['charactersperminute'] = '字/分';
$string['correctansweris'] = '正解：';
$string['defaultinstructions'] = 'できるだけ速く文章を読んでください。そのあと問題に答えてください。';
$string['error:correctanswertoohigh'] = '正解の番号が解答の数より大きいです。';
$string['error:needtwoanswers'] = '解答を 2 つ以上、1 行に 1 つずつ入力してください。';
$string['error:passmark'] = '0 から 100 までの数を入力してください。';
$string['error:targetwpm'] = '0 より大きい数を入力してください。';
$string['error:wordcount'] = '0 以上を入力してください。空欄にすると自動で数えます。';
$string['finishedreading'] = '読み終わりました';
$string['formexplainer'] = '学習者には 4 つの画面が表示されます。タイマーは「読み始める」をタップすると始まり、「読み終わりました」をタップすると止まります。文章には戻れません。下の各セクションで 1 つの画面を設定します。';
$string['formexplainer_heading'] = 'このアイテムのしくみ';
$string['formexplainer_step1'] = 'タイトル、指示、ヒント、任意の画像。学習者は「読み始める」をタップします。';
$string['formexplainer_step2'] = '文章とタイマー。読み終わりのボタンは文章の最後にあります。';
$string['formexplainer_step3'] = '最大 5 問の多肢選択問題。結果が出るまで、学習者には正解かどうか表示されません。';
$string['formexplainer_step4'] = '読む速さ、スピードポイント、クイズのスコア、フィードバック付きの解答、そして文章。';
$string['gradeitem'] = 'このアイテムを採点する';
$string['gradeitem_details'] = '成績はクイズのスコアです。読む速さは学習者に表示されますが、成績には含まれません。';
$string['instructions'] = '指示';
$string['intronote'] = 'タイマーは「読み始める」をタップすると始まります。読み終わったあとは文章に戻れません。';
$string['introtext'] = '導入テキスト';
$string['introtext_details'] = '指示はタイトルの下の短い 1 行です。導入テキストには任意でヒントを書けます。画像も任意です。「読み始める」をタップするとタイマーが始まること、文章には戻れないことの注意書きは自動で追加されます。';
$string['item_desc'] = '速読の練習です。学習者はタイマーが動いている間に文章を読み、そのあと文章についての問題に答えます。読む速さが 1 分あたりの語数（日本語では文字数）で表示されます。';
$string['minsec'] = '{$a->minutes} 分 {$a->seconds} 秒';
$string['noanswer'] = '未回答';
$string['notread'] = '最後まで読まなかったので、読む速さはありません。';
$string['passage'] = '文章';
$string['passage_details'] = 'やさしい文章を選んでください。学習者がほぼすべての語を知っているのが理想です。速読は速く読む練習で、新しい語を覚える練習ではありません。';
$string['passagestatus'] = '{$a->count} 語。目標の速さ（1 分あたり {$a->target} 語）で読むと約 {$a->time} かかります。';
$string['passagestatus_cpm'] = '{$a->count} 文字。目標の速さ（1 分あたり {$a->target} 文字）で読むと約 {$a->time} かかります。';
$string['passmark'] = '速さが有効になる正答率';
$string['passmark_details'] = '問題の正答率がこの値以上のときだけ、読む速さが有効になります。';
$string['passmarkstatus'] = '問題が {$a->questions} 問なら、{$a->needed} 問正解する必要があります。';
$string['picture'] = '画像';
$string['pluginname'] = '速読';
$string['questionanswers'] = '解答（1 行に 1 つ）';
$string['questioncount'] = '{$a} 問';
$string['questionfeedback'] = 'フィードバック（任意）';
$string['questionno'] = '問題 {$a}';
$string['questionofn'] = '問題 {$a->number} / {$a->total}';
$string['questions'] = '問題';
$string['questions_details'] = '問題を 1〜5 問入力してください。結果画面まで、学習者には正解かどうか表示されません。フィードバックは結果画面にだけ表示されます。';
$string['questiontext'] = '問題文';
$string['readingspeed'] = 'あなたの読む速さ';
$string['readtime'] = '{$a->count} 語を {$a->minutes} 分 {$a->seconds} 秒で';
$string['readtime_cpm'] = '{$a->count} 文字を {$a->minutes} 分 {$a->seconds} 秒で';
$string['screen1'] = '画面 1：導入';
$string['screen2'] = '画面 2：読む文章';
$string['screen3'] = '画面 3：クイズ';
$string['screen4'] = '画面 4：結果と採点';
$string['showallquestions'] = 'すべての設問を一度に表示';
$string['showallquestions_details'] = 'オフ：1 問ずつ表示し、タップで次に進みます。オン：すべての問題を 1 ページに表示し、送信ボタンを置きます。';
$string['showtimer'] = '読んでいる間タイマーを表示';
$string['showtimer_details'] = '時間はどちらの場合も記録されます。学習者が緊張するようなら、タイマーを非表示にしてください。';
$string['speedcounts'] = 'あなたの速さは有効です。{$a->total} 問中 {$a->needed} 問の正解が必要で、{$a->correct} 問正解しました。';
$string['speeddoesnotcount'] = '今回は速さが有効になりません。{$a->total} 問中 {$a->needed} 問の正解が必要でしたが、正解は {$a->correct} 問でした。次はもう少しゆっくり読んで、内容をよく理解しましょう。';
$string['speedpoints'] = 'スピードポイント';
$string['speedreading'] = '速読';
$string['startreading'] = '読み始める';
$string['submitanswers'] = '解答を送信';
$string['tapnext'] = '答えをタップすると次の問題に進みます。結果は最後に表示されます。';
$string['targetis'] = '目標：{$a}';
$string['targetwpm'] = '目標の速さ';
$string['targetwpm_details'] = 'この速さ以上なら 10 スピードポイント、この半分以下の速さなら 0 になります。';
$string['thepassage'] = '文章';
$string['timerrunning'] = 'タイマー作動中';
$string['toofast'] = '速すぎて本当に読んだとは言えないため、速さは有効になりません。次は文章を全部読んでください。';
$string['wordcount'] = '語数';
$string['wordcount_details'] = '空欄にすると語数を自動で数えます。特定の語数にしたい場合は入力してください。<br>注：日本語、中国語、韓国語、タイ語では、語ではなく文字を数えます。';
$string['wordcountshort'] = '{$a} 語';
$string['wordsperminute'] = '語/分';
$string['youranswer'] = 'あなたの答え：';
$string['youranswers'] = 'あなたの答え';
