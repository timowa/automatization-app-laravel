<?php

namespace App\Scenarios;

use App\DTO\OfferChanged;

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
                if (!$ruleObj->passes($offerChanged)) {
                    continue(2);
                }
            }
            return $scenario;
        }

        return null;
    }
}
