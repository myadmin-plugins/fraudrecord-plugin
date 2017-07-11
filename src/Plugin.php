<?php

namespace Detain\MyAdminFraudRecord;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'FraudRecord Plugin';
	public static $description = 'Allows handling of FraudRecord emails and honeypots';
	public static $help = '';
	public static $type = 'plugin';


	public function __construct() {
	}

	public static function getHooks() {
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			//'ui.menu' => [__CLASS__, 'getMenu'],
		];
	}

	public static function getMenu(GenericEvent $event) {
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
					if (has_acl('client_billing'))
							$menu->add_link('admin', 'choice=none.abuse_admin', '//my.interserver.net/bower_components/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', 'FraudRecord');
		}
	}

	public static function getRequirements(GenericEvent $event) {
		$loader = $event->getSubject();
		$loader->add_requirement('class.FraudRecord', '/../vendor/detain/myadmin-fraudrecord-plugin/src/FraudRecord.php');
		$loader->add_requirement('deactivate_kcare', '/../vendor/detain/myadmin-fraudrecord-plugin/src/abuse.inc.php');
		$loader->add_requirement('deactivate_abuse', '/../vendor/detain/myadmin-fraudrecord-plugin/src/abuse.inc.php');
		$loader->add_requirement('get_abuse_licenses', '/../vendor/detain/myadmin-fraudrecord-plugin/src/abuse.inc.php');
	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_radio_setting('Security & Fraud', 'FraudRecord Fraud Detection', 'fraudrecord_enable', 'Enable FraudRecord', 'Enable FraudRecord', FRAUDRECORD_ENABLE, [true, false], ['Enabled', 'Disabled']);
		$settings->add_text_setting('Security & Fraud', 'FraudRecord Fraud Detection', 'fraudrecord_api_key', 'API Key', 'API Key', (defined('FRAUDRECORD_API_KEY') ? FRAUDRECORD_API_KEY : ''));
		$settings->add_text_setting('Security & Fraud', 'FraudRecord Fraud Detection', 'fraudrecord_score_lock', 'Lock if Score > #', 'Lock if Score > #', (defined('FRAUDRECORD_SCORE_LOCK') ? FRAUDRECORD_SCORE_LOCK : ''));
		$settings->add_text_setting('Security & Fraud', 'FraudRecord Fraud Detection', 'fraudrecord_possible_fraud_score', 'Email Possible Fraud Score > #', 'Email Possible Fraud Score > #', (defined('FRAUDRECORD_POSSIBLE_FRAUD_SCORE') ? FRAUDRECORD_POSSIBLE_FRAUD_SCORE : ''));
		$settings->add_radio_setting('Security & Fraud', 'FraudRecord Fraud Detection', 'fraudrecord_reporting', 'Enable FraudRecord Reporting', 'Enable FraudRecord Reporting', FRAUDRECORD_REPORTING, [true, false], ['Enabled', 'Disabled']);
	}

}
