<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('個人主頁') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- 區塊 1：個人資料 (原本的) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- 區塊 2：修改密碼 (原本的) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- ⭐️ 這裡就是【新增插入】的 Google 綁定卡片 ⭐️ --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                   <h3 class="text-lg font-medium text-gray-900 mb-2">第三方帳號綁定</h3>
                   
                {{-- 顯示綁定成功或失敗提示 --}}
                @if (session('status') === 'google-linked')
                     <p class="text-sm text-green-600 mb-2">✅ Google 帳號已成功綁定！</p>                                  
                @endif
                
                <div class="flex items-center justify-between mt-4">
                    <div class="flex items-center space-x-3">
                        <span class="font-medium text-gray-700">Google 帳號</span>
                        @if (auth()->user()->google_id)
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full font-semibold">已綁定</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full font-semibold">未綁定</span>
                        @endif
                    </div>
                    {{-- 如果尚未綁定，顯示按鈕 --}}
                    @if (!auth()->user()->google_id)
                        <a href="{{ route('google.login') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 borderborder-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                            綁定 Google 帳號
                        </a>
                    @endif
                </div>
                </div>
            </div>
            
            {{-- 區塊 3：刪除帳號 (原本的) --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
