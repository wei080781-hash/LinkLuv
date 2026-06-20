<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">生活牆</h2>
            @include('components.message-form')
            <div id="messages-list" class="flex flex-col gap-4"></div>
        </div>
    </div>

    {{-- Lightbox --}}
    <div id="lightbox" class="hidden fixed inset-0 bg-black/85 z-50 items-center justify-center">
        <button onclick="closeLightbox()"
            class="absolute top-4 right-5 text-white text-4xl leading-none bg-transparent border-none cursor-pointer">✕</button>
        <div id="lightbox-content"></div>
    </div>

    <style>
    /* 新增：回覆分支線樣式 */
    .reply-branch {
        position: relative;
    }

    .reply-branch::before {
        content: '';
        position: absolute;
        left: -16px;
        /* 連接父容器的垂直線 */
        top: 22px;
        /* 對準頭像垂直中心 */
        width: 16px;
        /* 線條長度 */
        height: 2px;
        /* 線條粗細 */
        background-color: #e5e7eb;
        /* border-gray-200 的顏色 */
    }

    #lightbox.active {
        display: flex;
    }

    #lightbox img,
    #lightbox video {
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 8px;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
    }

    .replies-wrapper {
        overflow: hidden;
        max-height: 0;
        opacity: 0;
        transition: max-height 0.35s ease, opacity 0.25s ease;
    }

    .replies-wrapper.expanded {
        max-height: 99999px;
        opacity: 1;
    }

    .toggle-arrow {
        display: inline-block;
        transition: transform 0.2s ease;
        font-size: 0.65rem;
    }

    .toggle-open .toggle-arrow {
        transform: rotate(180deg);
    }

    .msg-bubble.msg-highlight {
        background: #dbeafe !important;
        border-color: #93c5fd !important;
        border: 2px solid #93c5fd;
        border-radius: 1rem;
    }

    .msg-bubble {
        transition: background 0.15s;
    }

    .reply-form-wrap {
        display: none;
    }

    .reply-form-wrap.show {
        display: flex;
    }

    .msg-media img,
    .msg-media video {
        max-width: 240px;
        max-height: 240px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        margin-top: 6px;
        display: block;
        cursor: pointer;
    }
    </style>

    <script>
    /* ... (資料處理邏輯保持不變) ... */
    const expandedSet = new Set();
    let globalMsgMap = new Map();

    function renderMessages(messages) {
        const list = document.getElementById('messages-list');
        list.innerHTML = '';
        globalMsgMap = new Map();
        messages.forEach(m => globalMsgMap.set(m.id, {
            ...m,
            children: []
        }));
        const roots = [];
        messages.forEach(m => {
            if (!m.parent_id) roots.push(globalMsgMap.get(m.id));
            else {
                const parent = globalMsgMap.get(m.parent_id);
                if (parent) parent.children.push(globalMsgMap.get(m.id));
                else roots.push(globalMsgMap.get(m.id));
            }
        });
        roots.forEach(root => list.insertAdjacentHTML('beforeend', buildRootHTML(root)));
    }

    function buildRootHTML(msg) {
        const hasReplies = msg.children && msg.children.length > 0;
        const isOpen = expandedSet.has(msg.id);
        const count = msg.children ? msg.children.length : 0;

        const avatarCol = hasReplies ?
            `<div class="flex flex-col items-center flex-shrink-0 w-10">
                   <img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                   <div class="w-0.5 flex-1 bg-gray-300 mt-1 rounded-full min-h-3"></div>
               </div>` :
            `<img src="${msg.user.profile_photo_url}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">`;

        const toggleBtn = hasReplies ?
            `<button onclick="toggleReplies(${msg.id}, ${count})" id="tbtn-${msg.id}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 mt-1 select-none bg-transparent border-none cursor-pointer p-0"><span class="toggle-arrow ${isOpen ? 'rotate-180' : ''}">▼</span><span id="tlabel-${msg.id}">${isOpen ? '隱藏回覆' : `查看 ${count} 則回覆`}</span></button>` :
            '';

        const repliesHtml = hasReplies 
        ? msg.children.map(c => buildReplyHTML(c, msg.id, new Set(), 1)).join('') 
        : '';

        return `
        <div id="msg-${msg.id}" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4" data-id="${msg.id}">
            <div class="flex items-start gap-2.5">
                ${avatarCol}
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-100 hover:bg-gray-200 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-sm font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            <span class="text-xs text-gray-400 ml-auto">${buildTimeLabel(msg.created_at)}</span>
                        </div>
                        <p class="text-sm text-gray-800 m-0 whitespace-pre-wrap" id="content-${msg.id}">${escHtml(msg.content)}</p>
                        ${buildMediaHtml(msg)}
                    </div>
                    <div class="flex gap-3 text-xs text-gray-400 mt-1">
                        <button onclick="toggleReply(${msg.id}, ${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">回覆</button>
                        <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
                        <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>
                        <button onclick="toggleLike(${msg.id})" class="like-btn flex items-center gap-1 bg-transparent border-none cursor-pointer p-0 text-xs ${msg.is_liked ? 'text-pink-500 font-bold' : 'text-gray-400'}">❤️ <span id="lcount-${msg.id}">${msg.likes_count || 0}</span></button>
                    </div>
                    ${buildReplyForm(msg.id, msg.id)}
                    ${toggleBtn}
                </div>
            </div>
            <div id="rwrap-${msg.id}" class="replies-wrapper ${isOpen ? 'expanded' : ''}">
                <div class="ml-5 pl-4 border-l-2 border-gray-200 mt-2">
                    ${repliesHtml}
                </div>
            </div>
        </div>`;
    }

    function buildReplyHTML(msg, rootId, visited, depth = 1) {
        if (visited.has(msg.id)) return '';
        visited.add(msg.id);
        const hasChildren = msg.children && msg.children.length > 0;

        const childrenHtml = hasChildren 
        ? msg.children.map(c => buildReplyHTML(c, rootId, new Set(visited), depth + 1)).join('') 
        : '';
        const branchBtn = hasChildren ?
            `<button class="hover:text-gray-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400" id="bbtn-${msg.id}" onclick="toggleBranch(${msg.id}, this)">▾ 收合</button>` :
            '';
        const replyingTo = buildReplyingToTag(msg.parent_id, rootId);
        const timeLabel = buildTimeLabel(msg.created_at);
        const childrenSection = hasChildren
        ? (depth < 3
            ? `<div id="branch-${msg.id}" class="ml-6 pl-4 border-l-2 border-gray-200 mt-1">${childrenHtml}</div>`
            : `<div id="branch-${msg.id}">${childrenHtml}</div>`)
        : '';

        // 加入 reply-branch class 以觸發 CSS 偽元素
        return `
        <div id="msg-${msg.id}" class="reply-branch relative pt-2.5" data-id="${msg.id}" data-parent-id="${msg.parent_id || ''}">
            <div class="flex items-start gap-2">
                <img src="${msg.user.profile_photo_url}" class="w-7 h-7 rounded-full object-cover flex-shrink-0 mt-0.5 relative z-10">
                <div class="flex-1 min-w-0">
                    <div class="msg-bubble bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-2xl px-3 py-2">
                        <div class="flex items-baseline gap-1 flex-wrap mb-0.5">
                            <span class="text-xs font-bold text-gray-700">${escHtml(msg.user.name)}</span>
                            ${replyingTo}
                            <span class="text-xs text-gray-400 ml-auto">${timeLabel}</span>
                        </div>
                        <p class="text-sm text-gray-800 m-0 whitespace-pre-wrap" id="content-${msg.id}">${escHtml(msg.content)}</p>
                        ${buildMediaHtml(msg)}
                    </div>
                    <div class="flex gap-3 text-xs text-gray-400 mt-1">
                        <button onclick="toggleReply(${msg.id}, ${rootId})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">回覆</button>
                        ${branchBtn}
                        <button onclick="deleteMsg(${msg.id})" class="hover:text-red-500 bg-transparent border-none cursor-pointer p-0 text-xs text-red-300">刪除</button>
                        <button onclick="editMsg(${msg.id})" class="hover:text-blue-600 bg-transparent border-none cursor-pointer p-0 text-xs text-gray-400">編輯</button>
                        <button onclick="toggleLike(${msg.id})" class="like-btn flex items-center gap-1 bg-transparent border-none cursor-pointer p-0 text-xs ${msg.is_liked ? 'text-pink-500 font-bold' : 'text-gray-400'}">❤️ <span id="lcount-${msg.id}">${msg.likes_count || 0}</span></button>
                    </div>
                    ${buildReplyForm(msg.id, rootId)}
                </div>
            </div>
            ${childrenSection}
        </div>`;
    }

    /* ... (其餘函式維持不變) ... */
    function buildReplyForm(msgId, rootId) {
        return `<div id="rform-${msgId}" class="reply-form-wrap items-center gap-2 mt-1.5 flex-wrap"><form onsubmit="submitReply(event, ${rootId})" class="flex gap-2 w-full items-center"><input type="hidden" name="parent_id" value="${msgId}"><input type="text" name="content" placeholder="回覆..." class="flex-1 min-w-24 rounded-full text-sm px-4 py-1.5 border border-gray-300 outline-none focus:border-blue-400 transition-colors"><label class="text-gray-400 text-base cursor-pointer flex-shrink-0" title="上傳圖片或影片">📎<input type="file" name="media" accept="image/*,video/mp4,video/mov,video/ogg" class="hidden" onchange="previewMedia(this,'fprev-${msgId}')"></label><button type="submit" class="text-blue-600 text-sm font-bold bg-transparent border-none cursor-pointer whitespace-nowrap">送出</button></form><div id="fprev-${msgId}" class="msg-media"></div></div>`;
    }

    function buildReplyingToTag(parentId, rootId) {
        if (!parentId || parentId === rootId) return '';
        const parent = globalMsgMap.get(parentId);
        if (!parent) return '';

        // 取前 20 個字作為預覽
        const preview = parent.content ? parent.content.slice(0, 20) + (parent.content.length > 20 ? '...' : '') : '';
        return `
        <span class="text-xs text-gray-400">回覆</span> 
        <span class="text-xs font-semibold text-blue-500 cursor-pointer hover:underline" 
              onclick="scrollToMsg(${parentId})">@${escHtml(parent.user.name)}</span>
        <span class="text-xs text-gray-300">·</span>
        <span class="text-xs text-gray-400 cursor-pointer hover:text-blue-400 hover:underline italic max-w-32 truncate inline-block align-bottom"
              onclick="scrollToMsg(${parentId})"
              title="${escHtml(preview)}">「${escHtml(preview)}」</span>
    `;
}

    function buildTimeLabel(createdAt) {
        if (!createdAt) return '';
        const d = new Date(createdAt);
        const diff = Math.floor((Date.now() - d) / 1000);
        if (diff < 60) return '剛剛';
        if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
        if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
        if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
        return `${d.getMonth()+1}/${d.getDate()}`;
    }

    function buildMediaHtml(msg) {
        // 🚀 已完美對接你們的 AWS S3 儲存桶網址
        const s3BaseUrl = 'https://s3.ap-east-2.amazonaws.com/linkluv-media-bucket/';
        if (msg.media_type === 'image' && msg.image_path) {
           // 💡 判斷路徑：如果是新資料走 S3 就補上 S3 網域，如果是舊資料本地暫存就走 /storage/ 
           const isS3 = msg.image_path.startsWith('images/') || !msg.image_path.startsWith('storage/');
           const imgUrl = isS3 ? `${s3BaseUrl}${msg.image_path}` : `/storage/${msg.image_path}`;
           return `<div class="msg-media"><img src="${imgUrl}" onclick="openLightbox('image','${imgUrl}')"></div>`;
        }
        if (msg.media_type === 'video' && msg.video_path) {
            // 💡 影片經過壓縮後也上傳到 S3 (路徑為 videos/xxx.mp4)，同樣補上 S3 網域
            const isS3 = msg.video_path.startsWith('videos/') || !msg.video_path.startsWith('storage/');
            const videoUrl = isS3 ? `${s3BaseUrl}${msg.video_path}` : `/storage/${msg.video_path}`;
            
            return `<div class="msg-media"><video controls preload="metadata"><source src="${videoUrl}" type="video/mp4">您的瀏覽器不支援影片播放。</video></div>`;
        }
        
        return '';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    window.toggleReplies = function(rootId, count) {
        const wrap = document.getElementById(`rwrap-${rootId}`);
        const btn = document.getElementById(`tbtn-${rootId}`);
        const label = document.getElementById(`tlabel-${rootId}`);
        const arrow = btn ? btn.querySelector('.toggle-arrow') : null;
        if (!wrap) return;
        const isOpen = wrap.classList.contains('expanded');
        if (isOpen) {
            wrap.classList.remove('expanded');
            if (label) label.textContent = `查看 ${count} 則回覆`;
            if (arrow) arrow.classList.remove('rotate-180');
            expandedSet.delete(rootId);
        } else {
            wrap.classList.add('expanded');
            if (label) label.textContent = '隱藏回覆';
            if (arrow) arrow.classList.add('rotate-180');
            expandedSet.add(rootId);
        }
    };
    window.toggleBranch = function(msgId, btn) {
        const branch = document.getElementById(`branch-${msgId}`);
        if (!branch) return;
        const isHidden = branch.classList.contains('hidden');
        if (isHidden) {
            branch.classList.remove('hidden');
            btn.textContent = '▾ 收合';
        } else {
            branch.classList.add('hidden');
            btn.textContent = '▸ 展開';
        }
    };
    window.toggleReply = function(msgId, rootId) {
        if (rootId !== msgId) {
            const wrap = document.getElementById(`rwrap-${rootId}`);
            const label = document.getElementById(`tlabel-${rootId}`);
            const btn = document.getElementById(`tbtn-${rootId}`);
            const arrow = btn ? btn.querySelector('.toggle-arrow') : null;
            if (wrap && !wrap.classList.contains('expanded')) {
                wrap.classList.add('expanded');
                if (label) label.textContent = '隱藏回覆';
                if (arrow) arrow.classList.add('rotate-180');
                expandedSet.add(rootId);
            }
        }
        const form = document.getElementById(`rform-${msgId}`);
        if (!form) return;
        const isShown = form.classList.contains('show');
        if (isShown) {
            form.classList.remove('show');
        } else {
            form.classList.add('show');
            const input = form.querySelector('input[name="content"]');
            if (input) input.focus();
            highlightMsg(msgId);
        }
    };
    window.submitReply = function(e, rootId) {
        e.preventDefault();
        const form = e.target;
        const contentInput = form.querySelector('input[name="content"]');
        const fileInput = form.querySelector('input[type="file"]');
        if (!contentInput.value.trim() && (!fileInput || !fileInput.files.length)) {
            alert('請輸入回覆內容或上傳媒體');
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(() => {
            expandedSet.add(rootId);
            loadMessages();
        });
    };
    window.submitPost = function(e) {
        e.preventDefault();
        const fi = e.target.querySelector('input[type="file"]');
        if (fi?.files.length > 0 && fi.files[0].size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            fi.value = '';
            return;
        }
        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: new FormData(e.target),
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(d => {
            if (d.success) {
                loadMessages();
                e.target.reset();
            }
        });
    };
    window.deleteMsg = function(id) {
        if (!confirm('確定要刪除這則訊息嗎？')) return;
        fetch(`/messages/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.editMsg = function(id) {
        const p = document.getElementById(`content-${id}`);
        if (!p) return;
        const orig = p.innerText;
        p.innerHTML =
            `<textarea id="edit-ta-${id}" rows="2" class="w-full text-sm border border-gray-300 rounded-lg p-2 resize-none">${orig}</textarea><div class="flex gap-2 mt-1"><button onclick="saveEdit(${id})" class="text-xs bg-blue-500 text-white px-3 py-1 rounded-lg border-none cursor-pointer">儲存</button><button onclick="loadMessages()" class="text-xs bg-gray-200 text-gray-700 px-3 py-1 rounded-lg border-none cursor-pointer">取消</button></div>`;
    };
    window.saveEdit = function(id) {
        const val = document.getElementById(`edit-ta-${id}`)?.value;
        if (!val) return;
        fetch(`/messages/${id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                content: val
            }),
        }).then(r => r.json()).then(d => {
            if (d.success) loadMessages();
        });
    };
    window.toggleLike = function(id) {
        fetch(`/messages/${id}/like`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
        }).then(() => loadMessages());
    };
    window.previewMedia = function(input, previewId) {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 50 * 1024 * 1024) {
            alert('檔案太大，最大限制為 50MB');
            input.value = '';
            return;
        }
        const url = URL.createObjectURL(file);
        const el = document.getElementById(previewId);
        if (!el) return;
        el.innerHTML = file.type.startsWith('image/') ? `<img src="${url}" class="max-w-40 rounded-xl mt-1">` :
            `<video src="${url}" controls class="max-w-48 rounded-xl mt-1"></video>`;
    };
    window.openLightbox = function(type, src) {
        document.getElementById('lightbox-content').innerHTML = type === 'image' ? `<img src="${src}">` :
            `<video src="${src}" controls autoplay></video>`;
        document.getElementById('lightbox').classList.add('active');
    };
    window.closeLightbox = function() {
        document.getElementById('lightbox').classList.remove('active');
        document.getElementById('lightbox-content').innerHTML = '';
    };
    document.getElementById('lightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLightbox();
    });

    function highlightMsg(msgId) {
        document.querySelectorAll('.msg-highlight').forEach(el => el.classList.remove('msg-highlight'));
        const el = document.querySelector(`[data-id="${msgId}"]`);
        if (el) el.classList.add('msg-highlight');
    }
    window.scrollToMsg = function(msgId) {
        // 找到目標訊息，展開它所在的 replies-wrapper
        const rootWrap = document.querySelector(`[data-id="${msgId}"]`)?.closest('.replies-wrapper');
        if (rootWrap && !rootWrap.classList.contains('expanded')) {
        rootWrap.classList.add('expanded');
    }

    // 用 data-id 取代 id 避免重複選到
    const el = document.querySelector(`[data-id="${msgId}"]`);
    if (!el) return;
    // 清除舊高亮
    document.querySelectorAll('.msg-highlight').forEach(e => e.classList.remove('msg-highlight'));
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // 只高亮 bubble，不影響子元素
    const bubble = el.querySelector(':scope > .flex > .flex-1 > .msg-bubble, :scope > div > .flex-1 > .msg-bubble');
    const target = bubble || el;
    target.classList.add('msg-highlight');
    setTimeout(() => target.classList.remove('msg-highlight'), 1500);
    };

    function loadMessages() {
        fetch('/api/messages').then(r => r.json()).then(renderMessages);
    }
    loadMessages();
    </script>
</x-app-layout>