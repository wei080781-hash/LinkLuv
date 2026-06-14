# 修改紀錄：修復首頁認證連結與繁體中文語系設定

## 問題描述
1. 在 `welcome.blade.php` 中，導覽列的「登入」與「註冊」按鈕連結被硬編碼為 `#`。
2. 系統原始語言為英文，需整合繁體中文 (`zh_TW`) 支援以符合使用需求。
3. 登入與註冊頁面的 UI 文字需統一調整為直觀的繁體中文（如：將 "Name" 改為「姓名」，"Email" 改為「信箱」）。

## 解決方案
1. **連結修復**: 將連結改為使用 Laravel 的 `route()` 輔助函數：
   - 登入連結：`{{ route('login') }}`
   - 註冊連結：`{{ route('register') }}`
2. **語系與介面在地化**:
   - 透過 `php artisan lang:publish` 發布語言檔案。
   - 建立 `lang/zh_TW` 目錄並設定 `config/app.php` 中的 `locale` 為 `zh_TW`。
   - 更新 `lang/zh_TW/auth.php` 內的認證錯誤訊息為中文。
   - 直接修改 `resources/views/auth/login.blade.php` 與 `resources/views/auth/register.blade.php`，將 UI 標籤、按鈕與文字直接替換為繁體中文，確保介面顯示符合預期。

## 影響檔案
- `resources/views/welcome.blade.php`
- `config/app.php`
- `lang/zh_TW/auth.php`
- `lang/zh_TW/messages.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
