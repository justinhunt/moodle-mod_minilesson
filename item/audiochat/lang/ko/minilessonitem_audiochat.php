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
 * Korean strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '음성 채팅';
$string['aiactions_instructions'] = 'AI 지시문에서 시키면 AI가 채팅을 끝내고 그림을 보여 줄 수 있어요. 예: ‘학생이 사과값을 내면 apple.png를 보여 주고, 작별 인사를 한 뒤 채팅을 끝내세요.’';
$string['aiactions_unsupported'] = '관리자 설정에서 고른 Gemini Live 모델(Extended Thinking)은 아직 이 동작을 지원하지 않아서, AI가 채팅을 끝내거나 그림을 보여 주지 않아요.';
$string['aiactionsheading'] = 'AI 동작';
$string['aicanend'] = 'AI가 채팅을 끝낼 수 있음';
$string['aicanend_desc'] = '대화가 끝나면 AI가 채팅을 끝내게 하기';
$string['aicanend_help'] = '체크하면 학생이 종료 버튼을 누른 것처럼 AI가 직접 채팅을 끝낼 수 있어요. 채팅을 언제 끝낼지 AI 지시문에 적어 주세요. AI는 먼저 작별 인사를 해요. 학생은 언제든지 종료 버튼으로 채팅을 끝낼 수 있어요.';
$string['aichat_endedbyai'] = 'AI가 채팅을 끝냈어요.';
$string['aipictures'] = 'AI가 보여 줄 수 있는 그림';
$string['aipictures_help'] = '채팅 중에 AI가 학생에게 보여 줄 수 있는 그림이에요. AI는 그림을 볼 수 없고 파일 이름으로만 알기 때문에, redapple.png나 blackkitten.jpg처럼 내용을 알 수 있는 이름을 붙이세요. 각 그림을 언제 보여 줄지 AI 지시문에 적어 주세요.';
$string['aipictureshown'] = '그림: {$a}';
$string['audiochat_silenceduration'] = 'AI가 답하기 전 대기 시간';
$string['audiochat_silenceduration_details'] = 'AI가 학생의 발화가 끝났다고 보고 답하기 전에, 학생이 말을 멈추고 있어야 하는 시간(밀리초)이에요. 값을 높이면 학습자가 문장 중간에 멈추고 생각할 여유가 더 생겨요. 값을 낮추면 더 빠르고 대화다운 주고받기가 돼요. 기본값은 3500(3.5초)이에요. 500~30000 범위를 벗어난 값은 기본값으로 돌아가요. 학생이 말한 뒤에만, 그리고 항목의 ‘자동 전송’이 켜져 있을 때만 적용돼요.';
$string['cantchat_ningxia'] = '채팅을 사용할 수 없어요. 이 항목 유형을 사용하려면 MiniLesson 활동 관리자 설정에 유효한 {$a} 키가 있어야 해요. 중국(닝샤 지역)에서도 채팅을 사용할 수 없어요';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live 모델';
$string['geminilivemodel_details'] = '공급자가 Gemini일 때 사용하는 Gemini Live 모델이에요. Gemini 3.8 Live는 지연 시간이 짧은 음성을 위한 Google의 현재 기본값이에요. 3.1 프리뷰는 이전 모델이며 Google은 이 모델에서 벗어나길 권장해요. Gemini와 Cloud Poodll 공급자에 적용되고 OpenAI에는 적용되지 않아요.';
$string['gradingfailed'] = 'AI가 이 채팅을 채점하지 못해 0점으로 처리됐어요. 대본은 저장되었어요.';
$string['item_desc'] = '음성 채팅 항목은 AI 파트너와 음성으로 대화하는 환경을 만들어요. 주제를 토론하거나 그림에 대해 질문하는 데 쓸 수 있어요. AI 봇에게 주는 프롬프트는 자유 텍스트이며 수정할 수 있어요.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = '음성 채팅';
$string['provider'] = '공급자';
$string['provider_details'] = '음성 채팅 항목에 사용할 AI 공급자를 선택하세요. Cloud Poodll은 바로 사용할 수 있지만 닝샤 지역에서는 안 돼요. 다른 공급자는 minilesson 관리자 BYOK 설정에 API 키를 입력해야 해요.';
$string['unknown'] = '알 수 없음';
