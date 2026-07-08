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
        // ✅ 修正後的寫法：優先讀取環境變數，若無則自動在 Linux 尋找全域指令
        $ffmpeg = \FFMpeg\FFMpeg::create([
            'ffmpeg.binaries'  => PHP_OS_FAMILY === 'Windows' ? 'D:/ffmpeg-8.1.1-full_build/ffmpeg-8.1.1-full_build/bin/ffmpeg.exe' : '/usr/bin/ffmpeg',
            'ffprobe.binaries' => PHP_OS_FAMILY === 'Windows' ? 'D:/ffmpeg-8.1.1-full_build/ffmpeg-8.1.1-full_build/bin/ffprobe.exe' : '/usr/bin/ffprobe',
            'timeout'          => 3600,
            'ffmpeg.threads'   => 12,
        ]);

        $originalPath = $this->message->video_path; // 例如: videos/xxx.mp4
        $filename = time() . '_compressed.mp4';
        $s3Key = 'videos/' . $filename; // 未來在 S3 上的路徑

        // 原始檔案的絕對路徑 (Ubuntu 本地端)
        $fullPath = storage_path('app/public/' . $originalPath);
        if (!file_exists($fullPath)) {
        \Log::error("影片壓縮失敗：找不到原始檔案 - " . $fullPath);
        return;
        }

        // 本地壓縮暫存路徑
        $localCompressedPath = storage_path('app/public/videos/local_' . $filename);

        // 2. 設定壓縮格式
        $video = $ffmpeg->open($fullPath);

        // 🚀 修正 1：改用 X264() 空建構子，手動指名 'aac'，避開 libmp3lame 崩潰
        $format = new X264();
        $format->setAudioCodec('aac');
        $format->setVideoCodec('libx264');
        $format->setKiloBitrate(1000);
        $format->setAudioKiloBitrate(128);
        $format->setAdditionalParameters([
            '-crf', '28',
            '-preset', 'fast',
            '-movflags', '+faststart',
        ]);
        // 先在 Ubuntu 本地端壓好
        $video->save($format, $localCompressedPath);

        // 🚀 修正 2：關鍵！將壓好的影片檔案「上傳至 AWS S3」
        if (file_exists($localCompressedPath)) {
            $fileContents = file_get_contents($localCompressedPath);
            
            // 丟上 S3 磁碟
            // ✅ 修正後的新寫法（上傳同時強制蓋上「公開」印章）
            Storage::disk('s3')->put($s3Key, $fileContents, [
                'visibility' => 'public',
                'ACL' => 'public-read',
            ]);

            // 3. 更新資料庫中的影片網址為 S3 的路徑
            $this->message->update([
                'video_path' => $s3Key,
                'status'     => 'ready',
            ]);

            // 廣播通知前端影片已就緒
            broadcast(new \App\Events\MessageStatusUpdated($this->message));

            // ✅ 加這行，清除快取讓前端拿到新資料
            for ($i = 1; $i <= 10; $i++) {
                \Cache::forget("messages_feed_page_{$i}");
            }

            // 🚀 修正 3：擦乾淨屁股，刪除 Ubuntu 本地硬碟的「原始片」與「壓縮片」
            Storage::disk('public')->delete($originalPath); // 刪除原始檔
            if (file_exists($localCompressedPath)) {
                unlink($localCompressedPath); // 刪除本地壓縮暫存檔
            }
        } else {
            throw new \Exception("本地壓縮檔案生成失敗，無法上傳 S3");
        }
    } catch (\Exception $e) {
        \Log::error('影片壓縮或上傳 S3 失敗：' . $e->getMessage());

        // 2. 更新資料庫狀態為失敗
        $this->message->update(['status' => 'failed']);
        
        // 3. 🔥 立刻廣播給所有人，讓大家的轉圈圈瞬間變成「⚠️ 影片轉檔失敗，請重新上傳」
        broadcast(new \App\Events\MessageStatusUpdated($this->message));

        throw $e; // 讓 queue 記錄為失敗，方便開 Tinker 查 
    }

    }
}    


