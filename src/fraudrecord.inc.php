<?php
/**
 * fraudrecord Fraud Stuff
 *
 * API Downloaded @ http://www.maxmind.com/download/ccfd/
 * MaxMind Fields @ http://www.maxmind.com/app/ccv
 * Fields and Descriptions @ http://www.maxmind.com/app/fraud-detection-manual
 * Set your MaxMind Version @ http://www.maxmind.com/app/minfraud_version
 * Score Formula from http://www.maxmind.com/app/ccfd_formula
 * The "score" is calculated as follows:
 * score = 	2.5 * isFreeEmail +
 * 2.5 * countryDoesntMatch +
 * 5 * isAnonymousProxy +
 * 5 * highRiskCountry +
 * 10 * min(distance,5000) / maxEarthArc +
 * 2 * binDoesntMatch +
 * 1 * binNameDoesntMatch +
 * 5 * carderEmail +
 * 5 * highRiskUsername +
 * 5 * highRiskPassword +
 * 5 * shipForward +
 * 2.5 * proxyScore
 * Note this formula is capped at 10. maxEarthArc is defined as 20037 kilometers.
 *
 * @author Joe Huss <detain@interserver.net>
 * @copyright 2018
 * @package MyAdmin
 * @category General
 */

/**
 * @param $value
 * @return string
 */
function fraudrecord_hash($value)
{
	for ($i = 0; $i < 32000; $i++) {
		$value = sha1('fraudrecord-'.$value);
	}
	return $value;
}

/**
 * @param $custid
 * @param $module
 * @param $type
 * @param $text
 * @param $value
 */
function fraudrecord_report($custid, $module, $type, $text, $value)
{
	//myadmin_log('accounts', 'info', "fraudrecord_report($custid, $module, $type, $text, $value) Called", __LINE__, __FILE__);
	$module = get_module_name($module);
	$db = get_module_db($module);
	$GLOBALS['tf']->accounts->set_db_module($module);
	$GLOBALS['tf']->history->set_db_module($module);
	$data = $GLOBALS['tf']->accounts->read($custid);
	$lid = $data['account_lid'];
	$GLOBALS['tf']->accounts->set_db_module('default');
	$log_custid = $GLOBALS['tf']->accounts->cross_reference($custid);
	$defaultdb = $GLOBALS['tf']->db;
	$defaultdb->query("select access_ip from access_log where access_owner='{$log_custid}' limit 1", __LINE__, __FILE__);
	$defaultdb->next_record(MYSQL_ASSOC);
	$ip = $defaultdb->Record['access_ip'];
	$h = [
		'_action' => 'report',
		'_api' => FRAUDRECORD_API_KEY,
		'_type' => $type,
		'_text' => $text,
		'_value' => $value,
		'name' => fraudrecord_hash(strtolower(str_replace(' ', '', trim($data['name'])))),
		'email' => fraudrecord_hash(strtolower(trim($data['account_lid']))),
		'ip' => fraudrecord_hash($ip)
	];
	if (trim($ip) == '') {
		unset($h['ip']);
	}
	if (trim($data['name']) == '') {
		unset($h['name']);
	}
	$options = [
		CURLOPT_POST => count($h),
		CURLOPT_SSL_VERIFYPEER => false
	];
	$h = getcurlpage('https://www.fraudrecord.com/api/', $h, $options);
	add_output($h);
}

/**
 * update_fraudrecord()
 * updates the fraudrecord data for a given user.
 *
 * @param integer     $custid customer id
 * @param string      $module module to update it with
 * @param bool|string $ip     ip address to register with the query, or false to have it use session ip
 * @return bool pretty much always returns true
 * @throws \Exception
 */
