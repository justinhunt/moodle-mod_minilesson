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
 * Japanese strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '会話';
$string['check_auto'] = '自動チェック（音声認識）';
$string['check_self'] = 'セルフチェック（録音のみ）';
$string['checking'] = '確認中…';
$string['checkmode'] = 'チェック方法';
$string['checkmode_help'] = '自動チェック：会話が進む前に、各セリフが音声認識で一致する必要があります。セルフチェック：各セリフは録音されるだけで、常に受け付けられます。学習者は再生して録り直すことができます。どちらも同じ方法で採点されます。音声認識の結果ではなく、学習者が自分のセリフをいくつ終えたかで採点されます。';
$string['choosepart'] = 'どちらの役をやりますか？';
$string['complete'] = '会話が終わりました';
$string['conversation'] = '会話';
$string['defaultinstructions'] = '自分のセリフが出たら言いましょう。相手の話を聞きましょう。';
$string['dialog'] = '会話';
$string['dialog_help'] = '1行に1つのセリフを書きます。誰が言うかを示すため、各行の最初に A) または B) を付けます。例：

A) いらっしゃいませ！ご注文はお決まりですか？
B) ラテのLサイズをお願いします。';
$string['error:dialogspeakers'] = '会話には A) と B) の両方のセリフが必要です。';
$string['great'] = 'よくできました！';
$string['hearit'] = '聞く';
$string['item_desc'] = '学習者は決められた会話の一方の役を話し、もう一方の役はコンピューターの音声が話します。';
$string['labela'] = 'A の名前';
$string['labela_help'] = '2つの役の名前（任意）です。例：「店員」と「客」。チャットとスタート画面に表示されます。空欄のままにすると、A と B が使われます。';
$string['labelb'] = 'B の名前';
$string['listen'] = '相手の話を聞きましょう…';
$string['look_dotted'] = '点線の枠にグレーの文字';
$string['look_grey'] = 'グレーの文字のみ';
$string['movedon'] = 'スキップ済み';
$string['moveon'] = 'スキップする';
$string['myrecording'] = '自分の録音';
$string['playall'] = '会話全体を再生';
$string['playingback'] = '再生中 {$a->num}/{$a->total}';
$string['pluginname'] = '会話';
$string['prompt_full'] = 'セリフ全体';
$string['prompt_hidden'] = '非表示';
$string['prompt_initials'] = '最初の文字だけ';
$string['prompthelp'] = 'ヒント';
$string['prompthelp_help'] = '学習者の次のセリフをどこまで表示するかです。「最初の文字だけ」または「非表示」では、学習者はセリフを覚えている必要があります。セリフを言うと、すべての単語が表示されます。';
$string['promptlook'] = 'ヒントの見た目';
$string['recorded'] = '録音しました';
$string['recording'] = '録音中… マイクを押すと停止します。';
$string['recordingreplaced'] = '録音を置き換えました。';
$string['redo'] = '録り直す';
$string['redohere'] = 'マイクを押して、このセリフを録り直しましょう。';
$string['redowait'] = '相手が話し終わるのを待ってから、「録り直す」を押してください。';
$string['replay'] = 'もう一度聞く';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = '学習者が選ぶ';
$string['saidfirsttry'] = '1回目で言えました';
$string['saidintries'] = '{$a}回目で言えました';
$string['scene'] = '場面の画像';
$string['scene_help'] = '場面を表すためにチャットの背景に表示する画像（任意）です。例：カフェ、教室、オフィス。使われる画像は1枚だけです。2枚ある場合は、最初に追加した画像が使われます。';
$string['startagain'] = '最初から';
$string['startconversation'] = 'スタート';
$string['stopplayback'] = '停止';
$string['studentrole'] = '学習者の役';
$string['studentrole_help'] = '学習者が話す役です。もう一方の役はコンピューターの音声が話します。';
$string['summaryauto'] = '{$a->total}個中{$a->correct}個のセリフを正しく言えました';
$string['summaryself'] = '録音したセリフ：{$a}。どのセリフも録り直せます。';
$string['tryagain'] = 'おしい！もう一度言ってみましょう。';
$string['voicea'] = 'A の音声';
$string['voiceb'] = 'B の音声';
$string['youare'] = 'あなたの役：{$a}';
$string['yourline'] = 'あなたのセリフ';
$string['yourturn'] = 'あなたの番です。マイクを押して、セリフを言いましょう。';
