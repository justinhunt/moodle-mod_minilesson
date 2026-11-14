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
 * Japanese strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'AI でウィジェットを作る';
$string['aiinstructions_copied'] = 'AI への指示をコピーしました';
$string['aiinstructions_copy'] = 'AI への指示をコピー';
$string['aiinstructions_download'] = 'ダウンロード';
$string['aiinstructions_edit'] = 'あとでウィジェットを変更するには、下のファイル欄からダウンロードし、この指示と一緒に新しいチャットに添付して、変更したい点を伝えてください。';
$string['aiinstructions_intro'] = 'ウィジェットは ChatGPT、Claude、Gemini で作ります。そこで試したり、変更を頼んだりできます:';
$string['aiinstructions_show'] = '指示を表示';
$string['aiinstructions_step1'] = 'この指示を新しいチャットに貼り付けるか、ダウンロードしてファイルを添付します。';
$string['aiinstructions_step2'] = 'アクティビティを説明します。例:「よく使う動詞 10 個の過去形のクイズ。指示は英語と日本語で、スコアを報告する。」';
$string['aiinstructions_step3'] = 'チャットで試し、納得できるまで変更を頼みます。';
$string['aiinstructions_step4'] = '「Export it for Poodll」と入力し、渡されたファイルをダウンロードして、下でアップロードします。';
$string['error:htmlencoding'] = 'HTML ファイルは UTF-8 テキストで保存する必要があります。';
$string['error:importbase64'] = 'ウィジェットファイル {$a} は有効な base64 ではありません。ファイルのバイトを「data:」接頭辞なしのプレーンな base64 でエンコードしてください。';
$string['error:importboth'] = 'ウィジェットは widgethtml か customfile1 のファイルのどちらか一方で指定してください。両方は指定できません。';
$string['error:importgradepolicy'] = 'gradepolicy は 0（ウィジェットのスコアを無視）、1（記録する）、2（レッスンの評点に含める）のいずれかにしてください。';
$string['error:importnofile'] = 'このアイテムにはウィジェットがありません。ウィジェットの HTML を widgethtml フィールドに入れてください。';
$string['error:importonefile'] = 'このアイテムの customfile1 ファイル領域に複数のファイルがあります。ウィジェットファイル（.html または .zip）は 1 つだけ追加してください。';
$string['error:manifestentry'] = 'マニフェストの「entry」（{$a}）には ZIP 内の HTML ファイルを指定してください。';
$string['error:manifestfield'] = 'マニフェストの「{$a}」がないか、有効ではありません。';
$string['error:manifestjson'] = 'ウィジェットのマニフェストが有効な JSON ではありません。';
$string['error:manifestprotocol'] = 'このウィジェットには新しいレッスンホストが必要です。このホストのプロトコルバージョンは {$a} です。';
$string['error:manifestschema'] = 'マニフェストの「schemaVersion」は {$a} にしてください。';
$string['error:nomanifest'] = 'ZIP の最上位に manifest.json がありません。';
$string['error:nomanifesthtml'] = 'HTML ファイルにウィジェットのマニフェストがありません。&lt;script type="application/json" id="poodll-widget-manifest"&gt; ブロックが必要です。AI ツールに「export it for Poodll」と頼んでください。';
$string['error:notazip'] = 'このファイルは ZIP ファイルではないか、壊れています。';
$string['error:notpublished'] = 'このウィジェットを公開できなかったため、学習者には表示されません。アイテムをもう一度保存して再試行してください。';
$string['error:onezip'] = 'ウィジェットファイルは 1 つだけアップロードしてください。ほかは削除してください。';
$string['error:publishfailed'] = 'ウィジェットを公開できませんでした。';
$string['error:zipbadpath'] = 'ZIP に使用できないファイル名が含まれています: {$a}';
$string['error:zipduplicate'] = 'ZIP に同じ名前のファイルが 2 つあります: {$a}';
$string['error:zipencrypted'] = 'ZIP はパスワードで保護されています。パスワードのない ZIP をアップロードしてください。';
$string['error:zipfiletoobig'] = 'ファイル {$a->file} が大きすぎます。許可される最大サイズは {$a->size} です。';
$string['error:zipfiletype'] = 'この種類のファイルはウィジェットに使用できません: {$a}';
$string['error:zipread'] = 'ZIP からファイル {$a} を読み込めませんでした。';
$string['error:zipsymlink'] = 'ZIP にリンクが含まれています。リンクは使用できません: {$a}';
$string['error:ziptoobig'] = 'ZIP が大きすぎます。許可される ZIP の最大サイズは {$a} です。';
$string['error:ziptoomanyfiles'] = 'ZIP のファイル数が多すぎます。最大 {$a} 個までです。';
$string['error:ziptotaltoobig'] = 'ZIP 内のファイルの合計サイズが大きすぎます。最大 {$a} までです。';
$string['error:zipunreadable'] = 'ZIP を読み込めませんでした。';
$string['gradepolicy'] = 'ウィジェットのスコア';
$string['gradepolicy_help'] = '一部のウィジェットは「4 問中 3 問」のようにスコアを報告します。レッスンでそのスコアをどう扱うかを選びます。

