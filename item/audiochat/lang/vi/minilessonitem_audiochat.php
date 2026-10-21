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
 * Vietnamese strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Trò chuyện bằng giọng nói';
$string['audiochat_silenceduration'] = 'Khoảng lặng trước khi AI trả lời';
$string['audiochat_silenceduration_details'] = 'Học viên phải ngừng nói trong bao lâu (tính bằng mili giây) thì AI mới coi lượt của họ đã kết thúc và trả lời. Tăng giá trị để học viên có thêm thời gian dừng lại suy nghĩ giữa câu. Giảm giá trị để hội thoại qua lại nhanh và tự nhiên hơn. Mặc định là 3500 (3,5 giây). Giá trị ngoài khoảng 500 đến 30000 sẽ quay về mặc định. Chỉ áp dụng sau khi học viên đã nói, và chỉ khi mục được bật “Tự động gửi”.';
$string['cantchat_ningxia'] = 'Chat KHÔNG khả dụng. Để dùng loại mục này, bạn cần đặt khóa {$a} hợp lệ trong cài đặt quản trị của hoạt động MiniLesson. Chat cũng không khả dụng ở Trung Quốc (vùng Ninh Hạ)';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Mô hình Gemini Live';
$string['geminilivemodel_details'] = 'Mô hình Gemini Live được dùng khi nhà cung cấp là Gemini. Gemini 3.8 Live là mặc định hiện tại của Google cho giọng nói độ trễ thấp. Bản xem trước 3.1 là mô hình cũ hơn và Google khuyên nên chuyển sang mô hình khác. Điều này áp dụng cho nhà cung cấp Gemini và Cloud Poodll, không áp dụng cho OpenAI.';
$string['gradingfailed'] = 'AI không thể chấm điểm cuộc trò chuyện này nên được tính 0 điểm. Bản ghi đã được lưu.';
$string['item_desc'] = 'Mục Trò chuyện bằng giọng nói tạo một cuộc hội thoại bằng giọng nói với đối tác AI. Có thể dùng để thảo luận một chủ đề hoặc đặt câu hỏi về một bức hình. Các lời nhắc gửi cho bot AI là văn bản tự do và có thể chỉnh sửa.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Trò chuyện bằng giọng nói';
$string['provider'] = 'Nhà cung cấp';
$string['provider_details'] = 'Chọn nhà cung cấp AI cho mục Trò chuyện bằng giọng nói. Cloud Poodll dùng được ngay, trừ vùng Ninh Hạ. Với các nhà cung cấp khác, bạn cần nhập khóa API trong cài đặt quản trị BYOK của minilesson.';
$string['unknown'] = 'Không rõ';
