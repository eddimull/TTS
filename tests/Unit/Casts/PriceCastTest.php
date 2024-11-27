<?php

namespace Tests\Unit\Casts;

use App\Casts\Price;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Model;

class PriceCastTest extends TestCase
{
    protected $priceCast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priceCast = new Price();
    }

    public function testGetMethodConvertsIntegerCentsToDecimalString()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->get($model, 'price', 10050, []);

        $this->assertEquals('100.50', $result);
    }

    public function testGetMethodHandlesZero()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->get($model, 'price', 0, []);

        $this->assertEquals('0.00', $result);
    }

    public function testGetMethodHandlesLargeNumbers()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->get($model, 'price', 100000000, []);

        $this->assertEquals('1000000.00', $result);
    }

    public function testSetMethodConvertsDecimalToIntegerCents()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->set($model, 'price', 100.50, []);

        $this->assertEquals(10050, $result);
    }

    public function testSetMethodHandlesZero()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->set($model, 'price', 0, []);

        $this->assertEquals(0, $result);
    }

    public function testSetMethodHandlesIntegerInput()
    {
        $model = $this->createMock(Model::class);
        $result = $this->priceCast->set($model, 'price', 100, []);

        $this->assertEquals(10000, $result);
    }
}
