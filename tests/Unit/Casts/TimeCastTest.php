<?php

namespace Tests\Unit\Casts;

use App\Casts\TimeCast;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Model;

class TimeCastTest extends TestCase
{
    protected $timeCast;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timeCast = new TimeCast();
    }

    public function testGetMethodWithStringValue()
    {
        $model = $this->createMock(Model::class);
        $result = $this->timeCast->get($model, 'time_field', '14:30:00', []);

        $this->assertEquals('14:30:00', $result);
    }

    public function testGetMethodWithCarbonValue()
    {
        $model = $this->createMock(Model::class);
        $carbonTime = Carbon::createFromTime(14, 30, 0);
        $result = $this->timeCast->get($model, 'time_field', $carbonTime, []);

        $this->assertEquals('14:30:00', $result);
    }

    public function testSetMethod()
    {
        $model = $this->createMock(Model::class);
        $value = '14:30:00';
        $result = $this->timeCast->set($model, 'time_field', $value, []);

        $this->assertEquals($value, $result);
    }
}
