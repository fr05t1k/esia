<?php
namespace Helper;
// here you can define custom actions
// all public methods declared in helper class will be available in $I

use AspectMock\Test;
use Codeception\Module;
use Codeception\TestCase;

class Unit extends Module
{
    public function _after(TestCase $test)
    {
        Test::clean();
        parent::_after($test);
    }


}
