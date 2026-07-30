<?php

namespace Qwe987299\AutoBanSpam;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\Event\Saving as UserSaving;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\User\User;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Routes('api'))
        ->post('/qwe987299-auto-ban-spam/ban/{id}', 'qwe987299-auto-ban-spam.manual-ban', Api\Controller\ManualBanController::class),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attribute('canAutoBanSpamManualBan', function (UserSerializer $serializer, User $user) {
            $actor = $serializer->getActor();
            return $actor->hasPermission('autoBanSpam.manualBan');
        }),

    (new Extend\Settings())
        ->serializeToForum('autoBanKeywords', 'auto_ban_spam.keywords')
        ->serializeToForum('autoBanActionType', 'auto_ban_spam.action_type')
        ->serializeToForum('autoBanOnlyRecentDays', 'auto_ban_spam.only_recent_days'),

    (new Extend\Event())
        ->listen(UserSaving::class, Listener\CheckUserSaving::class)
        ->listen(PostSaving::class, Listener\CheckPostSaving::class)
        ->listen(DiscussionSaving::class, Listener\CheckDiscussionSaving::class),
];
