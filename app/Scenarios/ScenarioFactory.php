<?php

namespace App\Scenarios;

use App\Scenarios\Scenarios\AgentChangedScenario\AgentChangedScenario;
use App\Scenarios\Scenarios\AnnouncementScenario\AnnouncementScenario;
use App\Scenarios\Scenarios\BookedScenario\BookedScenario;
use App\Scenarios\Scenarios\DeletedScenario\DeletedScenario;
use App\Scenarios\Scenarios\DelayedScenario\DelayedScenario;
use App\Scenarios\Scenarios\FeedbackScenario\FeedbackScenario;
use App\Scenarios\Scenarios\PriceChangedScenario\PriceChangedScenario;
use App\Scenarios\Scenarios\RentScenario\RentScenario;
use App\Scenarios\Scenarios\SaleScenario\SaleScenario;
use App\Scenarios\Scenarios\SoldScenario\SoldScenario;
use App\Scenarios\Scenarios\WithdrawnScenario\WithdrawnScenario;

class ScenarioFactory
{
    /**
     * @return class-string<Scenario>[]
     */
    public function list(): array
    {
        return [
            AnnouncementScenario::class,
            RentScenario::class,
            SaleScenario::class,
            PriceChangedScenario::class,
            AgentChangedScenario::class,
            BookedScenario::class,
            SoldScenario::class,
            FeedbackScenario::class,
            WithdrawnScenario::class,
            DelayedScenario::class,
            DeletedScenario::class,
        ];
    }
}
