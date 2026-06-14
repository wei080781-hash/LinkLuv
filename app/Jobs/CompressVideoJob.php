<?php 
// 這個是處理影片任務的 Job，當使用者上傳影片後，會將影片路徑存到資料庫，然後 dispatch 這個 Job 來壓縮影片，壓縮完成後更新資料庫中的影片路徑，並刪除原始影片。
namespace App\Jobs;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
class CompressVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message;
     
    public function __construct(Message $message)
    {
        $this->message = $message;
    }
    
     public function handle()
{
    try {
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => 'D:/ffmpeg-8.1.1-full_build/ffmpeg-8.1.1-full_build/bin/ffmpeg.exe',
            'ffprobe.binaries' => 'D:/ffmpeg-8.1.1-full_build/ffmpeg-8.1.1-full_build/bin/ffprobe.exe',
            'timeout'          => 3600,
        ]);

        $originalPath = $this->message->video_path;
        $compressedPath = 'videos/' . time() . '_compressed.mp4';

        $fullPath = storage_path('app/public/' . $originalPath);
        if (!file_exists($fullPath)) {
            \Log::error("影片壓縮失敗：找不到原始檔案 - " . $fullPath);
            return;
        }

        $video = $ffmpeg->open($fullPath);

        $format = new X264('libmp3lame', 'libx264'); // ✅ 修正這裡
        $format->setAdditionalParameters(['-crf', '28']);

        $video->save($format, storage_path('app/public/' . $compressedPath));

        $this->message->update(['video_path' => $compressedPath]);
        Storage::disk('public')->delete($originalPath);

    } catch (\Exception $e) {
        \Log::error('影片壓縮失敗：' . $e->getMessage());
        throw $e; // 讓 queue 記錄為失敗
    }
}
}