* 無視: スコアは使いません。
* 保存するが評点にしない: スコアは受験記録と一緒に保存されますが、レッスンの評点には含まれません。
* アイテムの評点として使う: スコアを合計点に換算してレッスンの評点に含めます。終わる前に先へ進んだ学習者は 0 点になります。

ウィジェットが送るスコアは学習者のブラウザで計算されるため、学習者が書き換えることもできます。練習用に使い、重要なテストには使わないでください。';
$string['gradepolicy_ignore'] = '無視';
$string['gradepolicy_record'] = '保存するが評点にしない';
$string['gradepolicy_use'] = 'アイテムの評点として使う';
$string['item_desc'] = 'Smart Frame は、アップロードした HTML ページを Minilesson 内のフレームに読み込みます。レスポンシブで安全に動作し、評点を出すこともできます。解説用ウィジェット、独自のインタラクティブな活動、さらにはゲームにも使えます。';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'ウィジェットの言語';
$string['uilanguage_auto'] = '自動';
$string['uilanguage_help'] = 'ウィジェットの指示、ボタン、フィードバックの言語です。学習する言語は変わりません。

自動では、ウィジェットが対応していれば学習者自身の言語、次にレッスンの母語、次に学習者の Moodle の言語、最後にウィジェットの既定の言語を使います。ウィジェットの言語から選ぶには、アイテムを一度保存してください。';
$string['warning:importuilanguage'] = 'uilanguage「{$a->lang}」はウィジェットのインターフェース言語（{$a->languages}）にないため、学習者には既定の言語が表示されます。';
$string['widget_instructions1'] = '下のアクティビティに取り組みましょう。';
$string['widgetnotavailable'] = 'このアクティビティは現在利用できません。';
$string['widgetpackage'] = 'ウィジェットファイル';
$string['widgetpackage_help'] = 'ウィジェット本体です。HTML ファイル 1 つ（AI ツールで最も作りやすい形式）か、音声・動画・多数の画像を使うウィジェットなら ZIP を指定します。

HTML ファイルには、ウィジェットのマニフェストを &lt;script type="application/json" id="poodll-widget-manifest"&gt; ブロックに入れておく必要があります。ZIP には最上位に manifest.json と HTML ページが必要です。ウィジェットが使うものはすべてファイルか ZIP に含めてください。ウィジェットはほかのサイトからファイルを読み込めず、ブラウザのストレージ（localStorage など）や Cookie も使えません。';
$string['widgetsummary'] = '現在のウィジェット';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>言語: {$a->languages}<br>スコアの報告: {$a->grade}';
