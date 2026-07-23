<?php

namespace Qwe987299\AutoBanSpam\Service;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Http\AccessToken;
use Flarum\Notification\Notification;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AutoBanService
{
    protected SettingsRepositoryInterface $settings;
    protected Translator $translator;

    public function __construct(SettingsRepositoryInterface $settings, Translator $translator)
    {
        $this->settings = $settings;
        $this->translator = $translator;
    }

    /**
     * Get configured keywords as array
     */
    public function getKeywords(): array
    {
        $raw = $this->settings->get('auto_ban_spam.keywords', '');
        if (empty($raw)) {
            return [];
        }

        // Split by newlines or commas
        $lines = preg_split('/[\r\n,]+/', $raw);
        $keywords = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                $keywords[] = mb_strtolower($trimmed);
            }
        }

        return array_unique($keywords);
    }

    /**
     * Check if given string contains any auto-ban keywords
     */
    public function detectKeyword(?string $content): ?string
    {
        if (empty($content)) {
            return null;
        }

        $keywords = $this->getKeywords();
        if (empty($keywords)) {
            return null;
        }

        $lowerContent = mb_strtolower($content);

        foreach ($keywords as $keyword) {
            if (mb_strpos($lowerContent, $keyword) !== false) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Ban user indefinitely, revoke tokens, change nickname to "Banned", physically delete avatar file, wipe profile and clean content/relations
     */
    public function banAndCleanUser(User $user, ?User $actor = null): void
    {
        if (!$user->exists || ($user->suspended_until !== null && $user->suspended_until->isFuture())) {
            return;
        }

        // 1. Suspend user until 2099-12-31
        $user->suspended_until = Carbon::create(2099, 12, 31, 23, 59, 59);

        // 2. Change Nickname to "Banned"
        $user->nickname = 'Banned';

        // 3. Physically delete avatar file from server storage disk and clear profile fields
        $avatarPath = $user->getRawOriginal('avatar_url') ?? $user->avatar_url;
        if (!empty($avatarPath)) {
            try {
                /** @var FilesystemFactory $filesystemFactory */
                $filesystemFactory = resolve(FilesystemFactory::class);
                $disk = $filesystemFactory->disk('flarum-avatars');
                if ($disk->exists($avatarPath)) {
                    $disk->delete($avatarPath);
                }
            } catch (Throwable $e) {
                // Proceed if avatar disk file deletion encounters exception
            }
        }
        $user->avatar_url = null;

        if (isset($user->bio)) {
            $user->bio = '';
        }
        if (isset($user->social_buttons) || array_key_exists('social_buttons', $user->getAttributes())) {
            $user->social_buttons = null;
        }

        $user->save();

        // 4. Instantly revoke all active access tokens & log out user from all devices
        try {
            if (class_exists(AccessToken::class)) {
                AccessToken::where('user_id', $user->id)->delete();
            }
        } catch (Throwable $e) {
            // Ignore if tokens table is inaccessible
        }

        // Collect all discussion IDs affected by this user's posts before deleting/hiding
        $affectedDiscussionIds = Post::where('user_id', $user->id)
            ->pluck('discussion_id')
            ->filter()
            ->unique()
            ->toArray();

        // 5. Handle posts, discussions, uploaded files & pivot data deletion strategy
        $actionType = $this->settings->get('auto_ban_spam.action_type', 'soft');

        if ($actionType === 'hard') {
            // Hard delete: Clean notifications sent to/from banned user
            try {
                if (class_exists(Notification::class)) {
                    Notification::where('user_id', $user->id)
                        ->orWhere('from_user_id', $user->id)
                        ->delete();
                }
            } catch (Throwable $e) {}

            // Hard delete: Clean discussion read states & subscriptions
            try {
                if (Schema::hasTable('discussion_user')) {
                    DB::table('discussion_user')->where('user_id', $user->id)->delete();
                }
            } catch (Throwable $e) {}

            // Hard delete: Clean drafts (fof/drafts)
            try {
                if (Schema::hasTable('drafts')) {
                    DB::table('drafts')->where('user_id', $user->id)->delete();
                }
            } catch (Throwable $e) {}

            // Hard delete: Clean likes & votes (flarum/likes, fof/gamification)
            try {
                if (Schema::hasTable('post_likes')) {
                    DB::table('post_likes')->where('user_id', $user->id)->delete();
                }
                if (Schema::hasTable('post_votes')) {
                    DB::table('post_votes')->where('user_id', $user->id)->delete();
                }
            } catch (Throwable $e) {}

            // Hard delete: Permanently remove user's own discussions & posts
            Discussion::where('user_id', $user->id)->delete();
            Post::where('user_id', $user->id)->delete();

            // Hard delete: Delete all FoF Upload files if extension is active
            if (class_exists(\FoF\Upload\File::class)) {
                try {
                    /** @var \FoF\Upload\Adapters\Manager $manager */
                    $manager = resolve(\FoF\Upload\Adapters\Manager::class);

                    \FoF\Upload\File::where('actor_id', $user->id)->each(function (\FoF\Upload\File $file) use ($manager) {
                        try {
                            $adapter = $manager->instantiate($file->upload_method);
                            if ($adapter) {
                                $adapter->delete($file);
                            }
                        } catch (Throwable $e) {
                            // Proceed to delete DB record even if adapter deletion fails
                        }
                        $file->delete();
                    });
                } catch (Throwable $e) {
                    \FoF\Upload\File::where('actor_id', $user->id)->delete();
                }
            }

            // Refresh last post & stats for remaining affected discussions created by other users
            if (!empty($affectedDiscussionIds)) {
                Discussion::whereIn('id', $affectedDiscussionIds)->get()->each(function (Discussion $discussion) {
                    if ($discussion->posts()->count() === 0) {
                        $discussion->delete();
                    } else {
                        $discussion->refreshCommentCount();
                        $discussion->refreshParticipantCount();
                        $discussion->refreshLastPost();
                        $discussion->save();
                    }
                });
            }
        } else {
            // Soft delete: Hide / Send to approval queue, keeping original content intact
            Post::where('user_id', $user->id)->chunk(50, function ($posts) use ($actor, $user) {
                foreach ($posts as $post) {
                    $post->hidden_at = Carbon::now();
                    $post->hidden_user_id = $actor ? $actor->id : $user->id;
                    if (isset($post->is_approved)) {
                        $post->is_approved = false;
                    }
                    $post->save();
                }
            });

            Discussion::where('user_id', $user->id)->chunk(50, function ($discussions) use ($actor, $user) {
                foreach ($discussions as $discussion) {
                    $discussion->hidden_at = Carbon::now();
                    $discussion->hidden_user_id = $actor ? $actor->id : $user->id;
                    if (isset($discussion->is_approved)) {
                        $discussion->is_approved = false;
                    }
                    $discussion->save();
                }
            });

            // Refresh last post & stats for all affected discussions so list updates immediately
            if (!empty($affectedDiscussionIds)) {
                Discussion::whereIn('id', $affectedDiscussionIds)->get()->each(function (Discussion $discussion) {
                    $discussion->refreshCommentCount();
                    $discussion->refreshParticipantCount();
                    $discussion->refreshLastPost();
                    $discussion->save();
                });
            }
        }

        // 6. Refresh banned user's own discussion & post counts (resets profile /u/ counts)
        $user->refreshDiscussionCount();
        $user->refreshCommentCount();
        $user->save();
    }

    /**
     * Check fields and trigger auto-ban if keyword is found
     */
    public function checkAndTrigger(User $user, array $textsToCheck, ?User $actor = null): void
    {
        // Don't ban admins or users in exempt groups (with autoBanSpam.bypass permission)
        if ($user->isAdmin() || $user->hasPermission('autoBanSpam.bypass')) {
            return;
        }

        foreach ($textsToCheck as $text) {
            $matched = $this->detectKeyword($text);
            if ($matched !== null) {
                $this->banAndCleanUser($user, $actor);

                throw new ValidationException([
                    'auto_ban' => $this->translator->trans('qwe987299-auto-ban-spam.forum.banned_notice', [
                        '{keyword}' => $matched
                    ])
                ]);
            }
        }
    }
}
