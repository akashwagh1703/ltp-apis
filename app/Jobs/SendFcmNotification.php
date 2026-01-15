<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    protected $userId;
    protected $userType;
    protected $title;
    protected $body;
    protected $data;
    protected $type;

    public function __construct($userId, $userType, $title, $body, $data = [], $type = 'general')
    {
        $this->userId = $userId;
        $this->userType = $userType;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->type = $type;
    }

    public function handle(FcmService $fcmService)
    {
        $fcmService->sendToUser(
            $this->userId,
            $this->userType,
            $this->title,
            $this->body,
            $this->data,
            $this->type
        );
    }

    public function failed(\Throwable $exception)
    {
        Log::error('FCM notification job failed', [
            'user_id' => $this->userId,
            'user_type' => $this->userType,
            'error' => $exception->getMessage()
        ]);
    }
}
