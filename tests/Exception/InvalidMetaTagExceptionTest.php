<?php

declare(strict_types=1);

namespace Yiisoft\Yii\View\Tests\Exception;

use Yiisoft\Yii\View\Exception\InvalidMetaTagException;
use PHPUnit\Framework\TestCase;
use Yiisoft\Yii\View\Tests\Support\TestTrait;

final class InvalidMetaTagExceptionTest extends TestCase
{
    use TestTrait;

    public function testBase(): void
    {
        $exception = new InvalidMetaTagException('test', []);

        $this->assertSame('Invalid meta tag configuration in injection', $exception->getName());
        $this->assertStringStartsWithIgnoringLineEndings(
            "Got meta tag:\narray (\n)\n\nIn injection that implements",
            $exception->getSolution()
        );
    }
}
