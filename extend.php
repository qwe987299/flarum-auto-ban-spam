<?php

namespace Qwe987299\AutoBanSpam;

use Flarum\Extend;
use Flarum\User\Event\Saving as UserSaving;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Discussion\Event\Saving as DiscussionSaving;

$extenders = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Routes('api'))
        ->post('/qwe987299-auto-ban-spam/ban/{id}', 'qwe987299-auto-ban-spam.manual-ban', Api\Controller\ManualBanController::class),

    (new Extend\Settings())
        ->serializeToForum('autoBanKeywords', 'auto_ban_spam.keywords')
        ->serializeToForum('autoBanActionType', 'auto_ban_spam.action_type')
        ->serializeToForum('autoBanOnlyRecentDays', 'auto_ban_spam.only_recent_days'),

    (new Extend\Event())
        ->listen(UserSaving::class, Listener\CheckUserSaving::class)
        ->listen(PostSaving::class, Listener\CheckPostSaving::class)
        ->listen(DiscussionSaving::class, Listener\CheckDiscussionSaving::class),
];

// Flarum 1.x: Extend Tobscure/JsonApi UserSerializer
if (class_exists('Flarum\Extend\ApiSerializer')) {
    $extenders[] = (new \Flarum\Extend\ApiSerializer('Flarum\Api\Serializer\UserSerializer'))
        ->attribute('canAutoBanSpamManualBan', function ($serializer, $user) {
            return $serializer->getActor()->hasPermission('autoBanSpam.manualBan');
        });
}

// Flarum 2.0: Extend JSON:API UserResource Schema fields
if (class_exists('Flarum\Extend\ApiResource') && class_exists('Flarum\Api\Resource\UserResource') && class_exists('Flarum\Api\Schema\Boolean')) {
    $extenders[] = (new \Flarum\Extend\ApiResource(\Flarum\Api\Resource\UserResource::class))
        ->fields(fn () => [
            \Flarum\Api\Schema\Boolean::make('canAutoBanSpamManualBan')
                ->get(fn ($user, $context) => $context->getActor()->hasPermission('autoBanSpam.manualBan')),
        ]);
}

return $extenders;

