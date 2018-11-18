<?php

namespace Detain\MyAdminFraudRecord;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminFraudRecord
 */
class Plugin
{
	public static $name = 'FraudRecord Plugin';
	public static $description = 'Allows handling of FraudRecord based Fraud Lookups and Fraud Reporting';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			'function.requirements' => [__CLASS__, 'getRequirements'],
			//'ui.menu' => [__CLASS__, 'getMenu'],
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event)
	{
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
            if (has_acl('client_billing')) {
            }
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event)
	{
        /**
         * @var \MyAdmin\Plugins\Loader $this->loader
         */
        $loader = $event->getSubject();
		$loader->add_page_requirement('fraudrecord_report', '/../vendor/detain/myadmin-fraudrecord-plugin/src/fraudrecord.inc.php');
		$loader->add_requirement('fraudrecord_hash', '/../vendor/detain/myadmin-fraudrecord-plugin/src/fraudrecord.inc.php');
		$loader->add_requirement('update_fraudrecord', '/../vendor/detain/myadmin-fraudrecord-plugin/src/fraudrecord.inc.php');
		$loader->add_requirement('update_fraudrecord_noaccount', '/../vendor/detain/myadmin-fraudrecord-plugin/src/fraudrecord.inc.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
		$settings->add_radio_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'fraudrecord_enable', _('Enable FraudRecord'), _('Enable FraudRecord'), FRAUDRECORD_ENABLE, [true, false], ['Enabled', 'Disabled']);
		$settings->add_text_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'fraudrecord_api_key', _('API Key'), _('API Key'), (defined('FRAUDRECORD_API_KEY') ? FRAUDRECORD_API_KEY : ''));
		$settings->add_text_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'fraudrecord_score_lock', _('Lock if Score > #'), _('Lock if Score > #'), (defined('FRAUDRECORD_SCORE_LOCK') ? FRAUDRECORD_SCORE_LOCK : ''));
		$settings->add_text_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'fraudrecord_possible_fraud_score', _('Email Possible Fraud Score > #'), _('Email Possible Fraud Score > #'), (defined('FRAUDRECORD_POSSIBLE_FRAUD_SCORE') ? FRAUDRECORD_POSSIBLE_FRAUD_SCORE : ''));
		$settings->add_radio_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'fraudrecord_reporting', _('Enable FraudRecord Reporting'), _('Enable FraudRecord Reporting'), FRAUDRECORD_REPORTING, [true, false], ['Enabled', 'Disabled']);
	}
}
