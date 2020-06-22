<?php

declare(strict_types=1);

namespace BestIt\ODMParamConverterBundle\Tests\Converter;

use BestIt\CommercetoolsODM\DocumentManagerInterface;
use BestIt\CommercetoolsODM\Mapping\ClassMetadata;
use BestIt\ODMParamConverterBundle\Converter\ODMConverter;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Tests for the odm coverter
 *
 * @package BestIt\ODMParamConverterBundle\Tests\Converter
 */
class ODMConverterTest extends TestCase
{
    /**
     * Test that the support is working
     *
     * @return void
     */
    public function testThatSupportIsWorking()
    {
        $configuration = $this->createMock(ParamConverter::class);
        $configuration
            ->method('getClass')
            ->willReturnOnConsecutiveCalls(null, 'class');

        $converter = new ODMConverter(
            $documentManager = $this->createMock(DocumentManagerInterface::class)
        );

        $meta = $this->createMock(ClassMetadata::class);
        $meta->method('getRepository')->willReturn('repo');

        $documentManager
            ->method('getClassMetadata')
            ->willReturn($meta);

        static::assertFalse($converter->supports($configuration));
        static::assertTrue($converter->supports($configuration));
    }
}
