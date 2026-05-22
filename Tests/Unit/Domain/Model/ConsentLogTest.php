<?php

declare(strict_types=1);

namespace Maispace\MaiConsent\Tests\Unit\Domain\Model;

use Maispace\MaiConsent\Domain\Model\ConsentCategory;
use Maispace\MaiConsent\Domain\Model\ConsentLog;
use PHPUnit\Framework\TestCase;

class ConsentLogTest extends TestCase
{
    private ConsentLog $subject;

    protected function setUp(): void
    {
        $this->subject = new ConsentLog();
    }

    public function testDefaultCategoryIsNull(): void
    {
        self::assertNull($this->subject->getCategory());
    }

    public function testDefaultAcceptedIsFalse(): void
    {
        self::assertFalse($this->subject->isAccepted());
    }

    public function testDefaultSessionIsEmptyString(): void
    {
        self::assertSame('', $this->subject->getSession());
    }

    public function testDefaultIpAddressIsEmptyString(): void
    {
        self::assertSame('', $this->subject->getIpAddress());
    }

    public function testSetCategoryStoresCategory(): void
    {
        $category = new ConsentCategory();
        $category->setTitle('Analytics');
        $this->subject->setCategory($category);

        self::assertSame($category, $this->subject->getCategory());
    }

    public function testSetCategoryAcceptsNull(): void
    {
        $category = new ConsentCategory();
        $this->subject->setCategory($category);
        $this->subject->setCategory(null);

        self::assertNull($this->subject->getCategory());
    }

    public function testSetAcceptedStoresToTrue(): void
    {
        $this->subject->setAccepted(true);
        self::assertTrue($this->subject->isAccepted());
    }

    public function testSetAcceptedStoresToFalse(): void
    {
        $this->subject->setAccepted(true);
        $this->subject->setAccepted(false);
        self::assertFalse($this->subject->isAccepted());
    }

    public function testSetSessionStoresSession(): void
    {
        $this->subject->setSession('abc123session');
        self::assertSame('abc123session', $this->subject->getSession());
    }

    public function testSetIpAddressStoresIpAddress(): void
    {
        $this->subject->setIpAddress('192.168.1.0');
        self::assertSame('192.168.1.0', $this->subject->getIpAddress());
    }

    public function testInstancesAreIndependent(): void
    {
        $other = new ConsentLog();
        $other->setAccepted(true);
        $other->setSession('other-session');
        $other->setIpAddress('10.0.0.0');

        self::assertFalse($this->subject->isAccepted());
        self::assertSame('', $this->subject->getSession());
        self::assertSame('', $this->subject->getIpAddress());
    }
}
