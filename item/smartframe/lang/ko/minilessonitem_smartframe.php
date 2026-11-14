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
 * Korean strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'AI로 위젯 만들기';
$string['aiinstructions_copied'] = 'AI 지시문을 복사했어요';
$string['aiinstructions_copy'] = 'AI 지시문 복사';
$string['aiinstructions_download'] = '다운로드';
$string['aiinstructions_edit'] = '나중에 위젯을 바꾸려면 아래 파일 상자에서 내려받아 이 지시문과 함께 새 채팅에 첨부하고, 바꿀 내용을 말해 주세요.';
$string['aiinstructions_intro'] = 'ChatGPT, Claude, Gemini에서 위젯을 만드세요. 거기서 사용해 보고 수정을 요청할 수 있어요:';
$string['aiinstructions_show'] = '지시문 보기';
$string['aiinstructions_step1'] = '이 지시문을 새 채팅에 붙여 넣거나, 내려받아 파일로 첨부하세요.';
$string['aiinstructions_step2'] = '활동을 설명하세요. 예: ‘자주 쓰는 동사 10개의 과거형 퀴즈, 영어와 일본어 안내 포함, 점수 보고.’';
$string['aiinstructions_step3'] = '채팅에서 사용해 보고, 마음에 들 때까지 수정을 요청하세요.';
$string['aiinstructions_step4'] = '‘Export it for Poodll’라고 입력하고, 받은 파일을 내려받아 아래에 업로드하세요.';
$string['error:htmlencoding'] = 'HTML 파일은 UTF-8 텍스트로 저장해야 해요.';
$string['error:importbase64'] = '위젯 파일 {$a}은(는) 올바른 base64가 아니에요. 파일 바이트를 ‘data:’ 접두사 없이 일반 base64로 인코딩하세요.';
$string['error:importboth'] = '위젯은 widgethtml 또는 customfile1의 파일 중 하나로만 주세요. 둘 다는 안 돼요.';
$string['error:importgradepolicy'] = 'gradepolicy는 0(위젯 점수 무시), 1(기록), 2(레슨 성적에 반영) 중 하나여야 해요.';
$string['error:importnofile'] = '이 항목에는 위젯이 없어요. 위젯의 HTML을 widgethtml 필드에 넣으세요.';
$string['error:importonefile'] = '이 항목의 customfile1 파일 영역에 파일이 두 개 이상 있어요. 위젯 파일(.html 또는 .zip)은 하나만 추가하세요.';
$string['error:manifestentry'] = '매니페스트의 ‘entry’({$a})는 ZIP 안의 HTML 파일이어야 해요.';
$string['error:manifestfield'] = '매니페스트의 ‘{$a}’이(가) 없거나 올바르지 않아요.';
$string['error:manifestjson'] = '위젯의 매니페스트가 올바른 JSON이 아니에요.';
$string['error:manifestprotocol'] = '이 위젯은 더 새로운 레슨 호스트가 필요해요. 이 호스트의 프로토콜 버전은 {$a}이에요.';
$string['error:manifestschema'] = '매니페스트의 ‘schemaVersion’은 {$a}이어야 해요.';
$string['error:nomanifest'] = 'ZIP 최상위에 manifest.json이 없어요.';
$string['error:nomanifesthtml'] = 'HTML 파일에 위젯 매니페스트가 없어요. &lt;script type="application/json" id="poodll-widget-manifest"&gt; 블록이 필요해요. AI 도구에 ‘export it for Poodll’라고 요청하세요.';
$string['error:notazip'] = '이 파일은 ZIP 파일이 아니거나 손상되었어요.';
$string['error:notpublished'] = '이 위젯을 게시하지 못해 학습자에게 보이지 않아요. 항목을 다시 저장해서 다시 시도하세요.';
$string['error:onezip'] = '위젯 파일은 하나만 업로드하세요. 나머지는 삭제하세요.';
$string['error:publishfailed'] = '위젯을 게시하지 못했어요.';
$string['error:zipbadpath'] = 'ZIP에 허용되지 않는 파일 이름이 있어요: {$a}';
$string['error:zipduplicate'] = 'ZIP에 이름이 같은 파일이 두 개 있어요: {$a}';
$string['error:zipencrypted'] = 'ZIP가 비밀번호로 보호되어 있어요. 비밀번호가 없는 ZIP을 업로드하세요.';
$string['error:zipfiletoobig'] = '{$a->file} 파일이 너무 커요. 허용되는 최대 크기는 {$a->size}예요.';
$string['error:zipfiletype'] = '이 종류의 파일은 위젯에 쓸 수 없어요: {$a}';
$string['error:zipread'] = 'ZIP에서 {$a} 파일을 읽지 못했어요.';
$string['error:zipsymlink'] = 'ZIP에 링크가 있어요. 링크는 허용되지 않아요: {$a}';
$string['error:ziptoobig'] = 'ZIP가 너무 커요. 허용되는 ZIP 최대 크기는 {$a}예요.';
$string['error:ziptoomanyfiles'] = 'ZIP에 파일이 너무 많아요. 최대 {$a}개까지 허용돼요.';
$string['error:ziptotaltoobig'] = 'ZIP 안 파일들의 전체 크기가 너무 커요. 최대 {$a}까지 허용돼요.';
$string['error:zipunreadable'] = 'ZIP를 읽지 못했어요.';
$string['gradepolicy'] = '위젯 점수';
$string['gradepolicy_help'] = '어떤 위젯은 4개 중 3개처럼 점수를 보고해요. 레슨에서 그 점수를 어떻게 쓸지 고르세요.

