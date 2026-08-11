<?php

namespace App\Scenarios;

use App\Scenarios\AnnouncementScenario\AnnouncementScenario;

class ScenarioFactory
{
    /**
     * @return Scenario[]
     */
    public function list():array
    {
        return [
            AnnouncementScenario::class
        ];
    }
}
