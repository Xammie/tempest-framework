<?php

declare(strict_types=1);

namespace Tempest\Container\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tempest\Container\Exceptions\CircularDependencyEncountered;
use Tempest\Container\Exceptions\DependencyCouldNotBeAutowired;
use Tempest\Container\Exceptions\DependencyCouldNotBeInstantiated;
use Tempest\Container\Exceptions\InvokedCallableWasInvalid;
use Tempest\Container\Exceptions\TaggedDependencyCouldNotBeResolved;
use Tempest\Container\GenericContainer;
use Tempest\Container\Tests\Fixtures\BuiltinArrayClass;
use Tempest\Container\Tests\Fixtures\BuiltinDependencyArrayInitializer;
use Tempest\Container\Tests\Fixtures\BuiltinDependencyBoolInitializer;
use Tempest\Container\Tests\Fixtures\BuiltinDependencyStringInitializer;
use Tempest\Container\Tests\Fixtures\BuiltinTypesWithDefaultsClass;
use Tempest\Container\Tests\Fixtures\CallContainerObjectE;
use Tempest\Container\Tests\Fixtures\CircularWithInitializerA;
use Tempest\Container\Tests\Fixtures\CircularWithInitializerBInitializer;
use Tempest\Container\Tests\Fixtures\ClassWithLazySlowDependency;
use Tempest\Container\Tests\Fixtures\ClassWithLazySlowPropertyDependency;
use Tempest\Container\Tests\Fixtures\ClassWithSingletonAttribute;
use Tempest\Container\Tests\Fixtures\ClassWithSlowDependency;
use Tempest\Container\Tests\Fixtures\ContainerObjectA;
use Tempest\Container\Tests\Fixtures\ContainerObjectB;
use Tempest\Container\Tests\Fixtures\ContainerObjectC;
use Tempest\Container\Tests\Fixtures\ContainerObjectD;
use Tempest\Container\Tests\Fixtures\ContainerObjectDInitializer;
use Tempest\Container\Tests\Fixtures\ContainerObjectE;
use Tempest\Container\Tests\Fixtures\ContainerObjectEInitializer;
use Tempest\Container\Tests\Fixtures\DependencyWithBuiltinDependencies;
use Tempest\Container\Tests\Fixtures\DependencyWithTaggedDependency;
use Tempest\Container\Tests\Fixtures\EnumTag;
use Tempest\Container\Tests\Fixtures\HasTagObject;
use Tempest\Container\Tests\Fixtures\ImplementsInterfaceA;
use Tempest\Container\Tests\Fixtures\InjectA;
use Tempest\Container\Tests\Fixtures\InjectB;
use Tempest\Container\Tests\Fixtures\InterfaceA;
use Tempest\Container\Tests\Fixtures\IntersectionInitializer;
use Tempest\Container\Tests\Fixtures\InvokableClass;
use Tempest\Container\Tests\Fixtures\InvokableClassWithDependencies;
use Tempest\Container\Tests\Fixtures\InvokableClassWithParameters;
use Tempest\Container\Tests\Fixtures\OptionalTypesClass;
use Tempest\Container\Tests\Fixtures\SingletonClass;
use Tempest\Container\Tests\Fixtures\SingletonInitializer;
use Tempest\Container\Tests\Fixtures\SlowDependency;
use Tempest\Container\Tests\Fixtures\TaggedDependency;
use Tempest\Container\Tests\Fixtures\TaggedDependencyCliInitializer;
use Tempest\Container\Tests\Fixtures\TaggedDependencyWebInitializer;
use Tempest\Container\Tests\Fixtures\UnionImplementation;
use Tempest\Container\Tests\Fixtures\UnionInitializer;
use Tempest\Container\Tests\Fixtures\UnionInterfaceA;
use Tempest\Container\Tests\Fixtures\UnionInterfaceB;
use Tempest\Container\Tests\Fixtures\UnionTypesClass;
use Tempest\Reflection\ClassReflector;

use function Tempest\reflect;

/**
 * @internal
 */
