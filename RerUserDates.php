<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\RerUserDates;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Notification;
use Piwik\Plugins\UsersManager\API as APIUsersManager;
use Piwik\Url;
use Piwik\Version;

/**
 */
class RerUserDates extends Plugin
{
    /**
     * Default profiles value if Piwik's Settings Feature not available
     *
     * @var bool
     */
    protected $profiles = true;

    /**
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getJavaScriptFiles'      => 'getJsFiles',
            'UsersManager.getDefaultDates'         => 'noRangedDates',
            'Controller.UsersManager.userSettings' => 'userSettingsNotification',
            'Controller.CoreHome.index'            => 'checkDefaultReportDate',
            'Controller.MultiSites.index'          => 'checkDefaultReportDate',
        );
    }

    /**
     * @param $jsFiles
     */
    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/RerUserDates/javascripts/RerUserDates.js';
    }

    /**
     * Modifies Default dates UserSettings form
     *
     * @param $dates
     * @return array
     */
    public function noRangedDates(&$dates)
    {
        Piwik::checkUserIsNotAnonymous();

        $this->checkPiwikSettingsFeature();

        if (true === $this->profiles && false === $this->isSuperuser()) {
            $dates = array(
                'today'     => Piwik::translate('General_Today'),
                'yesterday' => Piwik::translate('General_Yesterday'),
                'week'      => Piwik::translate('General_CurrentWeek'),
                'month'     => Piwik::translate('General_CurrentMonth'),
                'year'      => Piwik::translate('General_CurrentYear'),
            );
        }
    }

    /**
     * Notify plugin's behaviour only to Super admins
     */
    public function userSettingsNotification()
    {
        Piwik::checkUserIsNotAnonymous();

        $this->checkPiwikSettingsFeature();

        if (true === $this->profiles && true === $this->isSuperuser()) {
            $notification = new Notification(Piwik::translate('RerUserDates_SuperuserMessage'));
            Notification\Manager::notify('RerUserDates_SuperuserMessage', $notification);
        }
    }

    /**
     * Checks if the current user has Superadmin privilege
     *
     * @return bool
     */
    protected function isSuperuser()
    {
        $userLogin = Piwik::getCurrentUserLogin();
        $user = APIUsersManager::getInstance()->getUser($userLogin);
        if (true == $user['superuser_access']) {

            return true;
        }

        return false;
    }

    /**
     * Checks if Piwik's Settings Feature is available (since 2.4.0)
     */
    protected function checkPiwikSettingsFeature()
    {
        if (version_compare(Version::VERSION, '2.4.0-b1', 'ge'))
        {
            $settings = new Settings('RerUserDates');
            $this->profiles = $settings->getSettingValue($settings->profiles);
        }
    }

    /**
     * Override for unwanted custom range selections setting to yesterday/day period with warning notification
     */
    public function checkDefaultReportDate()
    {
        Piwik::checkUserIsNotAnonymous();

        $this->checkPiwikSettingsFeature();

        if (true === $this->profiles && false === $this->isSuperuser()) {
            $userDates = APIUsersManager::getInstance()->getUserPreference(Piwik::getCurrentUserLogin(), APIUsersManager::PREFERENCE_DEFAULT_REPORT_DATE);
            $userReport = APIUsersManager::getInstance()->getUserPreference(Piwik::getCurrentUserLogin(), APIUsersManager::PREFERENCE_DEFAULT_REPORT);

            if (preg_match('/^[prev|last].+/', $userDates)) {
                APIUsersManager::getInstance()->setUserPreference(Piwik::getCurrentUserLogin(), APIUsersManager::PREFERENCE_DEFAULT_REPORT_DATE, 'yesterday');

                $notification = new Notification(Piwik::translate('RerUserDates_DefaultDateMessage'));
                $notification->context = Notification::CONTEXT_WARNING;
                Notification\Manager::notify('RerUserDates_DefaultDateMessage', $notification);

                $period = Common::getRequestVar('period');
                if ('range' == $period) {
                    Piwik::redirectToModule($userReport,'index', array('period' => 'day', 'date' => 'yesterday'));
                }
            }
        }
    }

}
