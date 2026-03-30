---
name: plugin-test
description: Writes PHPUnit tests for Plugin classes and procedural `.inc.php` functions following patterns in `tests/PluginTest.php` and `tests/FraudrecordFunctionsTest.php`. Use when adding tests for new methods, new hook handlers, new functions, or verifying hook return values. Trigger phrases: 'write test', 'add test', 'test coverage'. Do NOT use for integration tests that hit the database or live HTTP endpoints.
---
# plugin-test

## Critical

- **Never** `require_once` a procedural file in individual test methods — load it once in `setUpBeforeClass()` and cache `file_get_contents()` in a `private static $sourceContents` property.
- **Never** call DB-heavy or cURL-dependent functions directly. Instead, assert their existence and behaviour via static source analysis (`assertStringContainsString`, `assertMatchesRegularExpression` on `$sourceContents`).
- All test classes must live in `Detain\MyAdminFraudRecord\Tests\` (maps to `tests/`). Verify `composer.json` `autoload-dev` maps this namespace before adding a new file.
- Use tabs for indentation — the project enforces tabs, not spaces.

## Instructions

### Testing a Plugin class (`tests/PluginTest.php` pattern)

1. **Declare namespace and imports.**
   ```php
   namespace Detain\MyAdminFraudRecord\Tests;

   use Detain\MyAdminFraudRecord\Plugin;
   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   use ReflectionMethod;
   use Symfony\Component\EventDispatcher\GenericEvent;
   ```
   Add `@covers \Detain\MyAdminFraudRecord\Plugin` docblock on the class.

2. **Create a `ReflectionClass` in `setUp()`.**
   ```php
   private $reflection;
   protected function setUp(): void {
       $this->reflection = new ReflectionClass(Plugin::class);
   }
   ```

3. **Verify static properties** — existence, visibility (`isPublic`, `isStatic`), and exact value:
   ```php
   $this->assertTrue($this->reflection->hasProperty('name'));
   $prop = $this->reflection->getProperty('name');
   $this->assertTrue($prop->isPublic());
   $this->assertTrue($prop->isStatic());
   $this->assertSame('Expected Name', Plugin::$name);
   ```

4. **Verify `getHooks()` return value** — assert it is an array, has the expected keys, and each callback is `[Plugin::class, 'methodName']`:
   ```php
   $hooks = Plugin::getHooks();
   $this->assertIsArray($hooks);
   $this->assertSame([Plugin::class, 'getSettings'], $hooks['system.settings']);
   ```
   Also assert all referenced method names actually exist on the class:
   ```php
   foreach ($hooks as $event => $callback) {
       [$class, $method] = $callback;
       $this->assertTrue($this->reflection->hasMethod($method),
           "Plugin is missing method '{$method}' referenced by hook '{$event}'");
   }
   ```

5. **Verify event handler signatures** — each handler must be `public static`, accept exactly one `GenericEvent $event` parameter:
   ```php
   $method = $this->reflection->getMethod('getRequirements');
   $this->assertTrue($method->isPublic());
   $this->assertTrue($method->isStatic());
   $param = $method->getParameters()[0];
   $this->assertSame('event', $param->getName());
   $this->assertSame(GenericEvent::class, $param->getType()->getName());
   ```

6. **Test `getRequirements` with an anonymous class stub** (captures calls without a real loader):
   ```php
   $calls = [];
   $loader = new class($calls) {
       private $calls;
       public function __construct(&$calls) { $this->calls = &$calls; }
       public function add_requirement(string $name, string $path): void {
           $this->calls[] = ['add_requirement', $name, $path];
       }
       public function add_page_requirement(string $name, string $path): void {
           $this->calls[] = ['add_page_requirement', $name, $path];
       }
   };
   $event = new GenericEvent($loader);
   Plugin::getRequirements($event);
   $this->assertCount(4, $calls);
   $this->assertSame('fraudrecord_report', $calls[0][1]);
   ```
   Verify with `assertCount` that the total number of registered requirements matches exactly.

### Testing procedural functions (`tests/FraudrecordFunctionsTest.php` pattern)

1. **Load the file once in `setUpBeforeClass`.**
   ```php
   private static $sourceFile;
   private static $sourceContents;

   public static function setUpBeforeClass(): void {
       self::$sourceFile = dirname(__DIR__) . '/src/fraudrecord.inc.php';
       self::$sourceContents = file_get_contents(self::$sourceFile);
       require_once self::$sourceFile;
   }
   ```

2. **For pure functions**: call them directly and assert return type, length, determinism, and algorithm correctness.

3. **For DB/cURL-dependent functions**: use `assertMatchesRegularExpression` and `assertStringContainsString` on `self::$sourceContents`. Pattern for function existence:
   ```php
   $this->assertMatchesRegularExpression(
       '/function\s+my_function\s*\(/',
       self::$sourceContents
   );
   ```
   Pattern for exact parameter signatures:
   ```php
   $this->assertMatchesRegularExpression(
       '/function\s+update_fraudrecord\s*\(\s*\$custid\s*,\s*\$module\s*=\s*\'default\'/',
       self::$sourceContents
   );
   ```

4. **Verify function count** to catch accidental additions/deletions:
   ```php
   preg_match_all('/^\s*function\s+\w+\s*\(/m', self::$sourceContents, $matches);
   $this->assertCount(4, $matches[0]);
   ```

5. **Run tests**: `vendor/bin/phpunit` or `vendor/bin/phpunit tests/FraudrecordFunctionsTest.php`.

## Examples

**User says:** "Add a test that verifies `getHooks` maps `system.settings` to `getSettings`"

**Actions taken:**
- Open `tests/PluginTest.php`
- Add to the class (using existing `$this->reflection` from `setUp`):
  ```php
  public function testGetHooksSettingsMapping(): void
  {
      $hooks = Plugin::getHooks();
      $this->assertSame([Plugin::class, 'getSettings'], $hooks['system.settings']);
  }
  ```
- Run `vendor/bin/phpunit tests/PluginTest.php` to verify green.

**User says:** "Write a test that `fraudrecord_hash` returns a 40-char hex string"

**Actions taken:**
- Open `tests/FraudrecordFunctionsTest.php` (file already loaded via `setUpBeforeClass`)
- Add:
  ```php
  public function testFraudrecordHashReturnsSha1Length(): void
  {
      $result = fraudrecord_hash('hello');
      $this->assertSame(40, strlen($result));
      $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result);
  }
  ```
- Run `vendor/bin/phpunit tests/FraudrecordFunctionsTest.php`.

## Common Issues

- **`Call to undefined function fraudrecord_hash()`**: `require_once` is missing from `setUpBeforeClass`. Verify `self::$sourceFile` path uses `dirname(__DIR__) . '/src/fraudrecord.inc.php'`.
- **`Class 'Detain\MyAdminFraudRecord\Tests\...' not found`**: Autoloader not configured. Check `composer.json` has `"Detain\\MyAdminFraudRecord\\Tests\\"` under `autoload-dev.psr-4` pointing to `"tests/"`, then run `composer dump-autoload`.
- **Anonymous class stub method not called / wrong count**: You passed `$calls` by value not by reference. The constructor must use `&$calls` in both the parameter and assignment (`$this->calls = &$calls`).
- **`ReflectionMethod::getType()` returns null**: The method under test lacks a type hint. Assert `$this->assertNotNull($type)` first; if null, check the actual method signature in `src/Plugin.php`.
- **Test file not discovered**: PHPUnit config (`phpunit.xml.dist`) must include `<directory>tests</directory>` under `<testsuites>`. Verify with `vendor/bin/phpunit --list-tests`.