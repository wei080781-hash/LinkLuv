<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-6">
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-8">最新生活動態</h2>
            <div id="messages-list" class="space-y-4"></div>
        </div>
    </div>

    <script>
        function renderMessages(messages) {
            const list = document.getElementById('messages-list');
            list.innerHTML = '';
            
            // 遍歷所有留言，根據深度判斷縮排
            messages.forEach(msg => {
                const depth = parseInt(msg.depth);
                // 邏輯：Depth 0 = ml-0, Depth 1+ = ml-12
                const marginClass = (depth >= 1) ? 'ml-12' : 'ml-0';
                
                const html = `
                    <div id="message-${msg.id}" class="${marginClass} mt-4">
                        <div class="flex items-start gap-3">
                            <img src="https://i.pravatar.cc/40?u=${msg.id}" class="w-8 h-8 rounded-full flex-shrink-0">
                            <div class="flex-grow bg-white border border-gray-100 p-4 rounded-2xl shadow-sm">
                                <p class="text-xs text-gray-500 mb-1">${msg.user.name}</p>
                                <p class="text-sm text-gray-800">${msg.content}</p>
                                <div class="flex gap-4 mt-2">
                                    <button onclick="toggleReply(${msg.id})" class="text-xs text-gray-400 hover:text-pink-600">回覆</button>
                                    <button onclick="deleteMessage(${msg.id})" class="text-xs text-gray-400 hover:text-red-600">刪除</button>
                                </div>
                            </div>
                        </div>
                        <div id="reply-form-${msg.id}" class="hidden mt-3">
                            <form onsubmit="submitReply(event, ${msg.id})" class="flex gap-2">
                                @csrf
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
        
        function submitReply(e, parentId) {
            e.preventDefault();
            const form = e.target;
            fetch("{{ route('messages.store') }}", {
                method: 'POST',
                body: new FormData(form),
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}
            }).then(() => loadMessages());
        }

        function loadMessages() {
            fetch('/api/messages').then(r => r.json()).then(renderMessages);
        }

        loadMessages();
    </script>
</x-app-layout>