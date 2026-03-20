<?php
namespace OCA\NcBitwarden\Controller;

use OCA\NcBitwarden\Service\UserSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private UserSettingsService $settingsService,
        private string $userId,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function getSettings(): JSONResponse {
        return new JSONResponse($this->settingsService->getSettings($this->userId));
    }

    #[NoAdminRequired]
    public function saveSettings(): JSONResponse {
        try {
            $this->settingsService->saveSettings(
                $this->userId,
                (string)$this->request->getParam('server_type', 'cloud_us'),
                (string)$this->request->getParam('custom_url', '')
            );
            return new JSONResponse(['status' => 'ok']);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(['error' => $e->getMessage()], 400);
        }
    }
}
