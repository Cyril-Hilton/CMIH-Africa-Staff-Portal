<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventPublication
{
    public static function allowed(User $user): bool
    {
        return $user->isActive() && ($user->isCvoOrSuperAdmin() || (! in_array($user->access_role, [...User::MERCHANDISER_FIELD_ROLES, User::BRAND_PROMOTER_ROLE], true) && in_array(User::normalizeDepartmentKey($user->department), ['client_relations', 'operations_projects'], true)));
    }

    public static function normalise(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii($name))));
    }

    public function save(array $data, User $actor, ?UploadedFile $image = null, ?Event $existing = null, ?int $revision = null): Event
    {
        $fingerprint = $image ? app(FlyerFingerprint::class)->scan($image->getRealPath()) : null;
        $filename = $image ? basename(str_replace('\\', '/', $image->getClientOriginalName())) : null;
        $filenameKey = $filename ? self::normalise(pathinfo($filename, PATHINFO_FILENAME)) : null;
        $key = hash('sha256', self::normalise($data['title']).'|'.\Illuminate\Support\Carbon::parse($data['starts_at'])->toDateString());
        $written = null;
        try {
            return DB::transaction(function () use ($data, $actor, $image, $existing, $revision, $fingerprint, $filename, $filenameKey, $key, &$written) {
                DB::table('event_publication_locks')->where('id', 1)->increment('version');
                $event = $existing ? Event::whereKey($existing->id)->lockForUpdate()->firstOrFail() : new Event;
                abort_if($existing && $revision !== null && (int) $event->revision !== $revision, 409, 'This event changed. Refresh before saving.');
                $others = Event::when($existing, fn ($q) => $q->whereKeyNot($existing->id))->get();
                foreach ($others as $other) {
                    $reason = null;
                    $sameDay = $other->starts_at->toDateString() === \Illuminate\Support\Carbon::parse($data['starts_at'])->toDateString();
                    $name = self::normalise($data['title']);
                    $otherName = self::normalise($other->title);
                    if ($sameDay && $name === $otherName) {
                        $reason = 'The event name and date already exist';
                    } elseif ($sameDay && min(strlen($name), strlen($otherName)) >= 10 && levenshtein(substr($name, 0, 255), substr($otherName, 0, 255)) / max(strlen($name), strlen($otherName)) <= .08) {
                        $reason = 'A very similar event name already exists for this date';
                    }
                    if ($fingerprint && $other->flyer_sha256 === $fingerprint['sha256']) {
                        $reason = 'This exact flyer was already uploaded';
                    } elseif ($fingerprint && $other->flyer_fingerprint && app(FlyerFingerprint::class)->similar($fingerprint, $other->flyer_fingerprint)) {
                        $reason = 'This flyer looks like one already uploaded';
                    } elseif ($sameDay && $filenameKey && strlen($filenameKey) >= 10 && ! preg_match('/^(image|img|flyer|poster|whatsapp|screenshot|photo|untitled|download)\b/', $filenameKey) && $filenameKey === $other->filename_key) {
                        $reason = 'This flyer filename is already used for this event date';
                    }
                    if ($reason) {
                        throw ValidationException::withMessages(['image' => $reason.': “'.$other->title.'” ('.$other->starts_at->format('d M Y').'). Open the existing event instead of uploading it again.']);
                    }
                }
                $before = $event->exists ? $event->getAttributes() : [];
                if ($image) {
                    $written = 'event-flyers/'.Str::uuid().'.webp';
                    if (! Storage::disk('local')->put($written, app(FlyerFingerprint::class)->optimised($image->getRealPath()))) {
                        throw new \RuntimeException('Unable to save flyer.');
                    }
                    $event->fill(['image_path' => $written, 'image_disk' => 'local', 'flyer_sha256' => $fingerprint['sha256'], 'flyer_fingerprint' => $fingerprint, 'original_filename' => $filename, 'filename_key' => $filenameKey]);
                }
                $event->fill(collect($data)->only(['title', 'summary', 'location', 'starts_at', 'ends_at', 'registration_url', 'status'])->all());
                $event->event_key = $key;
                $event->updated_by = $actor->id;
                if (! $event->exists) {
                    $event->created_by = $actor->id;
                } else {
                    $event->revision++;
                }
                $event->save();
                \App\Services\PerformanceWorkflow::record('public_event', $event->id, $actor->id, $before ? 'update' : 'create', $before, $event->getAttributes(), 'Collaborative event publication');

                return $event;
            }, 3);
        } catch (\Throwable $e) {
            if ($written) {
                Storage::disk('local')->delete($written);
            }throw $e;
        }
    }
}
