---
name: plugin-hook-setup
description: Registers new MyAdmin plugin hooks and settings in src/Plugin.php. Use when adding a new event listener ('add hook'), a new admin setting ('register setting'), or a new function_requirements entry ('new event listener', 'new requirement'). Covers getHooks(), getSettings(), getRequirements() patterns. Do NOT use for modifying src/fraudrecord.inc.php logic or creating the plugin package itself.
---
# plugin-hook-setup

## Critical

- Every event handler MUST be `public static function` accepting `GenericEvent $event` — no exceptions.
- Every new handler added to `getHooks()` MUST have a corresponding method on the class, or tests will fail (`testGetHooksCallbacksAreValidMethods`).
- Use `[__CLASS__, 'methodName']` (not a string) as the callback value in `getHooks()`.
- Use tabs for indentation throughout `src/Plugin.php`.
- Wrap all user-visible strings in `_('string')` for gettext.

## Instructions

1. **Register the hook in `getHooks()`** (`src/Plugin.php`).
   Add a key/value pair to the returned array:
   ```php
   public static function getHooks()
   {
       return [
           'system.settings'       => [__CLASS__, 'getSettings'],
           'function.requirements' => [__CLASS__, 'getRequirements'],
           'your.event.name'       => [__CLASS__, 'yourHandlerMethod'],
       ];
   }
   ```
   Verify: the string key matches the event name fired via `run_event()` in MyAdmin core.

2. **Implement the handler method** on the Plugin class.
   Signature must match exactly — one `GenericEvent $event` parameter:
   ```php
   /**
    * @param \Symfony\Component\EventDispatcher\GenericEvent $event
    */
   public static function yourHandlerMethod(GenericEvent $event)
   {
       $subject = $event->getSubject();
       // act on $subject
       myadmin_log('accounts', 'info', _('Your message'), __LINE__, __FILE__);
   }
   ```
   Verify: method is `public static`, accepts exactly one typed `GenericEvent` param.

3. **Register a new function requirement** (use this step when adding a new procedural function in `src/fraudrecord.inc.php` that must be lazy-loaded).
   Inside `getRequirements()` in `src/Plugin.php`, add to the loader:
   ```php
   public static function getRequirements(GenericEvent $event)
   {
       $loader = $event->getSubject();
       // page requirement — loads only on matching page request:
       $loader->add_page_requirement('function_name', /* path to src/fraudrecord.inc.php */);
       // general requirement — always available:
       $loader->add_requirement('function_name', /* path to src/fraudrecord.inc.php */);
   }
   ```
   Use `add_page_requirement` for functions only needed on specific pages; `add_requirement` for functions needed globally.

4. **Register a new admin setting** inside `getSettings()` in `src/Plugin.php`.
   Subject is `\MyAdmin\Settings`. Choose the right helper:
   ```php
   public static function getSettings(GenericEvent $event)
   {
       $settings = $event->getSubject();
       // Boolean enable/disable toggle:
       $settings->add_radio_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'setting_key', _('Label'), _('Description'), SETTING_CONSTANT, [true, false], ['Enabled', 'Disabled']);
       // Secret value (masked in UI):
       $settings->add_password_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'setting_key', _('Label'), _('Description'), (defined('SETTING_CONSTANT') ? SETTING_CONSTANT : ''));
       // Numeric or text threshold:
       $settings->add_text_setting(_('Security & Fraud'), _('FraudRecord Fraud Detection'), 'setting_key', _('Label'), _('Description'), (defined('SETTING_CONSTANT') ? SETTING_CONSTANT : ''));
   }
   ```
   Verify: `defined()` guard is used for constants that may not exist in all environments.

5. **Run tests** to confirm nothing regressed:
   ```bash
   vendor/bin/phpunit tests/PluginTest.php
   ```
   All assertions in `testGetHooksCallbacksAreValidMethods` and `testPublicMethodCount` will fail if the new method is missing or not public/static.

## Examples

**User says:** "Add a hook for `ui.menu` that checks admin ACL"

**Actions taken:**
1. Add `'ui.menu' => [__CLASS__, 'getMenu']` to `getHooks()` return array in `src/Plugin.php` (uncomment existing stub or add new entry).
2. Implement/uncomment `getMenu(GenericEvent $event)` on the class:
   ```php
   public static function getMenu(GenericEvent $event)
   {
       $menu = $event->getSubject();
       if ($GLOBALS['tf']->ima == 'admin') {
           function_requirements('has_acl');
           if (has_acl('client_billing')) {
               // add menu items here
           }
       }
   }
   ```
3. Run `vendor/bin/phpunit tests/PluginTest.php` — `testPublicMethodCount` expects 5 public methods; adding a previously commented method keeps the count correct.

**Result:** `getHooks()` returns 3 entries; `getMenu` is callable via event dispatch.

## Common Issues

- **`testGetHooksCallbacksAreValidMethods` fails** — a hook in `getHooks()` references a method name that doesn't exist or is misspelled on the class. Fix: verify the method name string matches the actual method exactly (case-sensitive).
- **`testPublicMethodCount` fails with count mismatch** — you added or removed a public method without updating the test's expected count. Fix: update the `assertCount(N, $publicMethods)` assertion in `tests/PluginTest.php` to reflect the new total.
- **`testGetRequirementsPathsAreConsistent` fails** — a new `add_requirement` call uses a different path than the other requirements in `src/Plugin.php`. Fix: all requirements for this plugin must point to the same `src/fraudrecord.inc.php` file.
- **Settings constant not defined in test environment** — `PHP Notice: Use of undefined constant` during `getSettings()`. Fix: always guard constants with `(defined('CONST') ? CONST : '')` as shown in Step 4.
- **Hook never fires** — event name in `getHooks()` does not match the string passed to `run_event()` in MyAdmin core. Fix: grep MyAdmin core for the exact event name string.
