# MyAdmin FraudRecord Plugin

Integrates FraudRecord fraud detection into the MyAdmin billing system via the plugin/hook architecture.

## Commands

```bash
composer install                   # install deps
vendor/bin/phpunit                 # run all tests (phpunit.xml.dist)
vendor/bin/phpunit tests/FraudrecordFunctionsTest.php  # functions only
vendor/bin/phpunit tests/PluginTest.php                # plugin class only
```

## Architecture

**Namespace:** `Detain\MyAdminFraudRecord\` → `src/` · **Tests:** `Detain\MyAdminFraudRecord\Tests\` → `tests/`

**CI/CD:** `.github/` contains automated test workflows (`.github/workflows/tests.yml`) for continuous integration runs via PHPUnit.

**IDE Config:** `.idea/` holds PhpStorm project settings including `inspectionProfiles/Project_Default.xml`, `deployment.xml`, and `encodings.xml`.

**Entry points:**
- `src/Plugin.php` — event hook registration via `Plugin::getHooks()`, settings via `Plugin::getSettings()`, requirements via `Plugin::getRequirements()`
- `src/fraudrecord.inc.php` — procedural functions loaded on demand: `fraudrecord_hash()`, `fraudrecord_report()`, `update_fraudrecord()`, `update_fraudrecord_noaccount()`

**Hook registration pattern** (`src/Plugin.php`):
```php
public static function getHooks() {
    return [
        'system.settings'      => [__CLASS__, 'getSettings'],
        'function.requirements' => [__CLASS__, 'getRequirements'],
    ];
}
```

**Requirements registration** — paths relative to MyAdmin root:
```php
$loader->add_requirement('fraudrecord_hash', /* path to src/fraudrecord.inc.php */);
$loader->add_page_requirement('fraudrecord_report', /* path to src/fraudrecord.inc.php */);
```

**Settings registration** (`src/Plugin.php::getSettings`):
- Group: `_('Security & Fraud')` · Section: `_('FraudRecord Fraud Detection')`
- Keys: `fraudrecord_enable`, `fraudrecord_api_key`, `fraudrecord_score_lock`, `fraudrecord_possible_fraud_score`, `fraudrecord_reporting`
- Use `add_radio_setting()` for enable/disable, `add_password_setting()` for API key, `add_text_setting()` for thresholds

**FraudRecord API calls** (`src/fraudrecord.inc.php`):
- All data hashed before transmission: `fraudrecord_hash($value)` — 32000 iterations of `sha1('fraudrecord-'.$value)`
- HTTP POST via `getcurlpage('https://www.fraudrecord.com/api/', $h, $options)` with `CURLOPT_SSL_VERIFYPEER => false`
- Query action: `'_action' => 'query'` · Report action: `'_action' => 'report'`
- Response pattern: `<report>SCORE-COUNT-RELIABILITY-CODE</report>` parsed with named captures `(?P<score>.*)`, `(?P<count>.*)`, `(?P<reliability>.*)`, `(?P<code>.*)`
- Lock threshold: `FRAUDRECORD_SCORE_LOCK` constant · Alert threshold: `FRAUDRECORD_POSSIBLE_FRAUD_SCORE` constant

## Conventions

- All event handlers are `public static function` accepting `GenericEvent $event` from `Symfony\Component\EventDispatcher`
- Get event subject: `$event->getSubject()`
- Logging: `myadmin_log('accounts', 'info', $message, __LINE__, __FILE__)`
- Email alerts: `(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/fraud.tpl')`
- Wrap i18n strings: `_('string')`
- Tabs for indentation (see `.scrutinizer.yml`)
- Commit messages: lowercase, descriptive

## Testing Patterns

- Unit tests extend `PHPUnit\Framework\TestCase` · bootstrap: `vendor/autoload.php`
- `tests/FraudrecordFunctionsTest.php` — loads `src/fraudrecord.inc.php` via `setUpBeforeClass`, tests function existence and behavior
- `tests/PluginTest.php` — uses `ReflectionClass` to verify static properties (`$name`, `$description`, `$type`), method signatures, and hook return values
- Test hook callbacks with inline anonymous class stubs (no mocking framework needed)

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
