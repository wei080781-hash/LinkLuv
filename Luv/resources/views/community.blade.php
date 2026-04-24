@extends('layouts.app')

@section('title', '同好圈 - LinkLuv')

@section('content')
    <div class="container mx-auto py-12 px-6">
        <h1 class="text-4xl font-bold mb-8 text-gray-800">探索同好圈</h1>
        <p class="text-lg text-gray-600 mb-10">加入妳感興趣的圈子，遇見與妳頻率相同的人。</p>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- 範例圈子 -->
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 hover:shadow-xl transition-shadow">
                <div class="w-16 h-16 bg-pink-100 rounded-full flex items-center justify-center text-2xl mb-4">📸</div>
                <h2 class="text-xl font-bold mb-2">攝影愛好者</h2>
                <p class="text-gray-600 mb-4">分享底片、數位攝影心得，交流攝影技巧。</p>
                <button class="text-pink-600 font-bold hover:text-pink-700">加入圈子 →</button>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 hover:shadow-xl transition-shadow">
                <div class="w-16 h-16 bg-pink-100 rounded-full flex items-center justify-center text-2xl mb-4">🍳</div>
                <h2 class="text-xl font-bold mb-2">居家烹飪</h2>
                <p class="text-gray-600 mb-4">從簡單午餐到精緻甜點，分享妳的私房食譜。</p>
                <button class="text-pink-600 font-bold hover:text-pink-700">加入圈子 →</button>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 hover:shadow-xl transition-shadow">
                <div class="w-16 h-16 bg-pink-100 rounded-full flex items-center justify-center text-2xl mb-4">✈️</div>
                <h2 class="text-xl font-bold mb-2">城市旅行</h2>
                <p class="text-gray-600 mb-4">計畫下一次的輕旅行，尋找志同道合的旅伴。</p>
                <button class="text-pink-600 font-bold hover:text-pink-700">加入圈子 →</button>
            </div>
        </div>
    </div>
@endsection
