<!-- 留言框模板 -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-8">
    <h3 class="text-lg font-bold text-gray-700 mb-4">發表動態</h3>
    <form onsubmit="return submitPost(this, event)" class="message-form" enctype="multipart/form-data">
        @csrf
        <textarea name="content" required placeholder="說點什麼..." class="w-full border-gray-200 rounded-lg focus:ring-pink-500 focus:border-pink-500 shadow-sm"></textarea>
        <input type="file" name="media" class="mt-3 text-sm text-gray-500">
        <div class="flex justify-end mt-3">
            <button type="submit" class="bg-pink-600 text-white px-6 py-2 rounded-full font-semibold hover:bg-pink-700 transition">送出</button>  
        </div>
    </form>
 </div>  