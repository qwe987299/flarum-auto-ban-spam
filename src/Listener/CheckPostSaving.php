<?php

namespace Qwe987299\AutoBanSpam\Listener;

use Flarum\Post\Event\Saving;
use Illuminate\Support\Arr;
use Qwe987299\AutoBanSpam\Service\AutoBanService;

class CheckPostSaving
{
    protected AutoBanService $autoBanService;

    public function __construct(AutoBanService $autoBanService)
    {
        $this->autoBanService = $autoBanService;
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;
        $actor = $event->actor;
        $data = $event->data;

        $content = Arr::get($data, 'attributes.content') ?? $post->content;
        if (empty($content)) {
            return;
        }

        $user = $post->user ?? $actor;
        if (!$user) {
            return;
        }

        $this->autoBanService->checkAndTrigger($user, [$content], $actor);
    }
}
