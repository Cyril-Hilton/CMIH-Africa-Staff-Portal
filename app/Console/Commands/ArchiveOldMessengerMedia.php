<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Services\DropboxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveOldMessengerMedia extends Command
{
    protected $signature = 'messenger:archive-old-media
        {--days=60 : Archive media older than this many days}
        {--limit=250 : Maximum messages to process in one run}
        {--dry-run : Show what would be archived without uploading}
        {--delete-local : Delete the local attachment after Dropbox upload succeeds}';

    protected $description = 'Compress and archive old messenger media attachments to Dropbox.';

    public function handle(DropboxService $dropbox): int
    {
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');
        $deleteLocal = (bool) $this->option('delete-local');
        $processed = 0;
        $archived = 0;

        $query = Message::query()
            ->whereNotNull('attachment_path')
            ->whereNull('dropbox_archived_at')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id');

        $query->chunkById(50, function ($messages) use (&$processed, &$archived, $limit, $dryRun, $deleteLocal, $dropbox) {
            foreach ($messages as $message) {
                if ($processed >= $limit) {
                    return false;
                }

                $processed++;
                $archive = $this->prepareArchiveContent($message);

                if (! $archive) {
                    $this->warn("Skipped message {$message->id}: local attachment missing or unreadable.");
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would archive message {$message->id} to {$archive['dropbox_path']}.");
                    continue;
                }

                $sharedUrl = $dropbox->uploadFile($archive['dropbox_path'], $archive['content']);

                if (! $sharedUrl) {
                    $this->error("Dropbox upload failed for message {$message->id}.");
                    continue;
                }

                $originalPath = $message->original_attachment_path ?: $message->attachment_path;
                $message->forceFill([
                    'original_attachment_path' => $originalPath,
                    'dropbox_shared_url' => $sharedUrl,
                    'dropbox_archived_at' => now(),
                ])->save();

                if ($deleteLocal && $message->attachment_path) {
                    Storage::disk('local')->delete($message->attachment_path);
                    Storage::disk('public')->delete($message->attachment_path);
                }

                $archived++;
                $this->info("Archived message {$message->id}.");
            }

            return $processed < $limit;
        });

        $this->info("Messenger media archive complete. Processed {$processed}, archived {$archived}.");

        return self::SUCCESS;
    }

    private function prepareArchiveContent(Message $message): ?array
    {
        if (! $message->attachment_path) {
            return null;
        }

        $disk = Storage::disk('local')->exists($message->attachment_path) ? 'local' : 'public';
        if (! Storage::disk($disk)->exists($message->attachment_path)) {
            return null;
        }

        $localPath = Storage::disk($disk)->path($message->attachment_path);
        $content = null;
        $extension = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'bin';

        if ($message->isImage()) {
            $compressed = $this->compressImage($localPath);
            if ($compressed) {
                $content = $compressed;
                $extension = 'jpg';
            }
        }

        if ($content === null) {
            $content = @file_get_contents($localPath);
        }

        if ($content === false || $content === null) {
            Log::warning('Messenger archive could not read local attachment.', [
                'message_id' => $message->id,
                'path' => $message->attachment_path,
            ]);

            return null;
        }

        $safeName = Str::of(pathinfo($localPath, PATHINFO_FILENAME))
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->limit(80, '')
            ->toString();

        if ($safeName === '') {
            $safeName = 'attachment';
        }

        return [
            'content' => $content,
            'dropbox_path' => sprintf(
                'CMIH Messenger Archives/%s/message-%d-%s.%s',
                now()->format('Y/m'),
                $message->id,
                $safeName,
                $extension
            ),
        ];
    }

    private function compressImage(string $localPath): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($localPath);
        if (! $info || empty($info['mime'])) {
            return null;
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($localPath),
            'image/png' => @imagecreatefrompng($localPath),
            'image/gif' => @imagecreatefromgif($localPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($localPath) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        $width = (int) imagesx($source);
        $height = (int) imagesy($source);
        $maxSide = 1600;
        $scale = min(1, $maxSide / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 78);
        $compressed = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $compressed ?: null;
    }
}