* 무시: 점수를 쓰지 않아요.
* 저장만 하고 채점 안 함: 점수는 시도와 함께 저장되지만 레슨 성적에는 반영되지 않아요.
* 항목 성적으로 사용: 점수를 총점에 맞게 환산해 레슨 성적에 반영해요. 끝내기 전에 다음으로 넘어간 학습자는 0점이에요.

위젯이 보내는 점수는 학습자의 브라우저에서 계산되므로 학습자가 바꿀 수도 있어요. 연습용으로 쓰고, 중요한 시험에는 쓰지 마세요.';
$string['gradepolicy_ignore'] = '무시';
$string['gradepolicy_record'] = '저장만 하고 채점 안 함';
$string['gradepolicy_use'] = '항목 성적으로 사용';
$string['item_desc'] = 'Smart Frame은 업로드한 HTML 페이지를 Minilesson 안의 프레임에 불러와요. 반응형이고 안전하며, 점수를 낼 수도 있어요. 설명용 위젯, 직접 만든 대화형 활동, 게임에도 쓸 수 있어요.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = '위젯 언어';
$string['uilanguage_auto'] = '자동';
$string['uilanguage_help'] = '위젯의 안내, 버튼, 피드백에 쓰이는 언어예요. 학습하는 언어는 바뀌지 않아요.

자동은 위젯이 지원하면 학습자 자신의 언어를, 그다음 레슨의 모국어, 그다음 학습자의 Moodle 언어, 마지막으로 위젯의 기본 언어를 써요. 위젯의 언어 중에서 고르려면 항목을 한 번 저장하세요.';
$string['warning:importuilanguage'] = 'uilanguage ‘{$a->lang}’은(는) 위젯의 인터페이스 언어({$a->languages})가 아니라서 학습자에게는 기본 언어가 보여요.';
$string['widget_instructions1'] = '아래 활동을 완료하세요.';
$string['widgetnotavailable'] = '지금은 이 활동을 이용할 수 없어요.';
$string['widgetpackage'] = '위젯 파일';
$string['widgetpackage_help'] = '위젯 파일이에요. HTML 파일 하나(AI 도구가 가장 쉽게 만드는 형식) 또는 오디오, 동영상, 많은 이미지가 있는 위젯이라면 ZIP을 올리세요.

HTML 파일에는 위젯의 매니페스트가 &lt;script type="application/json" id="poodll-widget-manifest"&gt; 블록에 들어 있어야 해요. ZIP은 최상위에 manifest.json과 HTML 페이지가 있어야 해요. 위젯이 쓰는 것은 모두 파일이나 ZIP 안에 있어야 해요. 위젯은 다른 사이트에서 파일을 불러올 수 없고, 브라우저 저장소(localStorage 등)나 쿠키도 쓸 수 없어요.';
$string['widgetsummary'] = '현재 위젯';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>언어: {$a->languages}<br>점수 보고: {$a->grade}';
