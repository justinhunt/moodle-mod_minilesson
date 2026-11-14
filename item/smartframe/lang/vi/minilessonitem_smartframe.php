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
 * Vietnamese strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Tạo widget bằng AI';
$string['aiinstructions_copied'] = 'Đã sao chép hướng dẫn cho AI';
$string['aiinstructions_copy'] = 'Sao chép hướng dẫn cho AI';
$string['aiinstructions_download'] = 'Tải xuống';
$string['aiinstructions_edit'] = 'Để sửa widget sau này, hãy tải nó xuống từ ô tệp bên dưới, đính kèm vào một cuộc trò chuyện mới cùng với hướng dẫn này và nói điều cần thay đổi.';
$string['aiinstructions_intro'] = 'Hãy tạo widget trong ChatGPT, Claude hoặc Gemini, nơi bạn có thể dùng thử và yêu cầu chỉnh sửa:';
$string['aiinstructions_show'] = 'Hiện hướng dẫn';
$string['aiinstructions_step1'] = 'Dán hướng dẫn này vào một cuộc trò chuyện mới, hoặc tải xuống rồi đính kèm tệp.';
$string['aiinstructions_step2'] = 'Mô tả hoạt động. Ví dụ: “Một bài đố về thì quá khứ của 10 động từ thông dụng, có hướng dẫn bằng tiếng Anh và tiếng Nhật, có báo điểm.”';
$string['aiinstructions_step3'] = 'Dùng thử trong cuộc trò chuyện và yêu cầu chỉnh sửa cho đến khi bạn hài lòng.';
$string['aiinstructions_step4'] = 'Nhập “Export it for Poodll”, tải tệp nhận được xuống rồi tải lên bên dưới.';
$string['error:htmlencoding'] = 'Tệp HTML phải được lưu dưới dạng văn bản UTF-8.';
$string['error:importbase64'] = 'Tệp widget {$a} không phải base64 hợp lệ. Hãy mã hóa các byte của tệp thành base64 thuần, không có tiền tố “data:”.';
$string['error:importboth'] = 'Hãy cung cấp widget dưới dạng widgethtml hoặc tệp trong customfile1, không phải cả hai.';
$string['error:importgradepolicy'] = 'gradepolicy phải là 0 (bỏ qua điểm của widget), 1 (lưu lại) hoặc 2 (tính vào điểm bài học).';
$string['error:importnofile'] = 'Mục này chưa có widget. Hãy đặt HTML của widget vào trường widgethtml.';
$string['error:importonefile'] = 'Mục này có nhiều hơn một tệp trong vùng tệp customfile1. Chỉ thêm một tệp widget (.html hoặc .zip).';
$string['error:manifestentry'] = '“entry” của manifest ({$a}) phải trỏ đến một tệp HTML trong ZIP.';
$string['error:manifestfield'] = '“{$a}” trong manifest bị thiếu hoặc không hợp lệ.';
$string['error:manifestjson'] = 'Manifest của widget không phải JSON hợp lệ.';
$string['error:manifestprotocol'] = 'Widget này cần máy chủ bài học mới hơn. Máy chủ này dùng giao thức phiên bản {$a}.';
$string['error:manifestschema'] = '“schemaVersion” của manifest phải là {$a}.';
$string['error:nomanifest'] = 'ZIP không có manifest.json ở cấp cao nhất.';
$string['error:nomanifesthtml'] = 'Tệp HTML không có manifest của widget. Tệp cần có khối &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Hãy yêu cầu công cụ AI: “export it for Poodll”.';
$string['error:notazip'] = 'Tệp không phải tệp ZIP, hoặc đã bị hỏng.';
$string['error:notpublished'] = 'Không thể xuất bản widget này nên người học không thấy được. Hãy lưu lại mục này để thử lại.';
$string['error:onezip'] = 'Chỉ tải lên một tệp widget. Xóa các tệp còn lại.';
$string['error:publishfailed'] = 'Không thể xuất bản widget.';
$string['error:zipbadpath'] = 'ZIP chứa tên tệp không được phép: {$a}';
$string['error:zipduplicate'] = 'ZIP chứa hai tệp trùng tên: {$a}';
$string['error:zipencrypted'] = 'ZIP được bảo vệ bằng mật khẩu. Hãy tải lên ZIP không có mật khẩu.';
$string['error:zipfiletoobig'] = 'Tệp {$a->file} quá lớn. Kích thước tối đa cho phép là {$a->size}.';
$string['error:zipfiletype'] = 'Loại tệp này không được phép trong widget: {$a}';
$string['error:zipread'] = 'Không đọc được tệp {$a} trong ZIP.';
$string['error:zipsymlink'] = 'ZIP chứa một liên kết, điều này không được phép: {$a}';
$string['error:ziptoobig'] = 'ZIP quá lớn. Kích thước ZIP tối đa cho phép là {$a}.';
$string['error:ziptoomanyfiles'] = 'ZIP chứa quá nhiều tệp. Tối đa cho phép là {$a}.';
$string['error:ziptotaltoobig'] = 'Tổng dung lượng các tệp trong ZIP quá lớn. Tối đa cho phép là {$a}.';
$string['error:zipunreadable'] = 'Không đọc được ZIP.';
$string['gradepolicy'] = 'Điểm của widget';
$string['gradepolicy_help'] = 'Một số widget báo điểm, ví dụ 3 trên 4. Hãy chọn bài học sẽ làm gì với điểm đó.

