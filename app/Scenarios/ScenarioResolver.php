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

        if (is_null($offerChanged->previous)) {
            return new $scenarios[0];
        }

        foreach ($scenarios as $scenario) {
            $rules = $scenario->rules();
            foreach ($rules as $rule) {
                if (!$rule->passes($offerChanged)) {
                    continue(2);
                }
            }
            return new $scenario;
        }

        return null;
    }
}