final class ContainerTest extends TestCase
{
    public function test_get_with_autowire(): void
    {
        $container = new GenericContainer();

        $b = $container->get(ContainerObjectB::class);

        $this->assertInstanceOf(ContainerObjectB::class, $b);
        $this->assertInstanceOf(ContainerObjectA::class, $b->a);
    }

    public function test_get_with_definition(): void
    {
        $container = new GenericContainer();

        $container->register(
            ContainerObjectC::class,
            fn () => new ContainerObjectC(prop: 'test'),
        );

        $c = $container->get(ContainerObjectC::class);

        $this->assertEquals('test', $c->prop);
    }

    public function test_get_with_initializer(): void
    {
        $container = new GenericContainer()->setInitializers([
            ContainerObjectD::class => ContainerObjectDInitializer::class,
        ]);

        $d = $container->get(ContainerObjectD::class);

        $this->assertEquals('test', $d->prop);
    }

    public function test_singleton(): void
    {
        $container = new GenericContainer();

        $container->singleton(SingletonClass::class, fn () => new SingletonClass());

        $instance = $container->get(SingletonClass::class);

        $this->assertEquals(1, $instance::$count);

        $instance = $container->get(SingletonClass::class);

        $this->assertEquals(1, $instance::$count);
    }

    public function test_initialize_with_can_initializer(): void
    {
        $container = new GenericContainer();

        $container->addInitializer(ContainerObjectEInitializer::class);

        $object = $container->get(ContainerObjectE::class);

        $this->assertInstanceOf(ContainerObjectE::class, $object);
    }

    public function test_call_tries_to_transform_unmatched_values(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(ContainerObjectEInitializer::class);

        $classToCall = new CallContainerObjectE();

        $return = $container->invoke(reflect($classToCall)->getMethod('method'), input: '1');
        $this->assertInstanceOf(ContainerObjectE::class, $return);
        $this->assertSame('default', $return->id);

        $return = $container->invoke(reflect($classToCall)->getMethod('method'), input: new ContainerObjectE('other'));
        $this->assertInstanceOf(ContainerObjectE::class, $return);
        $this->assertSame('other', $return->id);
    }

    public function test_arrays_are_automatically_created(): void
    {
        $container = new GenericContainer();

        /**
         * @var BuiltinArrayClass $class
         */
        $class = $container->get(BuiltinArrayClass::class);

        $this->assertEmpty($class->anArray);
    }

    public function test_builtin_defaults_are_used(): void
    {
        $container = new GenericContainer();

        /**
         * @var BuiltinTypesWithDefaultsClass $class
         */
        $class = $container->get(BuiltinTypesWithDefaultsClass::class);

        $this->assertSame('This is a default value', $class->aString);
    }

    public function test_optional_types_resolve_to_null(): void
    {
        $container = new GenericContainer();

        /**
         * @var OptionalTypesClass $class
         */
        $class = $container->get(OptionalTypesClass::class);

        $this->assertNull($class->aString);
    }

    public function test_union_types_iterate_to_resolution(): void
    {
        $container = new GenericContainer();

        /** @var UnionTypesClass $class */
        $class = $container->get(UnionTypesClass::class);

        $this->assertInstanceOf(UnionTypesClass::class, $class);
        $this->assertInstanceOf(ContainerObjectA::class, $class->input);
    }

    public function test_singleton_initializers(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(SingletonInitializer::class);

        $a = $container->get(ContainerObjectE::class);
        $b = $container->get(ContainerObjectE::class);
        $this->assertSame(spl_object_id($a), spl_object_id($b));
    }

    public function test_union_initializers(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(UnionInitializer::class);

        $a = $container->get(UnionInterfaceA::class);
        $b = $container->get(UnionInterfaceB::class);

        $this->assertInstanceOf(UnionImplementation::class, $a);
        $this->assertInstanceOf(UnionImplementation::class, $b);
    }

    public function test_intersection_initializers(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(IntersectionInitializer::class);

        $a = $container->get(UnionInterfaceA::class);
        $b = $container->get(UnionInterfaceB::class);

        $this->assertInstanceOf(UnionImplementation::class, $a);
        $this->assertInstanceOf(UnionImplementation::class, $b);
    }

