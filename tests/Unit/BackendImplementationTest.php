<?php

namespace Tests\Unit;

use App\Enums\PublicationTaskType;
use App\Enums\ScenarioType;
use App\Helpers\JobResolver;
use App\Helpers\PublicationTaskDependenceInspector;
use App\Helpers\ScenarioVkPostTemplateResolver;
use App\Scenarios\ScenarioFactory;
use PHPUnit\Framework\TestCase;

class BackendImplementationTest extends TestCase
{
    public function test_scenario_factory_returns_eleven_scenarios_in_order(): void
    {
        $factory = new ScenarioFactory;
        $list = $factory->list();

        $this->assertCount(11, $list);

        $expected = [
            ScenarioType::ANNOUNCEMENT->value,
            ScenarioType::RENT->value,
            ScenarioType::SALE->value,
            ScenarioType::PRICE_CHANGED->value,
            ScenarioType::AGENT_CHANGED->value,
            ScenarioType::BOOKING->value,
            ScenarioType::SOLD->value,
            ScenarioType::FEEDBACK->value,
            ScenarioType::WITHDRAWN->value,
            ScenarioType::DELAYED->value,
            ScenarioType::DELETED->value,
        ];

        $actual = array_map(fn ($scenario) => (new $scenario)->type()->value, $list);

        $this->assertSame($expected, $actual);
    }

    public function test_job_resolver_returns_class_for_every_task_type(): void
    {
        $resolver = new JobResolver;

        foreach (PublicationTaskType::cases() as $type) {
            $class = $resolver->resolve($type);

            $this->assertNotEmpty($class);
            $this->assertTrue(class_exists($class), "Job class {$class} does not exist");
        }
    }

    public function test_dependence_inspector_returns_expected_dependencies(): void
    {
        $inspector = new PublicationTaskDependenceInspector;

        $this->assertNull($inspector->inspect(PublicationTaskType::VK_POST));
        $this->assertNull($inspector->inspect(PublicationTaskType::VK_END_LOOP_STORY));

        $this->assertSame(PublicationTaskType::VK_POST, $inspector->inspect(PublicationTaskType::VK_STORY));
        $this->assertSame(PublicationTaskType::VK_POST, $inspector->inspect(PublicationTaskType::VK_LOOP_STORY));
        $this->assertSame(PublicationTaskType::VK_POST, $inspector->inspect(PublicationTaskType::VK_COMMENT));
        $this->assertSame(PublicationTaskType::VK_POST, $inspector->inspect(PublicationTaskType::VK_REPOST));
        $this->assertSame(PublicationTaskType::VK_POST, $inspector->inspect(PublicationTaskType::VK_CREATE_PRODUCT));

        $this->assertSame(PublicationTaskType::VK_CREATE_PRODUCT, $inspector->inspect(PublicationTaskType::VK_EDIT_PRODUCT));
        $this->assertSame(PublicationTaskType::VK_CREATE_PRODUCT, $inspector->inspect(PublicationTaskType::VK_ARCHIVE_PRODUCT));
    }

    public function test_scenario_template_resolver_returns_template_for_post_scenarios(): void
    {
        $resolver = new ScenarioVkPostTemplateResolver;

        $postScenarios = [
            ScenarioType::ANNOUNCEMENT,
            ScenarioType::RENT,
            ScenarioType::SALE,
            ScenarioType::AGENT_CHANGED,
            ScenarioType::PRICE_CHANGED,
            ScenarioType::BOOKING,
            ScenarioType::SOLD,
            ScenarioType::FEEDBACK,
        ];

        foreach ($postScenarios as $scenario) {
            $template = $resolver->resolve($scenario);
            $this->assertInstanceOf(\App\Interfaces\VkPostTemplateInterface::class, $template);
        }
    }
}
