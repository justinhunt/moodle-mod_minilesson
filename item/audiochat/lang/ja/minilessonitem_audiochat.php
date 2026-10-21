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
 * Japanese strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '音声チャット';
$string['audiochat_silenceduration'] = 'AI が返答する前の間';
$string['audiochat_silenceduration_details'] = 'AI が学習者の発話は終わったと判断して返答するまでに、学習者が話すのをやめている必要がある時間（ミリ秒）です。値を大きくすると、文の途中で間を取って考える余裕が増えます。値を小さくすると、よりテンポのよい会話らしいやり取りになります。既定値は 3500（3.5 秒）です。500〜30000 の範囲外の値は既定値に戻ります。学習者が話した後にのみ適用され、アイテムの「自動送信」がオンのときだけ有効です。';
$string['cantchat_ningxia'] = 'チャットは利用できません。この項目タイプを使用するには、MiniLesson アクティビティの管理設定で有効な {$a} キーを設定する必要があります。チャットは中国（寧夏リージョン）でも利用できません。';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live モデル';
$string['geminilivemodel_details'] = 'プロバイダーが Gemini の場合に使う Gemini Live モデルです。Gemini 3.8 Live は、低遅延の音声向けの Google の現在の既定です。3.1 プレビューは旧モデルで、Google は移行を推奨しています。Gemini と Cloud Poodll のプロバイダーに適用され、OpenAI には適用されません。';
$string['gradingfailed'] = 'AI がこのチャットを採点できなかったため、0 点になりました。文字起こしは保存されています。';
$string['item_desc'] = '音声チャット項目は、AI パートナーとの音声会話を設定します。トピックについて話したり、写真について質問したりするのに使えます。AI ボットへの指示は自由記述で編集できます。';
$string['openai'] = 'OpenAI';
$string['pluginname'] = '音声チャット';
$string['provider'] = 'プロバイダー';
$string['provider_details'] = '音声チャット項目の AI プロバイダーを選択します。Cloud Poodll は基本的にそのまま動作しますが、寧夏リージョンでは動作しません。その他のプロバイダーでは、MiniLesson の管理 BYOK 設定で API キーを指定する必要があります。';
$string['unknown'] = '不明';
