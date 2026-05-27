<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;


class MessageController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        return Message::with(['user'])
            ->withCount('likes')
            ->withExists(['likes as is_liked' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('path', 'ASC')
            ->get();
    }
    // 處理留言的儲存與列表獲取，並處理階層深度邏輯。
    public function store(Request $request)
    {
        \Log::info('Files', $request->allFiles());
        \Log::info('Input', $request->except(['+']));
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:messages,id',
            'media' => 'nullable|mimes:jpg,jpeg,png,gif,mp4,mov,ogg|  max:20480', // 把圖片和影片的格式全部寫在一起
        ]);



        $parentId = $request->input('parent_id');
        // 判斷圖片還是影片，並儲存路徑
         $imagePath = null;
         $videoPath = null;
         $mediaType = null;

        if ($request->hasFile('media')) {
        $file = $request->file('media');
        $mime = $file->getMimeType(); 
        // 取得檔案的真正類型 (例如 image/jpeg 或 video/mp4)

        if (str_contains($mime, 'image')) {
            // 直接使用 storeAs，不需要 import Storage
            // 參數：(資料夾, 隨機檔名, 磁碟名稱)
            $imagePath = $file->storeAs('images', $file->hashName(), 'public');
            $mediaType = 'image';
        } elseif (str_contains($mime, 'video')) {
            // 同理，直接使用 storeAs
            $videoPath = $file->storeAs('videos', $file->hashName(), 'public');
            $mediaType = 'video';
        }
    }
        
        // 1.初始化深度與路徑
        $depth = 0;
        $path = '';
        $threadId1 = null;

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
            'content'   => $request->content,
            'parent_id' => $parentId,
            'image_path' => $imagePath,
            'video_path' => $videoPath,
            'media_type' => $mediaType,
            'depth'      => $depth, // 直接建立時寫入
        ]);

        // 3. 更新物化路徑 (Materialized Path)
        $paddedId = str_pad($message->id, 10, '0', STR_PAD_LEFT); // 固定 10 位長度
        $path = $parentId ? (Message::findOrFail($parentId)->path . '.' . $paddedId) : $paddedId;
        $threadId = $parentId ? Message::findOrFail($parentId)->thread_id : $message->id;
        
        // 4. 更新路徑與正確的 thread_id
        $message->update([
            'path' => $path,
            'depth' => $depth,
            'thread_id' => $threadId
        ]);

    return response()->json(['success' => true]);
}

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
         return response()->json([
         'success' => true, 
         'liked' => $isLiked,
         'likes_count' => $message->likes()->count()
        ]);    
    }
}
