<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\Message;
use App\Jobs\CompressVideoJob;


class MessageController extends Controller
{
    public function index()
    {
        // 1. 從 Redis 讀取全域訊息快取 (若無則查詢 DB 並存入 1 小時)
        $messages = Cache::remember('global_messages_feed', 60, function () {
             return Message::with(['user', 'parent.user'])   // ← 新增 parent.user
                 ->withCount('likes')
                 ->orderBy('path', 'ASC') // 物化路徑排序，確保父在子前
                 ->get();
         });

         // 2. 獲取目前用戶的點讚清單 (不快取，因為每個人不同)
         // 增加 null 檢查，防止未登入
         $likedIds = auth()->check() 
            ? auth()->user()->likes()->pluck('message_id', 'message_id')->toArray() 
            : [];

        // 3. 在記憶體中動態合併個人化狀態
        return $messages->map(function ($msg) use ($likedIds) {
            $msg->is_liked = isset($likedIds[$msg->id]);

            // ★ 附加 parent_user_name，供前端 @mention 使用
            $msg->parent_user_name = $msg->parent?->user?->name ?? null;

            return $msg;
        });    
    }

    // 處理留言的儲存與列表獲取，並處理階層深度邏輯。
    public function store(Request $request)
    {
        \Log::info('Files', $request->allFiles());
        \Log::info('Input', $request->except(['+']));

        $validated = $request->validate([
            'content'   => 'nullable|string|max:1000',   // ★ 改為 nullable，允許純圖/純影片留言
            'parent_id' => 'nullable|exists:messages,id',
            'media' => 'nullable|file|mimes:jpg,jpeg,png,gif,mp4,mov,ogg|max:51200',
        ]);

        // ★ 內容與媒體至少要有一個
        if (empty($validated['content']) && !$request->hasFile('media')) {
        return response()->json(['success' => false, 'message' => '請輸入內容或上傳媒體'], 422);
        }
        

        $parentId = $request->input('parent_id');
        // 判斷圖片還是影片，並儲存路徑
         $mediaPath = null;
         $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mime = $file->getMimeType(); 
        // 取得檔案的真正類型 (例如 image/jpeg 或 video/mp4)
        \Log::info('偵測到檔案類型: ' . $mime);

        if (str_contains($mime, 'image')) {
            // 圖片：立即處理壓縮
            $mediaPath = $this->handleImageUpload($file);
            $mediaType = 'image';
        } elseif (str_contains($mime, 'video')) {
            // 影片：存入原始路徑，由 Job 背景轉碼
            $mediaPath = $file->store('videos', 'public');
            $mediaType = 'video';
        }
        \Log::info('最終媒體類型判斷為: ' . ($mediaType ?? 'null'));
    }
        
        // 1.初始化深度與路徑
        $depth = 0;
        $threadId = null;

        if ($parentId) {
            $parent = Message::findOrFail($parentId);
            $depth = $parent->depth + 1;
            // 物化路徑：父路徑 + 暫存ID (這裡我們用一個假 ID 或之後再更新)
            // 建議：先建立再更新路徑，或者使用一個遞增序號
            $threadId = $parent->thread_id ?? $parent->id;
        }

        // 先建立留言
        $message = Message::create([
            'user_id'   => auth()->id(),
            'content'    => $request->content ?? '',
            'parent_id' => $parentId,
            'image_path' => ($mediaType === 'image') ? $mediaPath : null,
            'video_path' => ($mediaType === 'video') ? $mediaPath : null,
            'media_type' => $mediaType,
            'depth'      => $depth,
        ]);

        // 3. 更新物化路徑 (Materialized Path)
        $paddedId = str_pad($message->id, 10, '0', STR_PAD_LEFT); // 固定 10 位長度
        $path = $parentId ? (Message::findOrFail($parentId)->path . '.' . $paddedId) : $paddedId;
        $threadId = $parentId ? Message::findOrFail($parentId)->thread_id : $message->id;
        
        // 4. 更新路徑與正確的 thread_id
        $message->update([
            'path' => $path,
            'thread_id' => $threadId
        ]);

        // 5. 維護 Closure Table 關係
        if ($parentId) {
            $this->storeClosure($message->id, $parentId);
            } else {
                DB::table('message_closure')->insert(['ancestor' => $message->id, 'descendant' => $message->id]);
            }
            // 6. 若為影片，啟動背景壓縮任務
        if ($mediaType === 'video') {
            CompressVideoJob::dispatch($message);
        }

        // 7. 清除快取並回傳
        Cache::forget('global_messages_feed'); // 刪除全域快取，讓下一次讀取時重新生成
        return response()->json(['success' => true]);
}

