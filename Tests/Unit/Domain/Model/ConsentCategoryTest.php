<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Domain\Model;

use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use PHPUnit\Framework\TestCase;

class ConsentCategoryTest extends TestCase
{
    private ConsentCategory $subject;

    protected function setUp(): void
    {
        $this->subject = new ConsentCategory();
    }

    public function testDefaultTitleIsEmptyString(): void
    {
        self::assertSame('', $this->subject->getTitle());
    }

    public function testDefaultIdentifierIsEmptyString(): void
    {
        self::assertSame('', $this->subject->getIdentifier());
    }

    public function testDefaultDescriptionIsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    public function testDefaultIsRequiredIsFalse(): void
    {
        self::assertFalse($this->subject->isRequired());
    }

    public function testSetTitleStoresTitle(): void
    {
        $this->subject->setTitle('Analytics');
        self::assertSame('Analytics', $this->subject->getTitle());
    }

    public function testSetIdentifierStoresIdentifier(): void
    {
        $this->subject->setIdentifier('analytics');
        self::assertSame('analytics', $this->subject->getIdentifier());
    }

    public function testSetDescriptionStoresDescription(): void
    {
        $this->subject->setDescription('Used to track page visits.');
        self::assertSame('Used to track page visits.', $this->subject->getDescription());
    }

    public function testSetIsRequiredStoresToTrue(): void
    {
        $this->subject->setIsRequired(true);
        self::assertTrue($this->subject->isRequired());
    }

    public function testSetIsRequiredStoresToFalse(): void
    {
        $this->subject->setIsRequired(true);
        $this->subject->setIsRequired(false);
        self::assertFalse($this->subject->isRequired());
    }

    public function testInstancesAreIndependent(): void
    {
        $other = new ConsentCategory();
        $other->setTitle('Marketing');
        $other->setIdentifier('marketing');
        $other->setIsRequired(true);

        self::assertSame('', $this->subject->getTitle());
        self::assertSame('', $this->subject->getIdentifier());
        self::assertFalse($this->subject->isRequired());
    }
}
