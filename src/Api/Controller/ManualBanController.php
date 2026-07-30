<?php

namespace Qwe987299\AutoBanSpam\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Qwe987299\AutoBanSpam\Service\AutoBanService;

class ManualBanController implements RequestHandlerInterface
{
    protected AutoBanService $autoBanService;

    public function __construct(AutoBanService $autoBanService)
    {
        $this->autoBanService = $autoBanService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertPermission('autoBanSpam.manualBan');

        $id = Arr::get($request->getQueryParams(), 'id');
        if (!$id) {
            throw new ValidationException(['user' => 'User ID is required.']);
        }

        $user = User::findOrFail($id);

        if ($user->isAdmin()) {
            throw new ValidationException(['user' => 'Cannot ban an administrator.']);
        }

        $this->autoBanService->banAndCleanUser($user, $actor);

        return new JsonResponse(['success' => true]);
    }
}
