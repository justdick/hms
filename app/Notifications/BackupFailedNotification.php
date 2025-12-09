<?php

namespace App\Notifications;

use App\Models\Backup;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $type  The type of failure (backup, restore, scheduled)
     * @param  Backup|null  $backup  The backup associated with the failure
     * @param  string  $error  The error message
     * @param  Carbon  $timestamp  The timestamp of the failure
     */
    public function __construct(
        public string $type,
        public ?Backup $backup,
        public string $error,
        public Carbon $timestamp
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->getSubject();
        $message = (new MailMessage)
            ->subject($subject)
            ->error()
            ->greeting('HMS Backup Alert')
            ->line($this->getIntroLine());

        // Add backup details if available
        if ($this->backup) {
            $message->line('**Backup Details:**')
                ->line('- Filename: '.($this->backup->filename ?? 'N/A'))
                ->line('- Source: '.($this->backup->source ?? 'N/A'))
                ->line('- Status: '.($this->backup->status ?? 'N/A'));
        }

        $message->line('**Error Details:**')
            ->line($this->error)
            ->line('**Timestamp:** '.$this->timestamp->format('Y-m-d H:i:s'))
            ->line('Please investigate this issue as soon as possible to ensure data protection.');

        if ($this->type === 'scheduled') {
            $message->line('⚠️ **Warning:** The scheduled backup system may be at risk. Manual intervention may be required.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'backup_id' => $this->backup?->id,
            'backup_filename' => $this->backup?->filename,
            'backup_source' => $this->backup?->source,
            'error' => $this->error,
            'timestamp' => $this->timestamp->toIso8601String(),
        ];
    }

    /**
     * Get the email subject based on failure type.
     */
    protected function getSubject(): string
    {
        return match ($this->type) {
            'backup' => '[HMS] Backup Operation Failed',
            'restore' => '[HMS] Database Restore Failed',
            'scheduled' => '[HMS] Scheduled Backup Failed - Action Required',
            default => '[HMS] Backup System Alert',
        };
    }

    /**
     * Get the introduction line based on failure type.
     */
    protected function getIntroLine(): string
    {
        return match ($this->type) {
            'backup' => 'A database backup operation has failed.',
            'restore' => 'A database restore operation has failed.',
            'scheduled' => 'A scheduled backup has failed after all retry attempts.',
            default => 'A backup system operation has failed.',
        };
    }
}
