<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Message;
use App\Jobs\CompressVideoJob;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $perPage = 20;
        // 1. 從 Redis 讀取全域訊息快取 (若無則查詢 DB 並存入 1 小時)
        $messages = Cache::remember("messages_feed_page_{$page}", 60, function () use ($page, $perPage) {
             return Message::with(['user', 'parent.user'])
                 ->withCount('likes')
                 ->orderBy('path', 'ASC') // 物化路徑排序，確保父在子前
                 ->paginate($perPage, ['*'], 'page', $page);
         });

         // 2. 獲取目前用戶的點讚清單 (不快取，因為每個人不同)
         $likedIds = auth()->check() 
            ? auth()->user()->likes()->pluck('message_id', 'message_id')->toArray() 
            : [];

        
        $items = collect($messages->items())->map(function ($msg) use ($likedIds) {
            $msg->is_liked = isset($likedIds[$msg->id]);
            // ★ 附加 parent_user_name，供前端 @mention 使用
            $msg->parent_user_name = $msg->parent?->user?->name ?? null;
            return $msg;
        });
        
        return response()->json([
            'data' => $items,
            'has_more' => $messages->hasMorePages(),
            'next_page' => $messages->currentPage() + 1,
        ]);    
    }

    // 處理留言的儲存與列表獲取，並處理階層深度邏輯。
    public function store(Request $request)
    {
        \Log::info('Files', $request->allFiles());
        \Log::info('Input', $request->except(['+']));

        $validated = $request->validate([
            'content'   => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:messages,id',
            'media'     => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,ogg|max:51200',
        ]);

        // ★ 內容與媒體至少要有一個
        if (empty($validated['content']) && !$request->hasFile('media')) {
            return response()->json(['success' => false, 'message' => '請輸入內容或上傳媒體'], 422);
        }
        
        $parentId = $request->input('parent_id');
        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mime = $file->getMimeType(); 
            \Log::info('偵測到檔案類型: ' . $mime);

            if (str_contains($mime, 'image')) {
                // 圖片：立即處理壓縮並直接上傳 S3
                $mediaPath = $this->handleImageUpload($file);
                $mediaType = 'image';
            } elseif (str_contains($mime, 'video')) {
                // 影片：先存入本地原始路徑(public)，讓背景 Job 可以用 FFmpeg 讀取壓縮
                $mediaPath = $file->store('videos', 'public');
                $mediaType = 'video';
            }
            \Log::info('最終媒體類型判斷為: ' . ($mediaType ?? 'null'));
        }
        
        // 初始化深度與路徑
        $depth = 0;
        $threadId = null;

        if ($parentId) {
            $parent = Message::findOrFail($parentId);
            $depth = $parent->depth + 1;
            $threadId = $parent->thread_id ?? $parent->id;
        }

        // 先建立留言
        $message = Message::create([
            'user_id'    => auth()->id(),
            'content'    => $request->content ?? '',
            'parent_id'  => $parentId,
            'image_path' => ($mediaType === 'image') ? $mediaPath : null,
            'video_path' => ($mediaType === 'video') ? $mediaPath : null,
            'media_type' => $mediaType,
            'depth'      => $depth,
        ]);

        // 更新物化路徑 (Materialized Path)
        $paddedId = str_pad($message->id, 10, '0', STR_PAD_LEFT);
        $path = $parentId ? (Message::findOrFail($parentId)->path . '.' . $paddedId) : $paddedId;
        $threadId = $parentId ? Message::findOrFail($parentId)->thread_id : $message->id;
        
        $message->update([
            'path' => $path,
            'thread_id' => $threadId
        ]);

        // 維護 Closure Table 關係
        if ($parentId) {
            $this->storeClosure($message->id, $parentId);
        } else {
            DB::table('message_closure')->insert(['ancestor' => $message->id, 'descendant' => $message->id]);
        }

        // 若為影片，啟動背景壓縮任務
        if ($mediaType === 'video') {
            CompressVideoJob::dispatch($message);
        }

        // 清除快取並回傳
        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("messages_feed_page_{$i}");
        } 

        // 💡【本次新增的核心邏輯】
        // 1. 預先載入 user 關聯，讓前端能順利讀取到頭像與名稱
        $message->load(['user', 'parent.user']);
        
        // 2. 手動注入與 index() 方法相同的虛擬擴充屬性，防止前端 JavaScript 渲染時出錯
        $message->likes_count = 0;
        $message->is_liked = false;
        $message->parent_user_name = $message->parent?->user?->name ?? null;
        return response()->json(['success' => true, 'data' => $message]);
    }

    // 處理圖片優化與上傳至 S3
    private function handleImageUpload($file)
{
    if (!extension_loaded('gd')) {
        dd('GD 擴展未載入！請檢查您的 Web 伺服器 PHP 設定。');
    }

    $filename = 'images/' . time() . '_' . $file->hashName();
    $manager = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
    $img = $manager->read($file);

    if ($img->width() > 1200) {
        $img->scale(width: 1200);
        $encoded = $img->toJpeg(quality: 80); 
    } else {
        $encoded = $img->toJpeg(quality: 90); 
    }
    
    // 🚀 修正：把 'public' 改成 's3'，讓圖片直飛雲端！
    Storage::disk('s3')->put($filename, (string) $encoded);
    return $filename;
}
        
        
    

    // 處理樹狀 Closure 表關聯
    private function storeClosure($descendantId, $parentId) 
    {
        DB::table('message_closure')->insert(['ancestor' => $descendantId, 'descendant' => $descendantId]);
        
        $ancestors = DB::table('message_closure')->where('descendant', $parentId)->get();
        foreach ($ancestors as $a) {
            DB::table('message_closure')->insert([
                'ancestor' => $a->ancestor,
                'descendant' => $descendantId
            ]);
        }
    }

    public function destroy(Message $message)
    {
        \Log::info('刪除嘗試:', [
            'current_user_id' => auth()->id(),
            'message_owner_id' => $message->user_id,
            'message_id' => $message->id
        ]);

        if ($message->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '無權刪除'], 403);
        }

        $message->replies()->delete();
        $message->delete();

        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("messages_feed_page_{$i}");
        }     
        return response()->json(['success' => true]);
    }

    public function update(Request $request, Message $message)
    {
        \Log::info('Update 請求:', [
            'message_id' => $message->id,
            'input_data' => $request->all(),
            'current_user' => auth()->id(),
            'message_owner' => $message->user_id
        ]);

        if ($message->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '您沒有權限修改此訊息'
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);
        
        $message->update([
            'content' => $validated['content'],
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("messages_feed_page_{$i}");
        } 
        return response()->json(['success' => true]);
    }

    public function like(Message $message) 
    {
        $userId = auth()->id();
        $like = $message->likes()->where('user_id', $userId)->first();
        
        if ($like) {
            $like->delete();
            $isLiked = false;
        } else {
            $message->likes()->create(['user_id' => $userId]);
            $isLiked = true;
        }

        for ($i = 1; $i <= 10; $i++) {
            Cache::forget("messages_feed_page_{$i}");
        } 

        return response()->json([
             'success' => true, 
             'liked' => $isLiked,
             'likes_count' => $message->likes()->count()
        ]);    
    }
}