* Bỏ qua: không dùng điểm.
* Lưu nhưng không chấm: điểm được lưu cùng lượt làm bài nhưng không tính vào điểm bài học.
* Dùng làm điểm của mục: điểm được tính vào điểm bài học, quy đổi theo tổng điểm. Người học chuyển tiếp trước khi làm xong sẽ được 0.

Điểm do widget gửi được tính trong trình duyệt của người học nên người học có thể sửa. Hãy dùng để luyện tập, không dùng cho bài kiểm tra quan trọng.';
$string['gradepolicy_ignore'] = 'Bỏ qua';
$string['gradepolicy_record'] = 'Lưu nhưng không chấm';
$string['gradepolicy_use'] = 'Dùng làm điểm của mục';
$string['item_desc'] = 'Smart Frame tải một trang HTML đã tải lên vào một khung bên trong Minilesson. Mục này hiển thị tốt trên mọi màn hình, an toàn và có thể chấm điểm. Có thể dùng cho widget giải thích, hoạt động tương tác tự tạo hoặc thậm chí trò chơi.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Ngôn ngữ của widget';
$string['uilanguage_auto'] = 'Tự động';
$string['uilanguage_help'] = 'Ngôn ngữ của hướng dẫn, nút và phản hồi trong widget. Thiết lập này không thay đổi ngôn ngữ đang học.

Tự động sẽ dùng ngôn ngữ riêng của người học nếu widget có, sau đó là ngôn ngữ mẹ đẻ của bài học, rồi đến ngôn ngữ Moodle của người học, cuối cùng là ngôn ngữ mặc định của widget. Hãy lưu mục này một lần để có thể chọn trong các ngôn ngữ của widget.';
$string['warning:importuilanguage'] = 'uilanguage “{$a->lang}” không thuộc các ngôn ngữ giao diện của widget ({$a->languages}), nên người học sẽ thấy ngôn ngữ mặc định của nó.';
$string['widget_instructions1'] = 'Hoàn thành hoạt động bên dưới.';
$string['widgetnotavailable'] = 'Hoạt động này hiện không khả dụng.';
$string['widgetpackage'] = 'Tệp widget';
$string['widgetpackage_help'] = 'Widget: hoặc một tệp HTML (dạng mà công cụ AI tạo dễ nhất), hoặc một tệp ZIP cho widget có âm thanh, video hay nhiều hình ảnh.

Tệp HTML phải chứa manifest của widget trong một khối &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Tệp ZIP phải có manifest.json và một trang HTML ở cấp cao nhất. Mọi thứ widget dùng phải nằm trong tệp hoặc ZIP: widget không thể tải tệp từ trang web khác, và không thể dùng bộ nhớ trình duyệt (như localStorage) hay cookie.';
$string['widgetsummary'] = 'Widget hiện tại';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Ngôn ngữ: {$a->languages}<br>Có báo điểm: {$a->grade}';