    public function test_circular_with_initializer_log(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(CircularWithInitializerBInitializer::class);
        $this->assertContains(CircularWithInitializerBInitializer::class, $container->getInitializers());

        try {
            $container->get(CircularWithInitializerA::class);
        } catch (CircularDependencyEncountered $circularDependencyException) {
            $this->assertStringContainsString('CircularWithInitializerA', $circularDependencyException->getMessage());
            $this->assertStringContainsString('CircularWithInitializerB', $circularDependencyException->getMessage());
            $this->assertStringContainsString('CircularWithInitializerBInitializer', $circularDependencyException->getMessage());
            $this->assertStringContainsString('CircularWithInitializerC', $circularDependencyException->getMessage());
            $this->assertStringContainsString(__FILE__, $circularDependencyException->getMessage());
        }
    }

    public function test_tagged_singleton(): void
    {
        $container = new GenericContainer();

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('web'),
            tag: 'web',
        );

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('cli'),
            tag: 'cli',
        );

        $this->assertSame('web', $container->get(TaggedDependency::class, 'web')->name);
        $this->assertSame('cli', $container->get(TaggedDependency::class, 'cli')->name);
    }

    public function test_tagged_singleton_with_enum(): void
    {
        $container = new GenericContainer();

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('web'),
            tag: EnumTag::FOO,
        );

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('cli'),
            tag: EnumTag::BAR,
        );

        $this->assertSame('web', $container->get(TaggedDependency::class, EnumTag::FOO)->name);
        $this->assertSame('cli', $container->get(TaggedDependency::class, EnumTag::BAR)->name);
    }

    public function test_tagged_singleton_with_initializer(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(TaggedDependencyWebInitializer::class);
        $container->addInitializer(TaggedDependencyCliInitializer::class);

        $this->assertSame('web', $container->get(TaggedDependency::class, 'web')->name);
        $this->assertSame('cli', $container->get(TaggedDependency::class, 'cli')->name);
    }

    public function test_tagged_singleton_exception(): void
    {
        $container = new GenericContainer();

        $this->expectException(TaggedDependencyCouldNotBeResolved::class);

        $container->get(TaggedDependency::class, 'web');
    }

    public function test_autowired_tagged_dependency(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(TaggedDependencyWebInitializer::class);

        $dependency = $container->get(DependencyWithTaggedDependency::class);
        $this->assertSame('web', $dependency->dependency->name);
    }

    public function test_autowired_tagged_dependency_exception(): void
    {
        $container = new GenericContainer();

        try {
            $container->get(DependencyWithTaggedDependency::class);
        } catch (TaggedDependencyCouldNotBeResolved $cannotResolveTaggedDependency) {
            $this->assertStringContainsStringIgnoringLineEndings(
                <<<'TXT'
                	┌── DependencyWithTaggedDependency::__construct(TaggedDependency $dependency)
                	└── Tempest\Container\Tests\Fixtures\TaggedDependency
                TXT,
                $cannotResolveTaggedDependency->getMessage(),
            );
        }
    }

    public function test_singleton_on_class(): void
    {
        $container = new GenericContainer();

        $a = $container->get(ClassWithSingletonAttribute::class);

        $a->flag = true;

        $b = $container->get(ClassWithSingletonAttribute::class);

        $this->assertTrue($b->flag);
    }

    public function test_invoke_callable(): void
    {
        $container = new GenericContainer();
        $container->singleton(SingletonClass::class, fn () => new SingletonClass());

        $this->assertEquals('foo', $container->invoke(InvokableClass::class));
        $this->assertEquals('foobar', $container->invoke([new InvokableClass(), 'execute']));
        $this->assertEquals('bar', $container->invoke(InvokableClassWithParameters::class, param: 'bar'));
        $this->assertInstanceOf(ReflectionClass::class, $container->invoke(fn (SingletonClass $class) => new ReflectionClass($class)));
    }

    public function test_invoke_invokable_class(): void
    {
        $container = new GenericContainer();
        $container->singleton(SingletonClass::class, fn () => new SingletonClass());

        $result = $container->invoke(new ClassReflector(InvokableClassWithDependencies::class), param: 'bar');

        $this->assertSame('bar', $result);
    }

    public function test_call_function_with_parameters(): void
    {
        $container = new GenericContainer();
        $container->singleton(SingletonClass::class, fn () => new SingletonClass());

        $result = $container->invoke(
            method: fn (SingletonClass $class, string $prefix) => $prefix . $class::class,
            prefix: 'My resolved class is ',
        );

        $this->assertEquals('My resolved class is Tempest\Container\Tests\Fixtures\SingletonClass', $result);
    }

    public function test_call_function_with_dependencies_and_parameters(): void
    {
        $container = new GenericContainer();

        $result = $container->invoke(fn (string $param) => $param, param: 'foo');

        $this->assertEquals('foo', $result);
    }

    public function test_call_function_with_unresolvable_parameters(): void
    {
        $this->expectException(DependencyCouldNotBeAutowired::class);
        $this->expectExceptionMessageMatches('/because string cannot be resolved/');

        $container = new GenericContainer();
        $container->invoke(fn (string $param) => $param);
    }

    public function test_call_invalid_closure(): void
    {
        $this->expectException(InvokedCallableWasInvalid::class);
        $this->expectExceptionMessage('[array_map] cannot be invoked through the container.');

        $container = new GenericContainer();
        $container->invoke('array_map');
    }

    public function test_invoke_closure_with_function(): void
    {
        GenericContainer::setInstance($container = new GenericContainer());
        $container->singleton(SingletonClass::class, fn () => new SingletonClass());

        $result = \Tempest\invoke(fn (SingletonClass $class) => $class::class);

        $this->assertEquals(SingletonClass::class, $result);
    }

    public function test_builtin_dependency_initializer(): void
    {
        $container = new GenericContainer();
        $container->addInitializer(BuiltinDependencyArrayInitializer::class);
        $container->addInitializer(BuiltinDependencyBoolInitializer::class);
        $container->addInitializer(BuiltinDependencyStringInitializer::class);

        /** @var DependencyWithBuiltinDependencies $a */
        $a = $container->get(DependencyWithBuiltinDependencies::class);

        $this->assertSame('Hallo dependency!', $a->stringValue);
        $this->assertSame(['hallo', 'array', 42], $a->arrayValue);
        $this->assertTrue($a->boolValue);
    }

    public function test_inject(): void
    {
        $container = new GenericContainer();

        /** @var InjectA $a */
        $a = $container->get(InjectA::class);

        $this->assertInstanceOf(InjectB::class, $a->getB());
    }

    public function test_unregister(): void
    {
        $container = new GenericContainer();

        $container->register(InterfaceA::class, fn () => new ImplementsInterfaceA());

        $this->assertInstanceOf(ImplementsInterfaceA::class, $container->get(InterfaceA::class));

        $container->unregister(InterfaceA::class);

        $this->expectException(DependencyCouldNotBeInstantiated::class);

        $container->get(InterfaceA::class);
    }

    public function test_unregister_singleton(): void
    {
        $container = new GenericContainer();

        $container->singleton(InterfaceA::class, $instance = new ImplementsInterfaceA());

        $this->assertInstanceOf(ImplementsInterfaceA::class, $container->get(InterfaceA::class));
        $this->assertSame($instance, $container->get(InterfaceA::class));

        $container->unregister(InterfaceA::class);

        $this->expectException(DependencyCouldNotBeInstantiated::class);

        $container->get(InterfaceA::class);
    }

    public function test_unregister_tagged(): void
    {
        $container = new GenericContainer();

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('web'),
            tag: 'web',
        );

        $this->assertInstanceOf(TaggedDependency::class, $container->get(TaggedDependency::class, 'web'));

        $container->unregister(TaggedDependency::class, tagged: true);

        $this->expectException(TaggedDependencyCouldNotBeResolved::class);

        $container->get(TaggedDependency::class, 'web');
    }

    public function test_has(): void
    {
        $container = new GenericContainer();

        $this->assertFalse($container->has(InterfaceA::class));

        $container->register(InterfaceA::class, fn () => new ImplementsInterfaceA());

        $this->assertTrue($container->has(InterfaceA::class));
    }

    public function test_has_singleton(): void
    {
        $container = new GenericContainer();

        $this->assertFalse($container->has(InterfaceA::class));

        $container->singleton(InterfaceA::class, new ImplementsInterfaceA());

        $this->assertTrue($container->has(InterfaceA::class));
    }

    public function test_has_tagged_singleton(): void
    {
        $container = new GenericContainer();

        $this->assertFalse($container->has(TaggedDependency::class, 'web'));

        $container->singleton(
            TaggedDependency::class,
            new TaggedDependency('web'),
            tag: 'web',
        );

        $this->assertTrue($container->has(TaggedDependency::class, 'web'));
    }

    /**
     * @template T
     * @param (callable(): T) $callable
     * @return T
     */
    private function assertFasterThan(callable $callable, float $seconds): mixed
    {
        $start = microtime(true);
        $result = $callable();
        $end = microtime(true);
        $this->assertLessThan($seconds, $end - $start);
        return $result;
    }

    /**
     * @template T
     * @param (callable(): T) $callable
     * @return T
     */
    private function assertSlowerThan(callable $callable, float $seconds): mixed
    {
        $start = microtime(true);
        $result = $callable();
        $end = microtime(true);
        $this->assertGreaterThan($seconds, $end - $start);
        return $result;
    }

    public function test_lazy_dependency(): void
    {
        $container = new GenericContainer();

        /**
         * Set the load time of the dependency, increasing this increases test time but makes the test more robust
         * At extremely low values other operations might have a bigger effect than the usleep inside the slow dependency
         */
        $delay = 0.01;
        $counter = 1;

        $container->register(SlowDependency::class, function () use ($delay, &$counter) {
            return new SlowDependency($delay, $counter++);
        });

        // Normal example, this is slow during initialization, fast during use
        $instance1 = $this->assertSlowerThan(fn () => $container->get(ClassWithSlowDependency::class), $delay);
        $this->assertInstanceOf(ClassWithSlowDependency::class, $instance1);
        $this->assertInstanceOf(SlowDependency::class, $instance1->dependency);

        $this->assertSame('value1', $this->assertFasterThan(fn () => $instance1->dependency->value, $delay));

        // Lazy example, this is fast during initialization, slow during (first) use
        $instance2 = $this->assertFasterThan(fn () => $container->get(ClassWithLazySlowDependency::class), $delay);
        $this->assertInstanceOf(ClassWithLazySlowDependency::class, $instance2);
        $this->assertInstanceOf(SlowDependency::class, $instance2->dependency);

        $this->assertSame('value2', $this->assertSlowerThan(fn () => $instance2->dependency->value, $delay));
    }

    public function test_lazy_property_dependency(): void
    {
        $container = new GenericContainer();

        /**
         * Set the load time of the dependency, increasing this increases test time but makes the test more robust
         * At extremely low values other operations might have a bigger effect than the usleep inside the slow dependency
         */
        $delay = 0.01;
        $counter = 1;

        $container->register(SlowDependency::class, function () use ($delay, &$counter) {
            return new SlowDependency($delay, $counter++);
        });

        $instance = $this->assertFasterThan(fn () => $container->get(ClassWithLazySlowPropertyDependency::class), $delay);
        $this->assertInstanceOf(SlowDependency::class, $instance->dependency);

        $this->assertSame('value1', $this->assertSlowerThan(fn () => $instance->dependency->value, $delay));
    }

    public function test_has_tags_support(): void
    {
        $container = new GenericContainer();

        $container->singleton(HasTagObject::class, new HasTagObject('A', 'tagA'));
        $container->singleton(HasTagObject::class, new HasTagObject('B', 'tagB'));

        $this->assertSame('A', $container->get(HasTagObject::class, 'tagA')->name);
        $this->assertSame('B', $container->get(HasTagObject::class, 'tagB')->name);
    }
}