// 處理圖片優化
private function handleImageUpload($file)
{
  if (!extension_loaded('gd')) {
     dd('GD 擴展未載入！請檢查您的 Web 伺服器 PHP 設定。');
}

  $filename = 'images/' . time() . '_' . $file->hashName();
    // 使用 v3.x 的 Manager 語法，指定使用 GD 驅動  
  $manager = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
  $img = $manager->read($file);

  // [新增邏輯]：判斷寬度是否大於 1200px
  if ($img->width() > 1200) {
      // 大圖：進行縮放與壓縮  
      $img->scale(width: 1200);
      $encoded = $img->toJpeg(quality: 80); // 品質設為 80，畫質會更好
    } else {
        // 小圖：保持原尺寸，僅進行輕微編碼以確保格式統一為 JPG
        $encoded = $img->toJpeg(quality: 90); // 小圖不需要縮小，品質維持高標準
    }
    
    // 儲存圖片到 public 存儲
    Storage::disk('s3')->put($filename, (string) $encoded);
    return $filename;
}
private function handleImageUpload($file)
{
  if (!extension_loaded('gd')) {
     dd('GD 擴展未載入！請檢查您的 Web 伺服器 PHP 設定。');
}

  $filename = 'images/' . time() . '_' . $file->hashName();
    // 使用 v3.x 的 Manager 語法，指定使用 GD 驅動
  $manager = new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
  $img = $manager->read($file);

  // [新增邏輯]：判斷寬度是否大於 1200px
  if ($img->width() > 1200) {
      // 大圖：進行縮放與壓縮
      $img->scale(width: 1200);
      $encoded = $img->toJpeg(quality: 80); // 品質設為 80，畫質會更好
    } else {
        // 小圖：保持原尺寸，僅進行輕微編碼以確保格式統一為 JPG
        $encoded = $img->toJpeg(quality: 90); // 小圖不需要縮小，品質維持高標準
    }

    // 儲存圖片到 public 存儲
    Storage::disk('public')->put($filename, (string) $encoded);
    return $filename;
}

// 處理樹狀 Closure 表關聯
private function storeClosure($descendantId, $parentId)
{
    // 1. 建立自身關聯
    \DB::table('message_closure')->insert(['ancestor' => $descendantId, 'descendant' => $descendantId]);

    // 2. 繼承父留言的所有祖先關係
    $ancestors = \DB::table('message_closure')->where('descendant', $parentId)->get();

// 處理樹狀 Closure 表關聯
private function storeClosure($descendantId, $parentId) 
{
    // 1. 建立自身關聯
    \DB::table('message_closure')->insert(['ancestor' => $descendantId, 'descendant' => $descendantId]);
    
    // 2. 繼承父留言的所有祖先關係
    $ancestors = \DB::table('message_closure')->where('descendant', $parentId)->get();
    foreach ($ancestors as $a) {
        \DB::table('message_closure')->insert([
            'ancestor' => $a->ancestor,
            'descendant' => $descendantId
        ]);
    }
}

    public function destroy(Message $message)
    {
        // === 在此處加入除錯紀錄 ===
        \Log::info('刪除嘗試:', [
            'current_user_id' => auth()->id(),
            'message_owner_id' => $message->user_id,
            'message_id' => $message->id
        ]);
        // 權限檢查
        if ($message->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => '無權刪除'], 403);
        }
        // 同時刪除子留言（回覆）
        $message->replies()->delete();
        $message->delete();

        // 關鍵：清除快取，讓下一次讀取時重新生成
        Cache::forget('global_messages_feed');    
        return response()->json(['success' => true]);
    }
    // 更新內容方式
    public function update(Request $request, Message $message)
    {
        \Log::info('Update 請求:', [
                'message_id' => $message->id,
                'input_data' => $request->all(),
                'current_user' => auth()->id(),
                'message_owner' => $message->user_id
            ]);
        //確認是本人的留言才能修改
        if ($message->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => '您沒有權限修改此訊息'
            ], 403);
        }
        \Log::info('驗證前...');

        $validated = $request->validate([
        'content' => 'required|string|max:1000',
    ]);

         \Log::info('驗證通過，準備執行更新...');
    
    $message->update([
        'content' => $validated['content'],
    ]);
    // 關鍵：清除快取，讓下一次讀取時重新生成
    Cache::forget('global_messages_feed');

    return response()->json(['success' => true]);
    }
    


    public function like(Message $message) 
    {
        $userId = auth()->id();
        // 檢查使用者是否已經點過讚
         $like = $message->likes()->where('user_id',
         $userId)->first();
        
        if ($like) {
            $like->delete(); // 取消點讚
            $isLiked = false;
        } else {
            $message->likes()->create(['user_id' => $userId]); //點讚
            $isLiked = true;
        }

        // 關鍵：清除快取
        Cache::forget('global_messages_feed');

         return response()->json([
         'success' => true, 
         'liked' => $isLiked,
         'likes_count' => $message->likes()->count()
        ]);    
    }
}

