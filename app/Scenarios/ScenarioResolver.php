<?php

namespace App\Scenarios;

use App\DTO\OfferChanged;
use Illuminate\Support\Facades\Log;

class ScenarioResolver
{
    public function __construct(
        protected ScenarioFactory $scenarioFactory,
    )
    {

    }
    public function resolve(OfferChanged $offerChanged): ?Scenario
    {
        $scenarios = $this->scenarioFactory->list();

        foreach ($scenarios as $scenarioClass) {
            $scenario = new $scenarioClass;
            $rules = $scenario->rules();
            foreach ($rules as $rule) {

                $ruleObj = new $rule;
                $passes = $ruleObj->passes($offerChanged);
                if (!$passes) {
                    continue(2);
                }
            }
            return $scenario;
        }

        return null;
    }
}
