---
name: fraudrecord-api
description: Implements FraudRecord API queries and reports in `src/fraudrecord.inc.php`. Use when adding a new query type, changing hashing logic in `fraudrecord_hash()`, or adding a new report action. Covers `getcurlpage()` POST, `<report>` response parsing, score threshold checks. Trigger phrases: 'query fraudrecord', 'report fraud', 'hash customer data'. Do NOT use for Plugin class hook or settings changes.
---
# FraudRecord API

## Critical

- **Never transmit raw PII.** All customer fields (email, name, IP) MUST be passed through `fraudrecord_hash()` before inclusion in any `$h` payload.
- **Never skip empty-value guards.** Unset any hashed field whose source value is empty (trim check) before calling `getcurlpage()` — the API rejects blank hashed fields.
- **Always use constants** `FRAUDRECORD_API_KEY`, `FRAUDRECORD_SCORE_LOCK`, `FRAUDRECORD_POSSIBLE_FRAUD_SCORE` — never hardcode values.
- **Do NOT use PDO or cURL directly** — use `getcurlpage('https://www.fraudrecord.com/api/', $h, $options)`.

## Instructions

1. **Hash customer data** with `fraudrecord_hash($value)` — 32 000 SHA-1 iterations with `'fraudrecord-'` prefix:
   ```php
   function fraudrecord_hash($value)
   {
       for ($i = 0; $i < 32000; $i++) {
           $value = sha1('fraudrecord-'.$value);
       }
       return $value; // 40-char lowercase hex string
   }
   ```
   Apply normalization before hashing: `strtolower(str_replace(' ', '', trim($raw)))` for name/email; plain `trim()` for IP.
   Verify: result is a 40-char hex string matching `/^[0-9a-f]{40}$/`.

2. **Build the payload array** with `'_action'` set to `'query'` or `'report'`, plus `'_api' => FRAUDRECORD_API_KEY`.
   - Query payload (see `update_fraudrecord()`):
     ```php
     $h = [
         '_action' => 'query',
         '_api'    => FRAUDRECORD_API_KEY,
     ];
     $h['ip']    = fraudrecord_hash(trim($ip));      // unset if empty
     $h['email'] = fraudrecord_hash(strtolower(trim($data['account_lid'])));
     ```
   - Report payload (see `fraudrecord_report()`):
     ```php
     $h = [
         '_action' => 'report',
         '_api'    => FRAUDRECORD_API_KEY,
         '_type'   => $type,
         '_text'   => $text,
         '_value'  => $value,
         'name'    => fraudrecord_hash(strtolower(str_replace(' ', '', trim($data['name'])))),
         'email'   => fraudrecord_hash(strtolower(trim($data['account_lid']))),
         'ip'      => fraudrecord_hash($ip),
     ];
     if (trim($ip) == '')          { unset($h['ip']); }
     if (trim($data['name']) == '') { unset($h['name']); }
     ```
   Verify: `$h` contains no raw PII and no empty-string values before proceeding.

3. **Send the request** using `getcurlpage()` with SSL peer verification disabled:
   ```php
   $options = [
       CURLOPT_POST          => count($h),
       CURLOPT_SSL_VERIFYPEER => false,
   ];
   $h = getcurlpage('https://www.fraudrecord.com/api/', $h, $options);
   ```
   The variable `$h` is reused for the raw response string.

4. **Parse the `<report>` response** (query action only) using the exact named-capture regex:
   ```php
   if (preg_match('/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/', $h, $matches)) {
       unset($matches[0], $matches[1], $matches[2], $matches[3], $matches[4]);
       // named keys: score, count, reliability, code
   } else {
       myadmin_log('accounts', 'info', "fraudrecord got blank response ".$h, __LINE__, __FILE__);
   }
   ```
   Verify: after `unset`, `$matches` contains only the four named keys.

5. **Apply score thresholds** after a successful parse:
   ```php
   if ($matches['score'] >= FRAUDRECORD_SCORE_LOCK) {
       // disable account — call disable_account($custid, $module) after function_requirements('disable_account')
       myadmin_log('accounts', 'info', "High score, disabling account", __LINE__, __FILE__);
   }
   if ($matches['score'] > FRAUDRECORD_POSSIBLE_FRAUD_SCORE) {
       $subject = TITLE.' FraudRecord Possible Fraud';
       (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/fraud.tpl');
       myadmin_log('accounts', 'info', "score >threshold, emailing possible fraud", __LINE__, __FILE__);
   }
   ```

6. **Log all actions** with `myadmin_log('accounts', 'info', $message, __LINE__, __FILE__)`. Use `str_replace("\n", '', var_export($matches, true))` when logging the full match array.

7. **Run tests** after any change:
   ```bash
   vendor/bin/phpunit tests/FraudrecordFunctionsTest.php
   ```

## Examples

**User says:** "Add a query that also sends the customer's phone number to FraudRecord"

**Actions taken:**
1. In `update_fraudrecord()`, after the existing `$h['email']` line, add:
   ```php
   if (isset($data['phone']) && trim($data['phone']) != '') {
       $h['phone'] = fraudrecord_hash(strtolower(str_replace(' ', '', trim($data['phone']))));
   }
   ```
2. Field is only included when non-empty (guard matches existing `ip`/`name` pattern).
3. Run `vendor/bin/phpunit tests/FraudrecordFunctionsTest.php` — all tests must pass.

**Result:** Phone hash appears in the POST payload; no raw PII transmitted; empty-phone guard prevents API rejection.

## Common Issues

- **API returns empty string or non-`<report>` response:** The `preg_match` branch is skipped and the else-branch logs `"got blank response"`. Check that `FRAUDRECORD_API_KEY` constant is defined and non-empty at call time.
- **`unset($h['ip'])` not reached, API rejects request:** Ensure the empty-string guard uses `trim()`: `if (trim($ip) == '') { unset($h['ip']); }` — a string of spaces will pass `== ''` only after trimming.
- **Hash mismatch with FraudRecord server records:** Confirm normalization order: `strtolower` then `str_replace(' ', '', ...)` then `trim()` — applying trim last can leave internal spaces in names.
- **`CURLOPT_SSL_VERIFYPEER` not recognized:** This constant comes from the PHP cURL extension. Verify `extension=curl` is enabled: `php -m | grep curl`.
- **`testSourceFileDefinesFourFunctions` fails after adding a new function:** The test asserts exactly 4 functions exist. If you add a fifth, update the count assertion in `tests/FraudrecordFunctionsTest.php:213`.