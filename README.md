# Auto Ban Spam Keywords for Flarum

![Flarum Extension](https://img.shields.io/badge/Flarum-2.0.0%20%7C%201.8-brightgreen.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)

當系統偵測到使用者名稱、暱稱、簡介/簽名檔（Bio）、主題標題或留言內文包含後台設定的違規關鍵字時，自動執行安全防護處置：

- **無限期停權**：自動將該帳號停權至 `2099-12-31`。
- **變更顯示暱稱**：統一變更顯示暱稱（Nickname）為 `Banned`（`username` 保持不變）。
- **清空個人資料**：自動清空使用者頭像、個人簡介/簽名檔 (Bio，支援 `fof/user-bio`)，以及社群網路按鈕連結 (Social Profile Buttons，支援 `fof/socialprofile`)。
- **豁免權限群組 (不受影響的群組)**：支援於 Flarum 權限矩陣中勾選豁免群組（管理員預設豁免，其他群組可自由設定）。
- **使用者違規處置方式 (軟刪除 / 硬刪除)**：
  - **軟刪除 (Soft Delete)**：將該使用者過往發佈的所有主題（Discussions）與留言（Posts）執行隱藏 / 移入審核佇列，**完整保留原始內文**，並自動刷新討論區與個人資料統計頁。
  - **硬刪除 (Hard Delete)**：從資料庫與實體儲存區中永久徹底刪除該帳號發佈的所有主題、留言，以及透過 **FoF Upload (`fof/upload`)** 上傳的所有圖片與檔案（僅限有啟用 FoF Upload 時自動清理）。
- **後台管理介面**：管理員可自訂違規關鍵字清單並切換使用者違規處置方式（軟刪除或硬刪除）。

---

## 👨‍💻 作者資訊 (Author)

- **作者 (Author)**: Feng, Cheng-Chi
- **Email**: qwe987299@gmail.com
- **Packagist 帳號**: [qwe987299](https://packagist.org/users/qwe987299/)
- **授權條款 (License)**: MIT

---

## 🚀 安裝說明 (Installation)

於 Flarum 根目錄執行：

```bash
composer require qwe987299/flarum-auto-ban-spam
php flarum extension:enable qwe987299-auto-ban-spam
```

---

## ⚙️ 後台設定 (Configuration)

1. 登入 Flarum 管理員後台。
2. 前往 **擴充套件 (Extensions)** -> **Auto Ban Spam Keywords**：
   - **違規關鍵字清單**：輸入要攔截的關鍵字（多個關鍵字可用換行或逗號分隔）。
   - **使用者違規處置方式**：可選擇 **軟刪除（隱藏 / 等候審核）** 或 **硬刪除（永久刪除主題、留言與 FoF Upload 上傳的檔案）**。
3. 前往 **權限 (Permissions)** 頁面：
   - 於 **Auto Ban Spam Keywords** 區塊下勾選 **不受影響的群組**，即可設定豁免關鍵字封鎖檢查的使用者群組。
