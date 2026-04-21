<x-app-layout>
    <div class="py-12 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- 個人檔案 header -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 mb-6 flex items-start gap-6">
                <div class="w-32 h-32 bg-gray-200 rounded-full flex-shrink-0"></div>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-800">王小明</h1>
                    <p class="text-gray-500 mb-2">攝影愛好者 | 尋找旅遊同伴</p>
                    <div class="flex gap-2">
                        <span class="bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-xs font-bold">攝影</span>
                        <span class="bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-xs font-bold">旅行</span>
                        <span class="bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-xs font-bold">美食</span>
                    </div>
                </div>
            </div>

            <!-- 生活照相簿 -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-800 mb-4">生活相簿</h3>
                <div class="flex gap-6 overflow-x-auto pb-4 snap-x">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="flex-shrink-0 w-48 aspect-square bg-white p-3 shadow-md hover:shadow-xl transition-all duration-300 transform hover:-rotate-1 cursor-pointer border border-gray-100 snap-center">
                             <div class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs">照片 {{ $i + 1 }}</div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</x-app-layout>