function update_fraudrecord($custid, $module = 'default', $ip = false)
{
	//myadmin_log('accounts', 'info', "update_fraudrecord($custid, $module) Called", __LINE__, __FILE__);
	$module = get_module_name($module);
	$db = get_module_db($module);
	$GLOBALS['tf']->accounts->set_db_module($module);
	$GLOBALS['tf']->history->set_db_module($module);
	$data = $GLOBALS['tf']->accounts->read($custid);
	$new_data = [];
	if (isset($data['cc_whitelist']) && $data['cc_whitelist'] == 1) {
		myadmin_log('accounts', 'info', "update_fraudrecord({$custid}, {$module}) Customer is White Listed for CCs, Updating (non destructively) fraudrecord", __LINE__, __FILE__);
		//return true;
	}
	$h = [
		'_action' => 'query',
		'_api' => FRAUDRECORD_API_KEY
	];
	if ($ip === false) {
		$h['ip'] = trim(\MyAdmin\Session::get_client_ip());
	} else {
		$h['ip'] = trim($ip);
	}
	if ($ip != '') {
		$h['ip'] = fraudrecord_hash($h['ip']);
	} else {
		unset($h['ip']);
	}
	if (!isset($data['country']) || trim($data['country']) == '') {
		$data['country'] = 'US';
		$new_data['country'] = 'US';
	}
	//$fields = array('address', 'city', 'state', 'zip', 'name', 'country');
	//$fields = array('name');
	$fields = [];
	foreach ($fields as $field) {
		if (isset($data[$field]) && trim($data[$field]) != '') {
			$h[$field] = fraudrecord_hash(strtolower(str_replace(' ', '', trim($data[$field]))));
		}
	}
	$h['email'] = fraudrecord_hash(strtolower(trim($data['account_lid'])));
	//myadmin_log('accounts', 'info', "Calling With Arguments: " . str_replace("\n", '', var_export($h, TRUE)), __LINE__, __FILE__);
	$options = [
		CURLOPT_POST => count($h),
		CURLOPT_SSL_VERIFYPEER => false
	];
	$h = getcurlpage('https://www.fraudrecord.com/api/', $h, $options);
	// should return like '<report>0-0-0.0-8ef255ff538622eb</report>'
	if (preg_match('/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/', $h, $matches)) {
		unset($matches[0]);
		unset($matches[1]);
		unset($matches[2]);
		unset($matches[3]);
		unset($matches[4]);
		$smarty = new TFSmarty;
		$smarty->assign('account_id', $custid);
		$smarty->assign('account_lid', $GLOBALS['tf']->accounts->cross_reference($custid));
		$tmatches = $matches;
		$tmatches['code'] = '<a href="https://www.fraudrecord.com/api/?showreport='.$matches['code'].'">https://www.fraudrecord.com/api/?showreport='.$matches['code'].'</a>';
		$smarty->assign('fraudArray', $tmatches);
		$email = $smarty->fetch('email/admin/fraud.tpl');
		$new_data['fraudrecord_score'] = trim($matches['score']);
		$new_data['fraudrecord'] = myadmin_stringify($matches, 'json');
		myadmin_log('accounts', 'info', "update_fraudrecord($custid, $module) fraudrecord Output: ".str_replace("\n", '', var_export($matches, true)), __LINE__, __FILE__);
		//myadmin_log('accounts', 'info', "    fraudrecord Score: " . $matches['score'], __LINE__, __FILE__);

		if ($matches['score'] >= FRAUDRECORD_SCORE_LOCK) {
			myadmin_log('accounts', 'info', "update_fraudrecord({$custid}, {$module}) Carder Email Or High Score From Customer {$custid}, Disabling Account", __LINE__, __FILE__);
			if (!isset($data['cc_whitelist']) || $data['cc_whitelist'] != 1) {
				function_requirements('disable_account');
				disable_account($custid, $module);
			}
		}
		if ($matches['score'] > FRAUDRECORD_POSSIBLE_FRAUD_SCORE) {
			$subject = TITLE.' FraudRecord Possible Fraud';
			admin_mail($subject, $email, false, false, 'admin/fraud.tpl');
			myadmin_log('accounts', 'info', "update_fraudrecord($custid, $module)  $matches[score] >1.0,   Emailing Possible Fraud", __LINE__, __FILE__);
		}
		$GLOBALS['tf']->accounts->update($custid, $new_data);
	} else {
		myadmin_log('accounts', 'info', "update_fraudrecord($custid, $module) got blank response ".$h, __LINE__, __FILE__);
	}
	return true;
}

/**
 * update_fraudrecord_noaccount()
 * does a fraudrecord update on an array of data without actually checking or modifying an actual account.
 *
 * @param array $data the array of user data to get fraudrecord info for.
 * @return array the input $data but with the fraudrecord fields set
 * @throws \Exception
 * @throws \SmartyException
 */
function update_fraudrecord_noaccount($data)
{
	//myadmin_log('accounts', 'info', "update_fraudrecord_noaccount Called", __LINE__, __FILE__);
	$h = [
		'_action' => 'query',
		'_api' => FRAUDRECORD_API_KEY
	];
	$h['ip'] = fraudrecord_hash(\MyAdmin\Session::get_client_ip());
	if (!isset($data['country']) || trim($data['country']) == '') {
		$data['country'] = 'US';
		$new_data['country'] = 'US';
	}
	//$fields = array('address', 'city', 'state', 'zip', 'name', 'country');
	//$fields = array('name');
	$fields = [];
	foreach ($fields as $field) {
		if (isset($data[$field]) && trim($data[$field]) != '') {
			$h[$field] = fraudrecord_hash(strtolower(str_replace(' ', '', trim($data[$field]))));
		}
	}
	$h['email'] = fraudrecord_hash(strtolower(trim($data['lid'])));
	//myadmin_log('accounts', 'info', "update_fraudrecord($custid, $module) Calling With Arguments: " . str_replace("\n", '', var_export($h, TRUE)), __LINE__, __FILE__);
	$options = [
		CURLOPT_POST => count($h),
		CURLOPT_SSL_VERIFYPEER => false
	];
	$h = getcurlpage('https://www.fraudrecord.com/api/', $h, $options);
	// should return like '<report>0-0-0.0-8ef255ff538622eb</report>'
	if (preg_match('/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/', $h, $matches)) {
		unset($matches[0]);
		unset($matches[1]);
		unset($matches[2]);
		unset($matches[3]);
		unset($matches[4]);
		$smarty = new TFSmarty;
		$smarty->assign('account_id', $custid);
		$smarty->assign('account_lid', $GLOBALS['tf']->accounts->cross_reference($custid));
		$smarty->assign('fraudArray', $matches);
		$email = $smarty->fetch('email/admin/fraud.tpl');
		$headers = '';
		$headers .= 'MIME-Version: 1.0'.PHP_EOL;
		$headers .= 'Content-type: text/html; charset=UTF-8'.PHP_EOL;
		$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.PHP_EOL;
		$data['fraudrecord_score'] = trim($matches['score']);
		$data['fraudrecord'] = myadmin_stringify($matches, 'json');
		myadmin_log('accounts', 'info', "update_fraudrecord({$custid}, {$module}) fraudrecord Output: ".str_replace("\n", '', var_export($matches, true)), __LINE__, __FILE__);
		//myadmin_log('accounts', 'info', "    fraudrecord Score: " . $matches['score'], __LINE__, __FILE__);
		if ($matches['score'] >= 10.0) {
			$data['status'] = 'locked';
		}
	}
	return $data;
}
