<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Rule;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AnalysisContextTest extends TestCase
{

    public function testGetClassName(): void
    {
        $context = new AnalysisContext(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertSame('TestClass', $context->getClassName());
    }

    public function testGetClassNameReturnsNull(): void
    {
        $context = new AnalysisContext(
            className: null,
            methodName: null,
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertNull($context->getClassName());
    }

    public function testGetMethodName(): void
    {
        $context = new AnalysisContext(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertSame('testMethod', $context->getMethodName());
    }

    public function testGetMethodNameReturnsNull(): void
    {
        $context = new AnalysisContext(
            className: null,
            methodName: null,
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertNull($context->getMethodName());
    }

    public function testGetFilePath(): void
    {
        $context = new AnalysisContext(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertSame('/path/to/file.php', $context->getFilePath());
    }

    public function testIsPatchModeReturnsFalse(): void
    {
        $context = new AnalysisContext(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertFalse($context->isPatchMode());
    }

    public function testIsPatchModeReturnsTrue(): void
    {
        $context = new AnalysisContext(
            className: 'TestClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: true,
        );

        self::assertTrue($context->isPatchMode());
    }

    public function testGetClassReflection(): void
    {
        $context = new AnalysisContext(
            className: self::class,
            methodName: 'testGetClassReflection',
            filePath: __FILE__,
            patchMode: false,
        );

        $reflection = $context->getClassReflection();
        self::assertInstanceOf(ReflectionClass::class, $reflection);
        self::assertSame(self::class, $reflection->getName());
    }

    public function testGetClassReflectionReturnsNullWhenClassNameIsNull(): void
    {
        $context = new AnalysisContext(
            className: null,
            methodName: null,
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertNull($context->getClassReflection());
    }

    public function testGetClassReflectionThrowsExceptionForNonExistentClass(): void
    {
        $context = new AnalysisContext(
            className: 'NonExistentClass',
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Could not get reflection for class NonExistentClass');

        $context->getClassReflection();
    }

    public function testGetMethodReflection(): void
    {
        $context = new AnalysisContext(
            className: self::class,
            methodName: 'testGetMethodReflection',
            filePath: __FILE__,
            patchMode: false,
        );

        $reflection = $context->getMethodReflection();
        self::assertInstanceOf(ReflectionMethod::class, $reflection);
        self::assertSame('testGetMethodReflection', $reflection->getName());
    }

    public function testGetMethodReflectionReturnsNullWhenClassNameIsNull(): void
    {
        $context = new AnalysisContext(
            className: null,
            methodName: 'testMethod',
            filePath: '/path/to/file.php',
            patchMode: false,
        );

        self::assertNull($context->getMethodReflection());
    }

    public function testGetMethodReflectionReturnsNullWhenMethodNameIsNull(): void
    {
        $context = new AnalysisContext(
            className: self::class,
            methodName: null,
            filePath: __FILE__,
            patchMode: false,
        );

        self::assertNull($context->getMethodReflection());
    }

    public function testGetMethodReflectionThrowsExceptionForNonExistentMethod(): void
    {
        $context = new AnalysisContext(
            className: self::class,
            methodName: 'nonExistentMethod',
            filePath: __FILE__,
            patchMode: false,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Could not get reflection for method');

        $context->getMethodReflection();
    }

}
