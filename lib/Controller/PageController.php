<?php
namespace OCA\NcBitwarden\Controller;

use OCA\NcBitwarden\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(IRequest $request) {
        parent::__construct(Application::APP_ID, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addScript(Application::APP_ID, 'nc_bitwarden-main');
        Util::addStyle(Application::APP_ID,  'nc_bitwarden-main');
        return new TemplateResponse(Application::APP_ID, 'main');
    }
}
