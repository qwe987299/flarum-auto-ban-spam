<?php

namespace Qwe987299\AutoBanSpam\Service;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\Paths;
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
    protected ?FilesystemFactory $filesystemFactory;
    protected ?Paths $paths;

    public function __construct(
        SettingsRepositoryInterface $settings,
        Translator $translator,
        ?FilesystemFactory $filesystemFactory = null,
        ?Paths $paths = null
    ) {
        $this->settings = $settings;
        $this->translator = $translator;
        $this->filesystemFactory = $filesystemFactory;
        $this->paths = $paths;
    }

    /**
     * Get FilesystemFactory instance safely across Flarum 1.x and 2.0
     */
    protected function getFilesystemFactory(): ?FilesystemFactory
    {
        if ($this->filesystemFactory !== null) {
            return $this->filesystemFactory;
        }

        if (function_exists('resolve')) {
            try {
                return resolve(FilesystemFactory::class);
            } catch (Throwable $e) {}
        }

        if (class_exists(\Illuminate\Container\Container::class)) {
            try {
                return \Illuminate\Container\Container::getInstance()->make(FilesystemFactory::class);
            } catch (Throwable $e) {}
        }

        return null;
    }

    /**
     * Get Paths instance safely across Flarum 1.x and 2.0
     */
    protected function getPaths(): ?Paths
    {
        if ($this->paths !== null) {
            return $this->paths;
        }

        if (function_exists('resolve') && class_exists(Paths::class)) {
            try {
                return resolve(Paths::class);
            } catch (Throwable $e) {}
        }

        if (class_exists(\Illuminate\Container\Container::class) && class_exists(Paths::class)) {
            try {
                return \Illuminate\Container\Container::getInstance()->make(Paths::class);
            } catch (Throwable $e) {}
        }

        return null;
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

        // Split by newlines, half-width commas (,) or full-width commas (，)
        $lines = preg_split('/[\r\n,，]+/u', $raw);
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
     * Ban user indefinitely, revoke tokens, change nickname to "Banned", physically delete avatar and profile cover files, wipe profile and clean content/relations
     */
    public function banAndCleanUser(User $user, ?User $actor = null): void
    {
        if (!$user->exists) {
            return;
        }

        // Return early only if already fully auto-banned and no explicit actor action is requesting cleanup
        if ($actor === null && $user->nickname === 'Banned' && $user->suspended_until !== null && $user->suspended_until->year >= 2099) {
            return;
        }

        // 1. Suspend user until 2099-12-31
        $user->suspended_until = Carbon::create(2099, 12, 31, 23, 59, 59);

        // 2. Change Nickname to "Banned"
        $user->nickname = 'Banned';

        // 3. Physically delete avatar file and profile cover from server storage disk and clear profile fields
        $this->deleteUserAvatar($user);
        $this->deleteUserCover($user);

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

            // Hard delete: Delete all FoF Upload files including thumbnails across Flarum 1.x and 2.0
            $this->deleteUserUploadFiles($user->id);

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
     * Physically delete user avatar, including Flarum 2.0 HiDPI variants (@2x, @3x)
     */
    protected function deleteUserAvatar(User $user): void
    {
        $avatarPath = $user->getRawOriginal('avatar_url') ?? $user->avatar_url;

        if (!empty($avatarPath)) {
            // If accessor returned a full URL, extract the relative base filename
            if (str_contains($avatarPath, '://')) {
                $parsed = parse_url($avatarPath, PHP_URL_PATH);
                if ($parsed) {
                    $avatarPath = basename($parsed);
                }
            }

            // 1. Flarum 2.0 AvatarUploader::deleteAllVariants
            if (class_exists(\Flarum\User\AvatarUploader::class)) {
                try {
                    $uploader = function_exists('resolve')
                        ? resolve(\Flarum\User\AvatarUploader::class)
                        : \Illuminate\Container\Container::getInstance()->make(\Flarum\User\AvatarUploader::class);

                    if (method_exists($uploader, 'deleteAllVariants')) {
                        $uploader->deleteAllVariants($avatarPath);
                    }
                } catch (Throwable $e) {}
            }

            // 2. Direct filesystem disk deletion for base avatar and 2.0 HiDPI variants (@2x, @3x)
            try {
                $filesystemFactory = $this->getFilesystemFactory();
                if ($filesystemFactory) {
                    $disk = $filesystemFactory->disk('flarum-avatars');

                    if ($disk->exists($avatarPath)) {
                        $disk->delete($avatarPath);
                    }

                    // Delete Flarum 2.0 variant paths (e.g. abc@2x.webp, abc@3x.webp)
                    $dot = strrpos($avatarPath, '.');
                    $base = $dot !== false ? substr($avatarPath, 0, $dot) : $avatarPath;
                    $ext = $dot !== false ? substr($avatarPath, $dot) : '';

                    foreach (['@2x', '@3x'] as $suffix) {
                        $variant = $base . $suffix . $ext;
                        if ($disk->exists($variant)) {
                            $disk->delete($variant);
                        }
                    }
                }
            } catch (Throwable $e) {}

            // 3. Fallback direct physical disk deletion in public/assets/avatars
            try {
                $paths = $this->getPaths();
                $publicPath = $paths ? $paths->public : (defined('FLARUM_PUBLIC_DIR') ? FLARUM_PUBLIC_DIR : null);

                if ($publicPath) {
                    $avatarDir = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;

                    if (file_exists($avatarDir . $avatarPath)) {
                        @unlink($avatarDir . $avatarPath);
                    }

                    $dot = strrpos($avatarPath, '.');
                    $base = $dot !== false ? substr($avatarPath, 0, $dot) : $avatarPath;
                    $ext = $dot !== false ? substr($avatarPath, $dot) : '';

                    foreach (['@2x', '@3x'] as $suffix) {
                        $variantFile = $avatarDir . $base . $suffix . $ext;
                        if (file_exists($variantFile)) {
                            @unlink($variantFile);
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        $user->avatar_url = null;
        if (isset($user->has_avatar_2x) || array_key_exists('has_avatar_2x', $user->getAttributes())) {
            $user->has_avatar_2x = false;
        }
        if (isset($user->has_avatar_3x) || array_key_exists('has_avatar_3x', $user->getAttributes())) {
            $user->has_avatar_3x = false;
        }
    }

    /**
     * Physically delete sycho/flarum-profile-cover image & thumbnail
     */
    protected function deleteUserCover(User $user): void
    {
        $coverPath = isset($user->cover) ? ($user->getRawOriginal('cover') ?? $user->cover) : null;
        if (!empty($coverPath)) {
            if (str_contains($coverPath, '://')) {
                $parsed = parse_url($coverPath, PHP_URL_PATH);
                if ($parsed) {
                    $coverPath = basename($parsed);
                }
            }

            try {
                $filesystemFactory = $this->getFilesystemFactory();
                if ($filesystemFactory) {
                    $disk = $filesystemFactory->disk('sycho-profile-cover');
                    $thumbnailPath = 'thumbnails/' . $coverPath;

                    if ($disk->exists($coverPath)) {
                        $disk->delete($coverPath);
                    }
                    if ($disk->exists($thumbnailPath)) {
                        $disk->delete($thumbnailPath);
                    }
                }
            } catch (Throwable $e) {}

            try {
                $paths = $this->getPaths();
                $publicPath = $paths ? $paths->public : null;
                if ($publicPath) {
                    $coverDir = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'covers' . DIRECTORY_SEPARATOR;
                    if (file_exists($coverDir . $coverPath)) {
                        @unlink($coverDir . $coverPath);
                    }
                    if (file_exists($coverDir . 'thumbnails' . DIRECTORY_SEPARATOR . $coverPath)) {
                        @unlink($coverDir . 'thumbnails' . DIRECTORY_SEPARATOR . $coverPath);
                    }
                }
            } catch (Throwable $e) {}

            $user->cover = null;
        }
    }

    /**
     * Physically delete all FoF Upload files including thumbnails across Flarum 1.x and 2.0
     */
    protected function deleteUserUploadFiles(int $userId): void
    {
        if (!class_exists(\FoF\Upload\File::class)) {
            return;
        }

        try {
            $files = \FoF\Upload\File::where('actor_id', $userId)->get();

            /** @var \FoF\Upload\Repositories\FileRepository|null $fileRepo */
            $fileRepo = null;
            if (class_exists(\FoF\Upload\Repositories\FileRepository::class)) {
                try {
                    $fileRepo = function_exists('resolve')
                        ? resolve(\FoF\Upload\Repositories\FileRepository::class)
                        : \Illuminate\Container\Container::getInstance()->make(\FoF\Upload\Repositories\FileRepository::class);
                } catch (Throwable $e) {}
            }

            /** @var \FoF\Upload\Helpers\Util|null $util */
            $util = null;
            if (class_exists(\FoF\Upload\Helpers\Util::class)) {
                try {
                    $util = function_exists('resolve')
                        ? resolve(\FoF\Upload\Helpers\Util::class)
                        : \Illuminate\Container\Container::getInstance()->make(\FoF\Upload\Helpers\Util::class);
                } catch (Throwable $e) {}
            }

            /** @var \FoF\Upload\Adapters\Manager|null $manager */
            $manager = null;
            if (class_exists(\FoF\Upload\Adapters\Manager::class)) {
                try {
                    $manager = function_exists('resolve')
                        ? resolve(\FoF\Upload\Adapters\Manager::class)
                        : \Illuminate\Container\Container::getInstance()->make(\FoF\Upload\Adapters\Manager::class);
                } catch (Throwable $e) {}
            }

            $filesystemFactory = $this->getFilesystemFactory();
            $paths = $this->getPaths();
            $publicPath = $paths ? $paths->public : null;
            $filesBaseDir = $publicPath
                ? rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR
                : null;

            foreach ($files as $file) {
                try {
                    // Check if file is private-shared
                    $isPrivateShared = false;
                    if ($util && method_exists($util, 'isPrivateShared')) {
                        try {
                            $isPrivateShared = $util->isPrivateShared($file);
                        } catch (Throwable $e) {}
                    }

                    if ($isPrivateShared && $filesystemFactory) {
                        try {
                            $sharedDisk = $filesystemFactory->disk('private-shared');
                            if (!empty($file->path) && $sharedDisk->exists($file->path)) {
                                $sharedDisk->delete($file->path);
                            }
                            if (!empty($file->thumbnail_path) && $sharedDisk->exists($file->thumbnail_path)) {
                                $sharedDisk->delete($file->thumbnail_path);
                            }
                        } catch (Throwable $e) {}
                    } else {
                        // Resolve adapter
                        $adapter = null;
                        if ($util && method_exists($util, 'getStorageAdapterForFile')) {
                            try {
                                $adapter = $util->getStorageAdapterForFile($file);
                            } catch (Throwable $e) {}
                        }
                        if (!$adapter && $manager && !empty($file->upload_method)) {
                            try {
                                $adapter = $manager->instantiate($file->upload_method);
                            } catch (Throwable $e) {}
                        }

                        // Use FileRepository::deleteFromStorage if available (safely deletes main + thumbnail)
                        $deletedViaRepo = false;
                        if ($fileRepo && $adapter && method_exists($fileRepo, 'deleteFromStorage')) {
                            try {
                                $deletedViaRepo = $fileRepo->deleteFromStorage($file, $adapter);
                            } catch (Throwable $e) {}
                        }

                        if (!$deletedViaRepo && $adapter) {
                            // Delete thumbnail first if present
                            if (!empty($file->thumbnail_path)) {
                                try {
                                    $thumbClone = clone $file;
                                    $thumbClone->path = $file->thumbnail_path;
                                    $adapter->delete($thumbClone);
                                } catch (Throwable $e) {}
                            }

                            // Delete main file
                            try {
                                $adapter->delete($file);
                            } catch (Throwable $e) {}
                        }
                    }

                    // Fallback physical disk unlink for local files directory
                    if ($filesBaseDir) {
                        if (!empty($file->path) && file_exists($filesBaseDir . $file->path)) {
                            @unlink($filesBaseDir . $file->path);
                        }
                        if (!empty($file->thumbnail_path) && file_exists($filesBaseDir . $file->thumbnail_path)) {
                            @unlink($filesBaseDir . $file->thumbnail_path);
                        }
                    }
                } catch (Throwable $e) {}

                // Delete file database record
                try {
                    $file->delete();
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {
            try {
                \FoF\Upload\File::where('actor_id', $userId)->delete();
            } catch (Throwable $e2) {}
        }
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

        // Check registration age threshold if configured (> 0)
        $onlyRecentDays = (int) $this->settings->get('auto_ban_spam.only_recent_days', 0);
        if ($onlyRecentDays > 0 && $user->joined_at) {
            if ($user->joined_at->lt(Carbon::now()->subDays($onlyRecentDays))) {
                // User registered longer ago than threshold, skip keyword detection
                return;
            }
        }

        foreach ($textsToCheck as $text) {
            $matched = $this->detectKeyword($text);
            if ($matched !== null) {
                $this->banAndCleanUser($user, $actor);

                throw new ValidationException([
                    'auto_ban' => $this->translator->trans('qwe987299-auto-ban-spam.forum.banned_notice')
                ]);
            }
        }
    }
}
