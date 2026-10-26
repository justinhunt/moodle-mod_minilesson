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
 * Korean strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '비디오 쉐도잉';
$string['aihelper_placeholder_shadow'] = '예: 문장 부호를 추가하고 철자와 대소문자 오류를 모두 고쳐 줘';
$string['autoforward'] = '자동으로 다음으로';
$string['autoforward_details'] = '한 줄의 마지막 쉐도잉이 끝나면 확인 / 다음을 기다리지 않고 저절로 다음 줄로 넘어가요. 이 모드에서는 재시도 버튼이 없어요.';
$string['enablesubtitlefetch'] = '자막 가져오기 버튼 사용';
$string['enablesubtitlefetch_details'] = '항목 양식에 YouTube 동영상의 자막을 자막 편집기로 내려받는 ‘자막 가져오기’ 버튼을 표시해요. 참고: 자막 가져오기는 항상 작동하지 않을 수 있고 언제든 중단될 수 있어요. Poodll이 항상 제공을 보장하지 않는 편의 도구예요.';
$string['error:badshadowlines'] = '쉐도잉할 줄은 * (모든 줄) 또는 쉼표로 구분한 줄 번호 목록이어야 해요. 예: 1,4,5,6.';
$string['error:badtimestamp'] = '클립 시작 및 종료 시간은 hh:mm:ss 형식이어야 해요. 예: 00:01:30.';
$string['error:badvtt'] = '자막을 분석하지 못했어요. 시간이 지정된 구간이 하나 이상 있는 올바른 WebVTT를 입력하세요.';
$string['error:nocuesinclip'] = '클립의 시작~종료 시간 안에 완전히 들어오는 자막 줄이 없어요. 시간이나 자막을 조정하세요.';
$string['error:noshadowlines'] = '선택한 줄 번호 중 클립의 시작~종료 시간 안에 있는 자막 줄과 일치하는 것이 없어요.';
$string['error:novideoid'] = 'YouTube 동영상 ID나 URL이 필요해요.';
$string['error:startafterend'] = '클립 종료 시간은 시작 시간보다 뒤여야 해요.';
$string['error:subtitlefetchdisabled'] = '이 사이트에서는 자막 가져오기가 꺼져 있어요.';
$string['fetchvtt'] = '자막 가져오기';
$string['fetchvtt_disabled'] = '자막 자동 가져오기가 현재 꺼져 있어요';
$string['fetchvtt_failed'] = 'YouTube에서 자막을 가져오지 못했어요.';
$string['fetchvtt_fetching'] = '가져오는 중...';
$string['fetchvtt_invalidurl'] = '먼저 올바른 YouTube URL이나 11자리 동영상 ID를 입력하세요.';
$string['fetchvtt_overwrite'] = '편집기에 있는 현재 자막이 바뀌어요. 계속할까요?';
$string['fetchvtt_overwrite_title'] = '자막을 바꿀까요?';
$string['item_desc'] = '비디오 쉐도잉 항목은 YouTube 클립을 한 줄씩 재생해요. 학생들은 단어가 강조 표시되는 동안 영상과 함께 말하며 각 자막 줄을 ‘쉐도잉’해요.';
$string['loopcount'] = '줄당 쉐도잉 횟수';
$string['loopcount_desc'] = '학생이 쉐도잉할 수 있도록 각 줄을 다시 재생하는 횟수예요.';
$string['loopindicator'] = '쉐도잉: {$a->current} / {$a->total}';
$string['oknext'] = '확인 / 다음';
$string['paste_empty'] = '먼저 YouTube에서 복사한 대본을 붙여넣으세요.';
$string['paste_failed'] = '대본을 변환하지 못했어요.';
$string['paste_label'] = 'YouTube에서 복사한 대본';
$string['paste_placeholder'] = '0:00
대본의 첫 번째 줄
0:03
대본의 두 번째 줄';
$string['paste_step_copy'] = '대본 전체를 선택해 복사한 뒤 아래 상자에 붙여넣으세요';
$string['paste_step_open_novideo'] = 'YouTube에서 동영상을 여세요 (먼저 양식에 동영상 ID를 입력하면 여기에 링크가 표시돼요)';
$string['paste_step_showtranscript'] = '동영상 아래에서 설명을 펼치고 ‘스크립트 표시’를 누르세요';
$string['paste_step_timestamps'] = '대본 패널의 ⋮ 메뉴에서 타임스탬프가 켜져 있는지 확인하세요';
$string['paste_success'] = '자막 {$a}줄을 추가했어요.';
$string['paste_wordhighlightoff'] = '붙여넣은 대본에는 줄 단위 시간 정보만 있어서 단어별 강조 표시를 껐어요. 영상에서는 한 줄씩 강조돼요.';
$string['pastevtt'] = '대본 붙여넣기';
$string['pastevtt_convert'] = '자막으로 변환';
$string['pastevtt_title'] = 'YouTube 대본 붙여넣기';
$string['pluginname'] = '비디오 쉐도잉';
$string['retry'] = '재시도';
$string['rotatedevice'] = '계속하려면 기기를 세로 모드로 돌려 주세요.';
$string['shadow_instructions1'] = '영상을 보세요. 그런 다음 각 줄을 쉐도잉하세요. 영상이 다시 재생될 때 듣고 함께 말하세요.';
$string['shadowlines'] = '쉐도잉할 줄';
$string['shadowlines_desc'] = '쉐도잉할 자막 줄 번호예요. 아래 자막에서 1부터 세요. 예: 1,4,5,6. 모든 줄을 쉐도잉하려면 *를 쓰세요. 나머지 줄도 영상을 보는 동안 계속 표시돼요.';
$string['shadowpause'] = '쉐도잉 사이 일시정지(초)';
$string['shadowvtt'] = '자막(WebVTT)';
$string['shadowvtt_desc'] = '아래 영역에 클립의 WebVTT 자막을 붙여넣거나 수정하세요.';
$string['startshadowing'] = '쉐도잉 시작';
$string['watchhint'] = '재생을 누르고 영상을 보세요. 끝나면 “쉐도잉 시작” 버튼을 누르세요.';
$string['wordhighlight'] = '단어별 강조 표시 사용';
$string['wordhighlight_details'] = '자막의 단어별 타임스탬프를 사용해 말하는 단어를 하나씩 강조해요. YouTube의 단어별 타이밍은 일부 동영상에서 정확하지 않아요. 끄면 줄 전체를 강조해요. 끄면 자막을 가져올 때 단어별 타임스탬프도 가져오지 않아요.';
$string['ytclipdetails'] = 'YouTube 클립(ID/URL, 시작 및 종료 초)';
