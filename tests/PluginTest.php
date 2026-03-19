<?php

namespace Detain\MyAdminFraudRecord\Tests;

use Detain\MyAdminFraudRecord\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Tests for the Plugin class.
 *
 * @covers \Detain\MyAdminFraudRecord\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * @var ReflectionClass
     */
    private $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(Plugin::class);
    }

    /**
     * Test that the Plugin class exists and can be reflected.
     */
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Plugin::class));
    }

    /**
     * Test that the Plugin class is in the correct namespace.
     */
    public function testClassNamespace(): void
    {
        $this->assertSame('Detain\MyAdminFraudRecord', $this->reflection->getNamespaceName());
    }

    /**
     * Test that the Plugin class is not abstract or final.
     */
    public function testClassIsInstantiable(): void
    {
        $this->assertTrue($this->reflection->isInstantiable());
        $this->assertFalse($this->reflection->isAbstract());
        $this->assertFalse($this->reflection->isFinal());
    }

    /**
     * Test that Plugin can be constructed without arguments.
     */
    public function testConstructor(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertTrue($constructor->isPublic());
        $this->assertSame(0, $constructor->getNumberOfRequiredParameters());

        $plugin = new Plugin();
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    /**
     * Test that the $name static property exists and has the expected value.
     */
    public function testNameProperty(): void
    {
        $this->assertTrue($this->reflection->hasProperty('name'));
        $prop = $this->reflection->getProperty('name');
        $this->assertTrue($prop->isPublic());
        $this->assertTrue($prop->isStatic());
        $this->assertSame('FraudRecord Plugin', Plugin::$name);
    }

    /**
     * Test that the $description static property exists and has the expected value.
     */
    public function testDescriptionProperty(): void
    {
        $this->assertTrue($this->reflection->hasProperty('description'));
        $prop = $this->reflection->getProperty('description');
        $this->assertTrue($prop->isPublic());
        $this->assertTrue($prop->isStatic());
        $this->assertSame(
            'Allows handling of FraudRecord based Fraud Lookups and Fraud Reporting',
            Plugin::$description
        );
    }

    /**
     * Test that the $help static property exists and is an empty string.
     */
    public function testHelpProperty(): void
    {
        $this->assertTrue($this->reflection->hasProperty('help'));
        $prop = $this->reflection->getProperty('help');
        $this->assertTrue($prop->isPublic());
        $this->assertTrue($prop->isStatic());
        $this->assertSame('', Plugin::$help);
    }

    /**
     * Test that the $type static property exists and equals 'plugin'.
     */
    public function testTypeProperty(): void
    {
        $this->assertTrue($this->reflection->hasProperty('type'));
        $prop = $this->reflection->getProperty('type');
        $this->assertTrue($prop->isPublic());
        $this->assertTrue($prop->isStatic());
        $this->assertSame('plugin', Plugin::$type);
    }

    /**
     * Test that there are exactly four static properties.
     */
    public function testStaticPropertyCount(): void
    {
        $staticProps = array_filter(
            $this->reflection->getProperties(),
            fn($p) => $p->isStatic()
        );
        $this->assertCount(4, $staticProps);
    }

    /**
     * Test that getHooks is a public static method returning an array.
     */
    public function testGetHooksMethodSignature(): void
    {
        $this->assertTrue($this->reflection->hasMethod('getHooks'));
        $method = $this->reflection->getMethod('getHooks');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(0, $method->getNumberOfParameters());
    }

    /**
     * Test that getHooks returns the expected hook mappings.
     */
    public function testGetHooksReturnValue(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertIsArray($hooks);
        $this->assertArrayHasKey('system.settings', $hooks);
        $this->assertArrayHasKey('function.requirements', $hooks);
        $this->assertCount(2, $hooks);
    }

    /**
     * Test that getHooks maps system.settings to getSettings.
     */
    public function testGetHooksSettingsMapping(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame([Plugin::class, 'getSettings'], $hooks['system.settings']);
    }

    /**
     * Test that getHooks maps function.requirements to getRequirements.
     */
    public function testGetHooksRequirementsMapping(): void
    {
        $hooks = Plugin::getHooks();
        $this->assertSame([Plugin::class, 'getRequirements'], $hooks['function.requirements']);
    }

    /**
     * Test that all hook callbacks reference callable static methods on Plugin.
     */
    public function testGetHooksCallbacksAreValidMethods(): void
    {
        $hooks = Plugin::getHooks();
        foreach ($hooks as $eventName => $callback) {
            $this->assertIsArray($callback);
            $this->assertCount(2, $callback);
            [$class, $method] = $callback;
            $this->assertSame(Plugin::class, $class);
            $this->assertTrue(
                $this->reflection->hasMethod($method),
                "Plugin is missing method '{$method}' referenced by hook '{$eventName}'"
            );
        }
    }

    /**
     * Test that getMenu is a public static method accepting a GenericEvent.
     */
    public function testGetMenuMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getMenu');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $type = $param->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that getRequirements is a public static method accepting a GenericEvent.
     */
    public function testGetRequirementsMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getRequirements');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $type = $param->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that getSettings is a public static method accepting a GenericEvent.
     */
    public function testGetSettingsMethodSignature(): void
    {
        $method = $this->reflection->getMethod('getSettings');
        $this->assertTrue($method->isPublic());
        $this->assertTrue($method->isStatic());
        $this->assertSame(1, $method->getNumberOfParameters());

        $param = $method->getParameters()[0];
        $this->assertSame('event', $param->getName());
        $type = $param->getType();
        $this->assertNotNull($type);
        $this->assertSame(GenericEvent::class, $type->getName());
    }

    /**
     * Test that all event handler methods exist on the class.
     */
    public function testAllEventHandlerMethodsExist(): void
    {
        $expectedMethods = ['getMenu', 'getRequirements', 'getSettings', 'getHooks'];
        foreach ($expectedMethods as $methodName) {
            $this->assertTrue(
                $this->reflection->hasMethod($methodName),
                "Missing expected method: {$methodName}"
            );
        }
    }

    /**
     * Test that the Plugin class has exactly the expected public methods.
     */
    public function testPublicMethodCount(): void
    {
        $publicMethods = array_filter(
            $this->reflection->getMethods(ReflectionMethod::IS_PUBLIC),
            fn($m) => $m->getDeclaringClass()->getName() === Plugin::class
        );
        $this->assertCount(5, $publicMethods); // __construct, getHooks, getMenu, getRequirements, getSettings
    }

    /**
     * Test that getRequirements calls add_page_requirement and add_requirement on the loader.
     */
    public function testGetRequirementsCallsLoader(): void
    {
        $calls = [];
        $loader = new class($calls) {
            private $calls;
            public function __construct(&$calls)
            {
                $this->calls = &$calls;
            }
            public function add_page_requirement(string $name, string $path): void
            {
                $this->calls[] = ['add_page_requirement', $name, $path];
            }
            public function add_requirement(string $name, string $path): void
            {
                $this->calls[] = ['add_requirement', $name, $path];
            }
        };

        $event = new GenericEvent($loader);
        Plugin::getRequirements($event);

        $this->assertCount(4, $calls);
        $this->assertSame('add_page_requirement', $calls[0][0]);
        $this->assertSame('fraudrecord_report', $calls[0][1]);
        $this->assertSame('add_requirement', $calls[1][0]);
        $this->assertSame('fraudrecord_hash', $calls[1][1]);
        $this->assertSame('add_requirement', $calls[2][0]);
        $this->assertSame('update_fraudrecord', $calls[2][1]);
        $this->assertSame('add_requirement', $calls[3][0]);
        $this->assertSame('update_fraudrecord_noaccount', $calls[3][1]);
    }

    /**
     * Test that all requirement paths point to the fraudrecord.inc.php file.
     */
    public function testGetRequirementsPathsAreConsistent(): void
    {
        $calls = [];
        $loader = new class($calls) {
            private $calls;
            public function __construct(&$calls)
            {
                $this->calls = &$calls;
            }
            public function add_page_requirement(string $name, string $path): void
            {
                $this->calls[] = $path;
            }
            public function add_requirement(string $name, string $path): void
            {
                $this->calls[] = $path;
            }
        };

        $event = new GenericEvent($loader);
        Plugin::getRequirements($event);

        $expectedPath = '/../vendor/detain/myadmin-fraudrecord-plugin/src/fraudrecord.inc.php';
        foreach ($calls as $path) {
            $this->assertSame($expectedPath, $path);
        }
    }
}
