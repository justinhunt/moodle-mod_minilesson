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
 * Korean strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '대화';
$string['check_auto'] = '자동 확인 (음성 인식)';
$string['check_self'] = '스스로 확인 (녹음만)';
$string['checking'] = '확인 중…';
$string['checkmode'] = '확인 방식';
$string['checkmode_help'] = '자동 확인: 대화가 이어지려면 각 대사가 음성 인식과 일치해야 해요. 스스로 확인: 각 대사는 녹음만 되고 항상 통과돼요. 학생은 다시 듣고 다시 녹음할 수 있어요. 두 방식 모두 같은 방법으로 채점돼요. 음성 인식 결과가 아니라 학생이 자기 대사를 몇 개 마쳤는지로 채점해요.';
$string['choosepart'] = '어떤 역할을 할래요?';
$string['complete'] = '대화 완료';
$string['conversation'] = '대화';
$string['defaultinstructions'] = '내 대사가 나오면 말하세요. 상대의 말을 잘 들으세요.';
$string['dialog'] = '대화';
$string['dialog_help'] = '한 줄에 대사 하나씩 쓰세요. 누가 말하는지 보여 주려면 각 줄을 A) 또는 B)로 시작하세요. 예:

A) 안녕하세요! 오늘 뭘 드릴까요?
B) 라테 큰 사이즈로 주세요.';
$string['error:dialogspeakers'] = '대화에는 A)와 B)의 대사가 모두 있어야 해요.';
$string['great'] = '잘했어요!';
$string['hearit'] = '들어 보기';
$string['item_desc'] = '학생은 정해진 대화에서 한 역할을 말하고, 다른 역할은 컴퓨터 음성이 말해요.';
$string['labela'] = 'A의 이름';
$string['labela_help'] = '두 역할의 이름(선택 사항)이에요. 예: ‘점원’과 ‘손님’. 채팅과 시작 화면에 표시돼요. 비워 두면 A와 B가 사용돼요.';
$string['labelb'] = 'B의 이름';
$string['listen'] = '상대의 말을 들으세요…';
$string['look_dotted'] = '점선 상자 안의 회색 글자';
$string['look_grey'] = '회색 글자만';
$string['movedon'] = '넘어감';
$string['moveon'] = '넘어가기';
$string['myrecording'] = '내 녹음';
$string['playall'] = '대화 전체 재생';
$string['playingback'] = '재생 중 {$a->num}/{$a->total}';
$string['pluginname'] = '대화';
$string['prompt_full'] = '대사 전체';
$string['prompt_hidden'] = '숨김';
$string['prompt_initials'] = '첫 글자만';
$string['prompthelp'] = '힌트';
$string['prompthelp_help'] = '학생의 다음 대사를 얼마나 보여 줄지 정해요. ‘첫 글자만’ 또는 ‘숨김’에서는 학생이 대사를 기억해야 해요. 대사를 말하면 전체 단어가 표시돼요.';
$string['promptlook'] = '힌트 모양';
$string['recorded'] = '녹음됨';
$string['recording'] = '녹음 중… 멈추려면 마이크를 누르세요.';
$string['recordingreplaced'] = '녹음을 바꿨어요.';
$string['redo'] = '다시 녹음';
$string['redohere'] = '마이크를 눌러 이 대사를 다시 녹음하세요.';
$string['redowait'] = '상대가 말을 마칠 때까지 기다린 다음 ‘다시 녹음’ 버튼을 누르세요.';
$string['replay'] = '다시 듣기';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = '학생이 선택';
$string['saidfirsttry'] = '한 번에 성공';
$string['saidintries'] = '{$a}번 만에 성공';
$string['scene'] = '장면 그림';
$string['scene_help'] = '장면을 보여 주기 위해 채팅 뒤에 표시하는 그림(선택 사항)이에요. 예: 카페, 교실, 사무실. 그림은 한 장만 사용돼요. 두 장이 있으면 먼저 추가한 그림이 사용돼요.';
$string['startagain'] = '처음부터 다시';
$string['startconversation'] = '시작';
$string['stopplayback'] = '정지';
$string['studentrole'] = '학생 역할';
$string['studentrole_help'] = '학생이 말하는 역할이에요. 다른 역할은 컴퓨터 음성이 말해요.';
$string['summaryauto'] = '맞게 말한 대사: {$a->correct}/{$a->total}';
$string['summaryself'] = '녹음한 대사: {$a}개. 어떤 대사든 다시 녹음할 수 있어요.';
$string['tryagain'] = '아까워요. 다시 말해 보세요.';
$string['voicea'] = 'A의 음성';
$string['voiceb'] = 'B의 음성';
$string['youare'] = '내 역할: {$a}';
$string['yourline'] = '내 대사';
$string['yourturn'] = '내 차례예요. 마이크를 누르고 대사를 말하세요.';
