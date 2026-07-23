<?php

namespace Qwe987299\AutoBanSpam\Listener;

use Flarum\Discussion\Event\Saving;
use Illuminate\Support\Arr;
use Qwe987299\AutoBanSpam\Service\AutoBanService;

class CheckDiscussionSaving
{
    protected AutoBanService $autoBanService;

    public function __construct(AutoBanService $autoBanService)
    {
        $this->autoBanService = $autoBanService;
    }

    public function handle(Saving $event): void
    {
        $discussion = $event->discussion;
        $actor = $event->actor;
        $data = $event->data;

        $title = Arr::get($data, 'attributes.title') ?? $discussion->title;
        if (empty($title)) {
            return;
        }

        $user = $discussion->user ?? $actor;
        if (!$user) {
            return;
        }

        $this->autoBanService->checkAndTrigger($user, [$title], $actor);
    }
}
