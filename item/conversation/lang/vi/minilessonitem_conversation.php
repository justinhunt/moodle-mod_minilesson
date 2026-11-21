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
 * Vietnamese strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Hội thoại';
$string['check_auto'] = 'Kiểm tra tự động (nhận dạng giọng nói)';
$string['check_self'] = 'Tự kiểm tra (chỉ ghi âm)';
$string['checking'] = 'Đang kiểm tra…';
$string['checkmode'] = 'Cách kiểm tra';
$string['checkmode_help'] = 'Kiểm tra tự động: nhận dạng giọng nói phải khớp với từng câu thoại thì cuộc hội thoại mới tiếp tục. Tự kiểm tra: mỗi câu thoại chỉ được ghi âm và luôn được chấp nhận. Học viên có thể nghe lại và ghi lại. Cả hai đều được chấm điểm như nhau: theo số câu thoại của mình mà học viên đã hoàn thành, không theo kết quả nhận dạng giọng nói.';
$string['choosepart'] = 'Bạn muốn đóng vai nào?';
$string['complete'] = 'Đã hoàn thành hội thoại';
$string['conversation'] = 'Hội thoại';
$string['defaultinstructions'] = 'Hãy nói câu thoại của bạn khi nó hiện ra. Hãy lắng nghe người đối thoại.';
$string['dialog'] = 'Hội thoại';
$string['dialog_help'] = 'Viết mỗi câu thoại trên một dòng. Bắt đầu mỗi dòng bằng A) hoặc B) để cho biết ai nói câu đó, ví dụ:

A) Xin chào! Hôm nay bạn muốn dùng gì?
B) Cho tôi một ly latte lớn nhé.';
$string['error:dialogspeakers'] = 'Cuộc hội thoại cần có câu thoại cho cả A) và B).';
$string['great'] = 'Làm tốt lắm!';
$string['hearit'] = 'Nghe mẫu';
$string['item_desc'] = 'Học viên nói một vai trong một cuộc hội thoại có sẵn, còn giọng máy nói vai kia.';
$string['labela'] = 'Tên của A';
$string['labela_help'] = 'Tên tùy chọn cho hai vai, ví dụ “Phục vụ” và “Khách hàng”. Tên hiện trong khung trò chuyện và trên màn hình bắt đầu. Nếu để trống, A và B sẽ được dùng.';
$string['labelb'] = 'Tên của B';
$string['listen'] = 'Hãy nghe người đối thoại…';
$string['look_dotted'] = 'Chữ xám trong khung nét đứt';
$string['look_grey'] = 'Chỉ chữ xám';
$string['movedon'] = 'Đã bỏ qua';
$string['moveon'] = 'Bỏ qua';
$string['myrecording'] = 'Bản ghi của tôi';
$string['playall'] = 'Phát toàn bộ hội thoại';
$string['playingback'] = 'Đang phát {$a->num}/{$a->total}';
$string['pluginname'] = 'Hội thoại';
$string['prompt_full'] = 'Cả câu';
$string['prompt_hidden'] = 'Ẩn';
$string['prompt_initials'] = 'Chỉ chữ cái đầu';
$string['prompthelp'] = 'Gợi ý';
$string['prompthelp_help'] = 'Hiển thị bao nhiêu phần của câu thoại tiếp theo của học viên. Với “Chỉ chữ cái đầu” hoặc “Ẩn”, học viên phải nhớ câu thoại. Toàn bộ từ sẽ hiện ra khi câu thoại được nói xong.';
$string['promptlook'] = 'Kiểu gợi ý';
$string['recorded'] = 'Đã ghi âm';
$string['recording'] = 'Đang ghi âm… bấm vào micrô để dừng.';
$string['recordingreplaced'] = 'Đã thay bản ghi.';
$string['redo'] = 'Ghi lại';
$string['redohere'] = 'Bấm vào micrô để ghi lại câu thoại này.';
$string['redowait'] = 'Hãy đợi người đối thoại nói xong, rồi bấm “Ghi lại”.';
$string['replay'] = 'Nghe lại';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = 'Học viên tự chọn';
$string['saidfirsttry'] = 'Nói đúng ngay lần đầu';
$string['saidintries'] = 'Nói đúng sau {$a} lần thử';
$string['scene'] = 'Hình bối cảnh';
$string['scene_help'] = 'Hình tùy chọn hiển thị phía sau khung trò chuyện để tạo bối cảnh: quán cà phê, lớp học, văn phòng. Chỉ dùng một hình. Nếu có hai hình, hình được thêm trước sẽ được dùng.';
$string['startagain'] = 'Bắt đầu lại';
$string['startconversation'] = 'Bắt đầu';
$string['stopplayback'] = 'Dừng';
$string['studentrole'] = 'Vai của học viên';
$string['studentrole_help'] = 'Vai mà học viên nói. Giọng máy nói vai còn lại.';
$string['summaryauto'] = 'Đã nói đúng {$a->correct}/{$a->total} câu thoại';
$string['summaryself'] = 'Đã ghi âm {$a} câu thoại. Bạn có thể ghi lại bất kỳ câu nào.';
$string['tryagain'] = 'Gần đúng rồi. Hãy nói lại câu thoại.';
$string['voicea'] = 'Giọng của A';
$string['voiceb'] = 'Giọng của B';
$string['youare'] = 'Vai của bạn: {$a}';
$string['yourline'] = 'Câu thoại của bạn';
$string['yourturn'] = 'Đến lượt bạn. Bấm vào micrô và nói câu thoại của bạn.';
