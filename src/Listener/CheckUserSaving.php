<?php

namespace Qwe987299\AutoBanSpam\Listener;

use Flarum\User\Event\Saving;
use Illuminate\Support\Arr;
use Qwe987299\AutoBanSpam\Service\AutoBanService;

class CheckUserSaving
{
    protected AutoBanService $autoBanService;

    public function __construct(AutoBanService $autoBanService)
    {
        $this->autoBanService = $autoBanService;
    }

    public function handle(Saving $event): void
    {
        $user = $event->user;
        $actor = $event->actor;
        $data = $event->data;

        $textsToCheck = [];

        // Check Username & Nickname
        if (!empty($user->username)) {
            $textsToCheck[] = $user->username;
        }

        $nickname = Arr::get($data, 'attributes.nickname') ?? $user->nickname;
        if (!empty($nickname)) {
            $textsToCheck[] = $nickname;
        }

        // Check Bio
        $bio = Arr::get($data, 'attributes.bio') ?? (isset($user->bio) ? $user->bio : null);
        if (!empty($bio)) {
            $textsToCheck[] = $bio;
        }

        $this->autoBanService->checkAndTrigger($user, $textsToCheck, $actor);
    }
}
