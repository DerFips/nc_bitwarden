<?php
namespace OCA\NcBitwarden\Settings;

use OCA\NcBitwarden\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class Personal implements ISettings {
    public function getForm(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'nc_bitwarden-settings');
        return new TemplateResponse(Application::APP_ID, 'settings');
    }
    public function getSection(): string { return Application::APP_ID; }
    public function getPriority(): int   { return 10; }
}
