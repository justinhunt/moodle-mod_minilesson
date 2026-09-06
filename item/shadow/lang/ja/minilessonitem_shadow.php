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
 * Japanese strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'ビデオシャドーイング';
$string['aihelper_placeholder_shadow'] = '例：句読点を追加し、スペルと大文字・小文字の誤りを修正する';
$string['enablesubtitlefetch'] = '字幕取得ボタンを有効にする';
$string['enablesubtitlefetch_details'] = '項目フォームに「字幕を取得」ボタンを表示します。これは YouTube 動画の字幕を字幕エディターにダウンロードします。注意：字幕の取得は常に成功するとは限らず、いつでも動作しなくなる可能性があります。これは Poodll が常時利用可能であることを保証しない補助ツールです。';
$string['error:badshadowlines'] = 'シャドーイングする行は * （全行）またはカンマ区切りの行番号リスト（例：1,4,5,6）で指定してください。';
$string['error:badtimestamp'] = 'クリップの開始・終了時刻は hh:mm:ss 形式で指定してください（例：00:01:30）。';
$string['error:badvtt'] = '字幕を解析できませんでした。少なくとも1つのタイミング付きキューを含む有効な WebVTT を入力してください。';
$string['error:nocuesinclip'] = 'クリップの開始・終了時刻の範囲内に完全に収まる字幕行がありません。時刻または字幕を調整してください。';
$string['error:noshadowlines'] = '選択した行番号のいずれも、クリップの開始・終了時刻内の字幕行と一致しません。';
$string['error:novideoid'] = 'YouTube の動画 ID または URL が必要です。';
$string['error:startafterend'] = 'クリップの終了時刻は開始時刻より後にしてください。';
$string['error:subtitlefetchdisabled'] = '字幕の取得はこのサイトで無効になっています。';
$string['fetchvtt'] = '字幕を取得';
$string['fetchvtt_disabled'] = '字幕の自動取得は現在無効です';
$string['fetchvtt_failed'] = 'YouTube から字幕を取得できませんでした。';
$string['fetchvtt_fetching'] = '取得中…';
$string['fetchvtt_invalidurl'] = '先に有効な YouTube の URL または 11 文字の動画 ID を入力してください。';
$string['fetchvtt_overwrite'] = '現在エディターにある字幕を置き換えます。続けますか？';
$string['fetchvtt_overwrite_title'] = '字幕を置き換えますか？';
$string['item_desc'] = 'ビデオシャドーイング項目は、YouTube クリップを 1 行ずつ再生します。学習者は、単語がハイライトされるのに合わせて動画と一緒に話し、各字幕行を「シャドーイング」します。';
$string['loopcount'] = '1行あたりのシャドーイング回数';
$string['loopcount_desc'] = '学習者がシャドーイングできるように、各行を何回再生し直すか。';
$string['loopindicator'] = 'シャドーイング: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / 次へ';
$string['paste_empty'] = 'まず YouTube からコピーした文字起こしを貼り付けてください。';
$string['paste_failed'] = '文字起こしを変換できませんでした。';
$string['paste_label'] = 'YouTube からコピーした文字起こし';
$string['paste_placeholder'] = '0:00
文字起こしの1行目
0:03
文字起こしの2行目';
$string['paste_step_copy'] = '文字起こし全体を選択してコピーし、下のボックスに貼り付けます';
$string['paste_step_open_novideo'] = 'YouTube で動画を開きます（先にフォームに動画 ID を入力すると、ここにリンクが表示されます）';
$string['paste_step_showtranscript'] = '動画の下で説明を展開し、「文字起こしを表示」をクリックします';
$string['paste_step_timestamps'] = '文字起こしパネルの ⋮ メニューで、タイムスタンプがオンになっていることを確認します';
$string['paste_success'] = '{$a} 行の字幕を追加しました。';
$string['paste_wordhighlightoff'] = '貼り付けた文字起こしには行単位のタイミングしかないため、単語ごとのハイライトはオフになりました。クリップは1行ずつハイライトします。';
$string['pastevtt'] = '文字起こしを貼り付け';
$string['pastevtt_convert'] = '字幕に変換';
$string['pastevtt_title'] = 'YouTube の文字起こしを貼り付け';
$string['pluginname'] = 'ビデオシャドーイング';
$string['retry'] = 'やり直す';
$string['rotatedevice'] = '続けるにはデバイスを縦向きにしてください。';
$string['shadow_instructions1'] = '動画を見ましょう。次に各行をシャドーイングします。もう一度再生される動画に合わせて、聞きながら話します。';
$string['shadowlines'] = 'シャドーイングする行';
$string['shadowlines_desc'] = 'シャドーイングする字幕行の番号。下の字幕で1から数えます（例：1,4,5,6）。すべての行をシャドーイングするには * を使います。他の行も視聴中は表示されます。';
$string['shadowpause'] = 'シャドーイングの間の一時停止（秒）';
$string['shadowvtt'] = '字幕 (WebVTT)';
$string['shadowvtt_desc'] = '下の欄にクリップの WebVTT 字幕を貼り付けるか編集します。';
$string['startshadowing'] = 'シャドーイングを開始';
$string['watchhint'] = '再生してクリップを見てください。終わったら「シャドーイングを開始」を押してください。';
$string['wordhighlight'] = '単語ごとのハイライトを有効にする';
$string['wordhighlight_details'] = '字幕の単語タイムスタンプを使って、各単語が話されるたびにハイライトします。YouTube の単語タイミングは一部の動画で信頼できないため、オフにすると行全体をハイライトします。オフの場合、字幕の取得時に単語タイムスタンプの取得もスキップされます。';
$string['ytclipdetails'] = 'YouTube クリップ（ID/URL、開始・終了秒）';
