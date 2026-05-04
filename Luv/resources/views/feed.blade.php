<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">生活牆</h2>
            @include('components.message-form')
            <div id="messages-list" class="space-y-4"></div>
        </div>
    </div>

    <script>
        // 發送留言後的呈現邏輯，氣泡框
        function renderMessages(messages) {
            const list = document.getElementById('messages-list');
            list.innerHTML = '';
            
            // 遍歷所有留言，根據深度判斷縮排
            messages.forEach(msg => {
                 const marginClass = (msg.depth >= 1) ? 'ml-12' : 'ml-0';
                // 圖片渲染判斷
           const imageHtml = msg.image_path ? `
               <div class="mt-3">
                   <img src="/storage/${msg.image_path}" class="max-w-xs rounded-xl shadow-sm border border-gray-100">
               </div>
           ` : '';
                const html = `
                    <div id="message-${msg.id}" class="${marginClass} mt-4">
                    <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm">
                                <p class="text-xs text-gray-500 mb-1">${msg.user.name}</p>
                                <p class="text-sm text-gray-800">${msg.content}</p>
                                <!-- 將圖片放在這裡，按鈕上方 -->
                                 ${imageHtml}
                                <!-- 平行排列的操作區：回覆、刪除、讚 -->
                                <div class="flex items-center gap-4 mt-3 border-t pt-2">
                                    <button onclick="toggleReply(${msg.id})" class="text-xs text-gray-500 hover:text-blue-600">回覆</button>
                                    <button onclick="deleteMessage(${msg.id})" class="text-xs text-gray-500 hover:text-red-600">刪除</button>
                                    <button onclick="editMessage(${msg.id})" class="text-xs text-gray-500 hover:text-blue-600">編輯</button>
                                    <button onclick="toggleLike(${msg.id})" class="flex items-center gap-1 text-xs ${msg.is_liked ? 'text-pink-600' : 'text-gray-400'}">
                                        <span>❤️</span> <span>${msg.likes_count || 0}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="reply-form-${msg.id}" class="hidden mt-2 ${marginClass}">
                            <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">

                                <input type="hidden" name="parent_id" value="${msg.id}">
                                <input type="text" name="content" required placeholder="回覆..." class="rounded-full text-sm px-4 py-1.5 border w-full">
                                <button type="submit" class="text-pink-600 text-sm font-bold px-3">送出</button>
                            </form>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', html);
            });
        }

        function toggleReply(id) { document.getElementById(`reply-form-${id}`).classList.toggle('hidden'); }
        // 在這裡貼上您的 deleteMessage 函式
        function deleteMessage(messageId) {
            if (!confirm('確定要刪除這則訊息嗎？')) return;
            
            fetch(`/messages/${messageId}`, {
                 method: 'DELETE',
                  headers: {
                       'X-CSRF-TOKEN':
                          document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    }
                })
                .then(r=> r.json())
                .then(data => {
                    if(data.success) {
                        loadMessages(); // 重新載入列表
                    } else {
                        alert(data.message || '刪除失敗');
                    }
                })
              .catch(error => {    // 3. 處理請求失敗的情況（例如網路中斷）
              console.error('Error:', error);
              alert('發生錯誤，請稍後再試');
        });
        }
        // 切換編輯模式 UI
        function editMessage(messageId) {
            const messageDiv = document.querySelector(`#message-${messageId} p:nth-child(2)`);
            const originalContent = messageDiv.innerText;
            
           messageDiv.innerHTML = `
                <textarea id="edit-textarea-${messageId}" class="w-full text-sm border rounded-lg p-2">${originalContent}</textarea>
                <div class="flex gap-2 mt-2">
                    <button onclick="saveEdit(${messageId})" class="text-xs bg-blue-500 text-white px-2 py-1 rounded">儲存</button>
                    <button onclick="loadMessages()" class="text-xs bg-gray-300 text-black px-2 py-1 rounded">取消</button>
                </div>
            `;
        }
        // 呼叫後端執行更新 (PATCH)
        function saveEdit(messageId) {const newContent = document.getElementById(`edit-textarea-${messageId}`).value;

            fetch(`/messages/${messageId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ content: newContent })
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    loadMessages(); // 重新載入列表以顯示更新後的內容
                } else {
                    console.log('後端回傳的數據:', data);
                    //  alert('更新失敗: ' + JSON.stringify(data));
                    alert(data.message || '更新失敗');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('發生錯誤，請稍後再試');
            });
                
        }
        
        // 按讚功能邏輯
        function toggleLike(messageId){
            fetch(`/messages/${messageId}/like`, {
                method: `POST`,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').
                    content
                }
            }).then(() => loadMessages());
        }
        // 即時更新功能邏輯
        function submitPost(event) {
        event.preventDefault(); 
        const form = event.target;
        const formData = new FormData(form);

        fetch("{{ route('messages.store') }}", {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                loadMessages(); 
                form.reset();   
            } else {
                alert('送出失敗');
            } 
        })
        .catch(error => {
            console.error('錯誤:', error);
        });
    }
        
        function submitReply(e, parentId) {
            e.preventDefault();
            const form = e.target;
            fetch("{{ route('messages.store') }}", {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            }).then(() => loadMessages());
        }

        function loadMessages() {
            fetch('/api/messages').then(r => r.json()).then(renderMessages); }
        loadMessages();
    </script>
</x-app-layout>