<?php
namespace OCA\NcBitwarden\Settings;

use OCA\NcBitwarden\AppInfo\Application;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {
    public function __construct(
        private IL10N         $l,
        private IURLGenerator $urlGenerator,
    ) {}

    public function getID(): string    { return Application::APP_ID; }
    public function getName(): string  { return $this->l->t('Bitwarden'); }
    public function getPriority(): int { return 75; }
    public function getIcon(): string  {
        return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
    }
}
