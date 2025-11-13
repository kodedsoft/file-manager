<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public function __construct(public int $uploadId, public int $processed, public int $total, public int $success = 0, public int $failure = 0, public string $status = 'processing')
    {}

    public function broadcastOn(): PrivateChannel
    {
        // private channel per upload to authorize only the owner
        return new PrivateChannel("upload.{$this->uploadId}");
    }

    public function broadcastWith(): array
    {
        return [
            'uploadId' => $this->uploadId,
            'processed' => $this->processed,
            'total' => $this->total,
            'success' => $this->success,
            'failure' => $this->failure,
            'status' => $this->status,
        ];
    }
}


