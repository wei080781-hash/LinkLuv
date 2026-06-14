<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">生活牆</h2>
            @include('components.message-form')
            <div class="linkluv-comments" id="messages-list"></div>
        </div>
    </div>

    <style>
        /* ── 命名空間根層 ── */
        .linkluv-comments { display: flex; flex-direction: column; gap: 16px; }
        .linkluv-comments .msg-container { min-width: 0; }
        .linkluv-comments .msg-row { display: flex; align-items: flex-start; gap: 8px; }
        .linkluv-comments .child-replies { margin-top: 6px; }
        .linkluv-comments .depth-1 > .child-replies { margin-left: 40px; }
        .linkluv-comments .depth-2 > .child-replies { margin-left: 0; }

        /* ── depth 樣式 ── */
        .linkluv-comments .depth-0 { background: #fff; border-radius: 16px; border: 1px solid #e5e7eb; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
        .linkluv-comments .depth-1 { margin-left: 0; }
        .linkluv-comments .depth-2 { margin-left: 0; }

        /* ── 子回覆容器（replies-inner 的樣式由 app.css 控制） ── */
        /* ── depth 2+ 的 reply-item 不加 nested-reply，所以不會繼續縮排 ── */

        /* ★ 強制鎖定：depth-2 內的所有 replies-inner 固定樣式，不疊加 */
        .linkluv-comments .replies-inner > .reply-depth-1 {
        margin-left: 0;
        }

        .linkluv-comments .replies-inner > .reply-depth-2 {
        margin-left: 28px;
        }

        .linkluv-comments .replies-inner > .reply-depth-3 {
        margin-left: 56px;
        }

        .linkluv-comments .branch-hidden {
        display: none !important;
        }
        

        /* ── 收合/展開按鈕 ── */
        .toggle-replies-btn {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 0.78rem; font-weight: 600; color: #2563eb;
            background: none; border: none; cursor: pointer;
            padding: 2px 0; margin-top: 4px;
            transition: color 0.15s; user-select: none;
        }
        .toggle-replies-btn:hover { color: #1d4ed8; }
        .toggle-replies-btn .arrow { display: inline-block; transition: transform 0.2s ease; font-size: 0.65rem; }
        .toggle-replies-btn.open .arrow { transform: rotate(180deg); }

        /* ── 回覆區收合動畫 ── */
        .replies-wrapper { overflow: hidden; max-height: 0; opacity: 0; transition: max-height 0.35s ease, opacity 0.25s ease; }
        .replies-wrapper.expanded { max-height: 99999px; opacity: 1; }

        /* ── 頭像 + 縱線容器 ── */
        .avatar-col { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
        .avatar-col .v-line { width: 2px; flex: 1; background: #d1d5db; margin-top: 3px; min-height: 8px; }

        /* ── 泡泡 ── */
        .bubble {
            border-radius: 14px; padding: 8px 12px;
            background: #f3f4f6; min-width: 0; word-break: break-word;
            transition: background 0.15s;
        }
        .bubble:hover { background: #e9eaec; }
        .depth-1 .bubble { border-radius: 12px; font-size: 0.95em; }
        .depth-2 .bubble { border-radius: 10px; font-size: 0.9em; background: #f9fafb; border: 1px solid #e5e7eb; }
        .depth-2 .bubble:hover { background: #f0f0f0; }

        /* ── 高亮 ── */
        .msg-highlight .bubble { background: #dbeafe !important; border-color: #93c5fd !important; }

        /* ── 操作列 ── */
        .action-bar { display: flex; gap: 10px; font-size: 0.72rem; color: #9ca3af; margin-top: 3px; }
        .action-bar button { background: none; border: none; cursor: pointer; padding: 0; font-size: 0.72rem; transition: color 0.15s; }
        .action-bar button:hover { color: #374151; }
        .action-bar .like-btn.liked { color: #ec4899; font-weight: 700; }

        /* ── 回覆輸入框 ── */
        .reply-form-wrap { display: none; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .reply-form-wrap.show { display: flex; }
        .reply-form-wrap input[type="text"] {
            flex: 1; min-width: 120px; border-radius: 999px; font-size: 0.82rem;
            padding: 6px 14px; border: 1px solid #d1d5db; outline: none;
        }
        .reply-form-wrap input[type="text"]:focus { border-color: #60a5fa; }
        .reply-form-wrap .send-btn { color: #2563eb; font-size: 0.82rem; font-weight: 700; background: none; border: none; cursor: pointer; white-space: nowrap; }
        .reply-form-wrap .attach-btn { color: #6b7280; font-size: 1rem; cursor: pointer; flex-shrink: 0; }

        /* ── 媒體預覽 ── */
        .media-preview img, .media-preview video {
            max-width: 240px; max-height: 240px; border-radius: 10px;
            border: 1px solid #e5e7eb; margin-top: 6px; display: block; cursor: pointer;
        }

        /* ── Lightbox ── */
        #lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; }
        #lightbox.active { display: flex; }
        #lightbox img, #lightbox video { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
        #lightbox-close { position: absolute; top: 16px; right: 20px; color: #fff; font-size: 2rem; cursor: pointer; background: none; border: none; }
    </style>

    <div id="lightbox">
        <button id="lightbox-close" onclick="closeLightbox()">✕</button>
        <div id="lightbox-content"></div>
    </div>

    <script>
    // ═══════════════════════════════════════════════════════════
    // 狀態
    // ═══════════════════════════════════════════════════════════
    const expandedSet = new Set();
    let globalMsgMap  = new Map();   // id → raw msg，供輔助函式查詢

    // ═══════════════════════════════════════════════════════════
    // 1. 資料處理層
    //    扁平列表 → 真正樹狀（無限層，parent_id 決定位置）
    // ═══════════════════════════════════════════════════════════
    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';

        // 初始化全域 Map（供 buildReplyingToTag 等查詢）
        globalMsgMap = new Map();
        messages.forEach(m => globalMsgMap.set(m.id, { ...m, children: [] }));

        // 建立 parent → children 連結
        const roots = [];
        messages.forEach(m => {
            if (!m.parent_id) {
                roots.push(globalMsgMap.get(m.id));
            } else {
                const parent = globalMsgMap.get(m.parent_id);
                if (parent) {
                    parent.children.push(globalMsgMap.get(m.id));
                } else {
                    // 父節點已刪除，降級為根
                    roots.push(globalMsgMap.get(m.id));
                }
            }
        });

        // 從根節點遞迴渲染
        roots.forEach(root => {
            list.insertAdjacentHTML('beforeend', buildMessageHTML(root, 0, new Set(), null));
        });
    }

    function flattenReplies(msg, depth = 1, visited = new Set(), result = [], branchRootId = null) {
    (msg.children || []).forEach(child => {
        if (visited.has(child.id)) return;
        visited.add(child.id);

        const currentBranchRootId = depth === 1 ? child.id : branchRootId;

        result.push({
            msg: child,
            realDepth: depth,
            visualDepth: Math.min(depth, 3),
            branchRootId: currentBranchRootId,
        });

        flattenReplies(child, depth + 1, visited, result, currentBranchRootId);
    });

    return result;

}

    // ═══════════════════════════════════════════════════════════
    // 2. 渲染核心
    //    遞迴產生器 + 視覺深度限制（最多顯示 3 種樣式）
    //    rootId：所屬根留言的 id（用於收合/展開與回覆框）
    // ═══════════════════════════════════════════════════════════
    function buildMessageHTML(msg, depth, visited, rootId) {
    if (visited.has(msg.id)) return '';
    visited.add(msg.id);

    const thisRootId = depth === 0 ? msg.id : rootId;

    // 只有 root 負責攤平所有 replies
    if (depth === 0) {
        const repliesHtml = flattenReplies(msg)
            .map(item => {
                return renderBubble(
                    item.msg,
                    item.realDepth,
                    item.visualDepth,
                    thisRootId,
                    '',
                    item.branchRootId
                );
            })
            .join('');

        return renderBubble(msg, 0, 0, thisRootId, repliesHtml);
    }

    function flattenAfterDepth2(msg, depth, visited = new Set(), result = []) {
    (msg.children || []).forEach(child => {
        if (visited.has(child.id)) return;
        visited.add(child.id);

        result.push({
            msg: child,
            realDepth: depth + 1,
            visualDepth: 2,
        });

        flattenAfterDepth2(child, depth + 1, visited, result);
    });

    return result;
}

    // 非 root 不再遞迴渲染 children
    const visualDepth = Math.min(depth, 2);
    return renderBubble(msg, depth, visualDepth, thisRootId, '');
}

    
    // ═══════════════════════════════════════════════════════════
    // 3. 泡泡渲染器
    //    依 visualDepth 決定頭像大小與縱線
    // ═══════════════════════════════════════════════════════════
    function renderBubble(msg, depth, visualDepth, rootId, childrenHtml, branchRootId = null) {
        const AV =
            visualDepth === 0 ? 40 :
            visualDepth === 1 ? 32 :
            visualDepth === 2 ? 28 :
            24;
        const hasChildren = childrenHtml && childrenHtml.trim() !== '';
        const isOpen      = expandedSet.has(msg.id);

        // ★ 縱線：只有 depth === 0 且有子項才畫，其餘一律只有頭像
        const avatarHTML = (depth === 0 && hasChildren) ? `
            <div class="avatar-col" style="width:${AV}px;flex-shrink:0;">
                <img src="${msg.user.profile_photo_url}"
                     style="width:${AV}px;height:${AV}px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <div class="v-line"></div>
            </div>` : `
            <img src="${msg.user.profile_photo_url}"
                 style="width:${AV}px;height:${AV}px;border-radius:50%;flex-shrink:0;object-fit:cover;">`;

        // 收合/展開按鈕（只有根留言才有）
        const replyCount = (msg.children || []).length;
        const toggleBtn = (depth === 0 && hasChildren) ? `
            <button class="toggle-replies-btn ${isOpen ? 'open' : ''}" id="tbtn-${msg.id}"
                    onclick="toggleReplies(${msg.id}, ${replyCount})">
                <span class="arrow">▼</span>
                <span id="tlabel-${msg.id}">${isOpen ? '隱藏回覆' : `查看 ${replyCount} 則回覆`}</span>
            </button>` : '';

            // ✨ 關鍵改動：只要不是根留言 (depth > 0) 且本身擁有子回覆，就渲染收合按鈕
            const hasSubChildren = msg.children && msg.children.length > 0;
            const branchToggleBtn = (depth > 0 && hasSubChildren)
            ? `<button id="tbtn-branch-${msg.id}" onclick="toggleBranch(${msg.id}, this)">收合回覆</button>`
            : '';

        // 回覆 @xxx 標記（depth > 0 才顯示）
        const replyingTo = depth > 0 ? buildReplyingToTag(msg.parent_id) : '';

        // 時間戳
        const timeLabel = buildTimeLabel(msg.created_at);

        // 回覆輸入框
        const replyForm = buildReplyForm(msg.id, rootId);

        // ★ 子回覆區：
        //   depth 0 → replies-inner + rwrap（收合動畫）
        //   depth >= 1 → 直接輸出，不加任何容器，不再疊加縮排
        const repliesSection = hasChildren ? (
            depth === 0
                ? `<div id="rwrap-${msg.id}" class="replies-wrapper ${isOpen ? 'expanded' : ''}">
                       <div class="replies-inner">${childrenHtml}</div>
                   </div>`
                : `<div class="child-replies">${childrenHtml}</div>`
        ) : '';

        // ★ CSS class 控制：
        // ★ 用真實 depth（不是 visualDepth）嚴格控制
        //   depth 0 → 無 class（根留言）
        //   depth 1 → reply-item（第一層 L 型線）
        //   depth 2 → reply-item（第二層 L 型線）
        //   depth 3+ → 空字串，完全不加任何 class，不畫線不縮排
        const branchClass = branchRootId ? `branch-root-${branchRootId}` : '';
        const itemClass = visualDepth > 0
        ? `reply-item reply-depth-${visualDepth} ${branchClass}`
        : '';
        const marginTop = depth === 0 ? '' : 'margin-top:6px;';

        // ✨ 關鍵改動：加入 data-id 與 data-parent-id 供全域遞迴精準找尋子孫
        return `
        <div id="msg-${msg.id}" class="msg-container depth-${visualDepth} ${itemClass}"
            data-id="${msg.id}"
            data-parent-id="${msg.parent_id || ''}"
            data-real-depth="${depth}"
            style="${marginTop}">
            <div class="msg-row">
            ${avatarHTML}
            <div style="flex:1;min-width:0;">
                <div class="bubble">
                    <div style="display:flex;align-items:baseline;gap:5px;flex-wrap:wrap;margin-bottom:2px;">
                        <span style="font-size:${visualDepth===0?'0.85':'0.78'}rem;font-weight:700;color:#374151;">${escHtml(msg.user.name)}</span>
                        ${replyingTo}
                        <span style="font-size:0.68rem;color:#9ca3af;margin-left:auto;">${timeLabel}</span>
                    </div>
                    <p style="font-size:${visualDepth===0?'0.88':'0.84'}rem;color:#1f2937;margin:0;white-space:pre-wrap;"
                       id="content-${msg.id}">${escHtml(msg.content)}</p>
                    ${buildMediaHtml(msg)}
                </div>
                <div class="action-bar">
                    <button onclick="toggleReply(${msg.id}, ${rootId})">回覆</button>
                    ${branchToggleBtn}
                    <button onclick="deleteMsg(${msg.id})" style="color:#f87171;">刪除</button>
                    <button onclick="editMsg(${msg.id})">編輯</button>
                    <button onclick="toggleLike(${msg.id})" class="like-btn ${msg.is_liked ? 'liked' : ''}">
                        ❤️ <span id="lcount-${msg.id}">${msg.likes_count || 0}</span>
                    </button>
                </div>
                ${replyForm}
                ${toggleBtn}
            </div>
            </div>
            ${repliesSection}
        </div>`;
    }

    // ═══════════════════════════════════════════════════════════
    // 4. 回覆輸入框
    // ═══════════════════════════════════════════════════════════
    function buildReplyForm(msgId, rootId) {
        return `
        <div id="rform-${msgId}" class="reply-form-wrap">
            <form onsubmit="submitReply(event, ${rootId})" style="display:flex;gap:8px;width:100%;align-items:center;">
                <input type="hidden" name="parent_id" value="${msgId}">
                <input type="text" name="content" placeholder="回覆...">
                <label class="attach-btn" title="上傳圖片或影片">
                    📎<input type="file" name="media" accept="image/*,video/mp4,video/mov,video/ogg"
                       style="display:none;" onchange="previewMedia(this,'fprev-${msgId}')">
                </label>
                <button type="submit" class="send-btn">送出</button>
            </form>
            <div id="fprev-${msgId}" class="media-preview"></div>
        </div>`;
    }

    // ═══════════════════════════════════════════════════════════
    // 5. 輔助函式
    // ═══════════════════════════════════════════════════════════

    // 「回覆 @XXX」標籤，點擊跳到原留言
    function buildReplyingToTag(parentId) {
        const parent = globalMsgMap.get(parentId);
        if (!parent) return '';
        return `<span style="font-size:0.68rem;color:#6b7280;">回覆</span>
                <span style="font-size:0.68rem;font-weight:600;color:#2563eb;cursor:pointer;"
                      onclick="scrollToMsg(${parentId})">@${escHtml(parent.user.name)}</span>`;
    }

    // 相對時間戳
    function buildTimeLabel(createdAt) {
        if (!createdAt) return '';
        const d    = new Date(createdAt);
        const diff = Math.floor((Date.now() - d) / 1000);
        if (diff < 60)    return '剛剛';
        if (diff < 3600)  return Math.floor(diff / 60) + ' 分鐘前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
        if (diff < 604800)return Math.floor(diff / 86400) + ' 天前';
        return `${d.getMonth()+1}/${d.getDate()}`;
    }

    // 媒體 HTML
    function buildMediaHtml(msg) {
        if (msg.media_type === 'image' && msg.image_path)
            return `<div class="media-preview"><img src="/storage/${msg.image_path}"
                        onclick="openLightbox('image','/storage/${msg.image_path}')"></div>`;
        if (msg.media_type === 'video' && msg.video_path)
            return `<div class="media-preview"><video controls preload="metadata">
                        <source src="/storage/${msg.video_path}" type="video/mp4">
                        您的瀏覽器不支援影片播放。</video></div>`;
        return '';
    }

    // XSS 防護
    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ═══════════════════════════════════════════════════════════
    // 6. 收合 / 展開（只用於根留言）
    // ═══════════════════════════════════════════════════════════
    window.toggleReplies = function(rootId, count) {
        const wrap  = document.getElementById(`rwrap-${rootId}`);
        const btn   = document.getElementById(`tbtn-${rootId}`);
        const label = document.getElementById(`tlabel-${rootId}`);
        if (!wrap) return;
        const isOpen = wrap.classList.contains('expanded');
        if (isOpen) {
            wrap.classList.remove('expanded');
            btn.classList.remove('open');
            label.textContent = `查看 ${count} 則回覆`;
            expandedSet.delete(rootId);
        } else {
            wrap.classList.add('expanded');
            btn.classList.add('open');
            label.textContent = '隱藏回覆';
            expandedSet.add(rootId);
        }
    };

    // ═══════════════════════════════════════════════════════════
    // 7. 切換回覆框
    // ═══════════════════════════════════════════════════════════
    window.toggleReply = function(msgId, rootId) {

        // 核心修正：找到明確的表單 ID
        const formId = `rform-${msgId}`;
        const form = document.getElementById(formId);

        if (!form) {
            console.error("找不到表單 DOM，ID:", formId);
            return;
        }

        // 切換顯示狀態
        form.classList.toggle('show');

        // 如果顯示表單，自動聚焦
        if (form.classList.contains('show')) {
            const input = form.querySelector('input[name="content"]');
            if (input) input.focus();
        }

        // 自動展開根留言回覆區
        if (rootId && rootId !== msgId) {
            const wrap = document.getElementById(`rwrap-${rootId}`);
            if (wrap && !wrap.classList.contains('expanded')) {
                wrap.classList.add('expanded');
                const btn   = document.getElementById(`tbtn-${rootId}`);
                const label = document.getElementById(`tlabel-${rootId}`);
                if (btn) {
                    btn.classList.add('open');
                    const label = document.getElementById(`tlabel-${rootId}`);
                    if (label) label.textContent = '隱藏回覆';
}       
            }
        }
    }; // 👈 確實閉合全域 toggleReply 函式

    // ✨ 全新無限層級樹狀遞迴收合邏輯
    window.toggleBranch = function(msgId, btn) {
        const isCollapsing = btn.textContent === '收合回覆';

        // 建立遞迴輔助函式，向下搜群所有子孫元素
        function toggleDescendants(id, hide) {
            const children = document.querySelectorAll(`.msg-container[data-parent-id="${id}"]`);
            children.forEach(child => {
                const childId = child.getAttribute('data-id');

                if (hide) {
                   // 執行隱藏：當前子節點加入隱藏 class，並強制將所有下層子孫一併隱藏
                   child.classList.add('branch-hidden');
                   toggleDescendants(childId, true);
                } else {
                   // 執行展開：移除隱藏 class
                   child.classList.remove('branch-hidden');
                   
                  // 【狀態記憶防呆】檢查此子節點本身的按鈕狀態
                  // 如果該子節點之前已經被使用者單獨點擊「收合」了，就不應該被動跟著父級一起炸開
                  const childBtn = document.getElementById(`tbtn-branch-${childId}`);
                  if (!childBtn || childBtn.textContent !== '展開回覆') {
                      toggleDescendants(childId, false);
                    }  
                }
            });
        }
        
        // 啟動遞迴追蹤
        toggleDescendants(msgId, isCollapsing);

        // 切換按鈕文字狀態
        btn.textContent = isCollapsing ? '展開回覆' : '收合回覆';
        
    };


    // ═══════════════════════════════════════════════════════════
    // 8. 送出回覆
    // ═══════════════════════════════════════════════════════════
    window.submitReply = function(e, rootId) {
        e.preventDefault();
        const form = e.target;
        const contentInput = form.querySelector('input[name="content"]');
        const fileInput    = form.querySelector('input[type="file"]');
        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體'); return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST', body: new FormData(form),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(() => {
            expandedSet.add(rootId);
            loadMessages();
        });
    };

    // ═══════════════════════════════════════════════════════════
    // 9. 送出新貼文
    // ═══════════════════════════════════════════════════════════
    window.submitPost = function(e) {
        e.preventDefault();
        const fi = e.target.querySelector('input[type="file"]');
        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB'); fi.value = ''; return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST', body: new FormData(e.target),
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(d => { if (d.success) { loadMessages(); e.target.reset(); } });
    };

    // ═══════════════════════════════════════════════════════════
    // 10. 刪除
    // ═══════════════════════════════════════════════════════════
    window.deleteMsg = function(id) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        }).then(r => r.json()).then(d => { if (d.success) loadMessages(); });
    };

    // ═══════════════════════════════════════════════════════════
    // 11. 編輯
    // ═══════════════════════════════════════════════════════════
    window.editMsg = function(id) {
        const p = document.getElementById(`content-${id}`);
        if (!p) return;
        const orig = p.innerText;
        p.innerHTML = `
            <textarea id="edit-ta-${id}" rows="2"
                style="width:100%;font-size:0.84rem;border:1px solid #d1d5db;border-radius:8px;padding:6px;resize:none;">${orig}</textarea>
            <div style="display:flex;gap:6px;margin-top:4px;">
                <button onclick="saveEdit(${id})"
                    style="font-size:0.75rem;background:#3b82f6;color:#fff;padding:3px 10px;border-radius:6px;border:none;cursor:pointer;">儲存</button>
                <button onclick="loadMessages()"
                    style="font-size:0.75rem;background:#e5e7eb;color:#374151;padding:3px 10px;border-radius:6px;border:none;cursor:pointer;">取消</button>
            </div>`;
    };
    window.saveEdit = function(id) {
        const val = document.getElementById(`edit-ta-${id}`)?.value;
        if (!val) return;
        fetch(`/messages/${id}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ content: val }),
        }).then(r => r.json()).then(d => { if (d.success) loadMessages(); });
    };

    // ═══════════════════════════════════════════════════════════
    // 12. 點讚
    // ═══════════════════════════════════════════════════════════
    window.toggleLike = function(id) {
        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        }).then(() => loadMessages());
    };

    // ═══════════════════════════════════════════════════════════
    // 13. 媒體預覽 / Lightbox
    // ═══════════════════════════════════════════════════════════
    window.previewMedia = function(input, previewId) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 50 * 1024 * 1024) { alert('檔案太大，最大限制為 50MB'); input.value = ''; return; }
        const url = URL.createObjectURL(file);
        const el  = document.getElementById(previewId);
        if (!el) return;
        el.innerHTML = file.type.startsWith('image/')
            ? `<img src="${url}" style="max-width:140px;border-radius:10px;margin-top:4px;">`
            : `<video src="${url}" controls style="max-width:180px;border-radius:10px;margin-top:4px;"></video>`;
    };
    window.openLightbox = function(type, src) {
        document.getElementById('lightbox-content').innerHTML =
            type === 'image' ? `<img src="${src}">` : `<video src="${src}" controls autoplay></video>`;
        document.getElementById('lightbox').classList.add('active');
    };
    window.closeLightbox = function() {
        document.getElementById('lightbox').classList.remove('active');
        document.getElementById('lightbox-content').innerHTML = '';
    };
    document.getElementById('lightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLightbox();
    });

    // ═══════════════════════════════════════════════════════════
    // 14. 高亮 / 跳轉
    // ═══════════════════════════════════════════════════════════
    function highlightMsg(msgId) {
        document.querySelectorAll('.msg-highlight').forEach(el => el.classList.remove('msg-highlight'));
        const el = document.getElementById(`msg-${msgId}`);
        if (el) el.classList.add('msg-highlight');
    }
    window.scrollToMsg = function(msgId) {
        const el = document.getElementById(`msg-${msgId}`);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('msg-highlight');
        setTimeout(() => el.classList.remove('msg-highlight'), 1500);
    };

    // ═══════════════════════════════════════════════════════════
    // 15. 全域檔案驗證
    // ═══════════════════════════════════════════════════════════
    document.addEventListener('change', function(e) {
        if (e.target.type === 'file') {
            const file = e.target.files[0];
            if (file && file.size > 50 * 1024 * 1024) {
                alert('檔案太大，最大限制為 50MB'); e.target.value = '';
            }
        }
    });

    // ═══════════════════════════════════════════════════════════
    // 16. 載入
    // ═══════════════════════════════════════════════════════════
    function loadMessages() {
        fetch('/api/messages').then(r => r.json()).then(renderMessages);
    }
    loadMessages();
    </script>
</x-app-layout>
