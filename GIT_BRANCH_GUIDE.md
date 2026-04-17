# GitHub 分支 (Branch) 操作指南

這份指南說明如何在 Git 專案中建立新的分支並進行切換。

## 1. 建立並切換到新分支 (推薦)
這是最快的方法，一條指令同時完成「建立」與「切換」。

```bash
git checkout -b <分支名稱>
```
*範例：`git checkout -b feature-login`*

或者使用較新的 Git 版本指令：
```bash
git switch -c <分支名稱>
```

---

## 2. 分步操作（先建立，後切換）

如果你習慣分開處理，可以這樣做：

1. **建立新分支**：
   ```bash
   git branch <分支名稱>
   ```

2. **切換到該分支**：
   ```bash
   git checkout <分支名稱>
   ```
   *(或使用 `git switch <分支名稱>`)*

---

## 3. 確認目前所在分支
執行以下指令，可以看到目前的清單，前面有星號 `*` 的就是你目前所在的分支：

```bash
git branch
```

---

## 4. 將新分支推送到 GitHub
建立分支後，遠端 GitHub 還沒有這個分支，你需要執行以下指令進行推送：

```bash
git push -u origin <分支名稱>
```
*`-u` 參數會設定上游追蹤，之後你在這個分支只需要打 `git push` 或 `git pull` 即可。*
