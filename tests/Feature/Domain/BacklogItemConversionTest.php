<?php

use App\Enums\BacklogItemStatus;
use App\Models\Activity;
use App\Models\BacklogItem;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('converts a backlog item into a worklog and marks it as converted', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'EUR',
    ]);

    $this->actingAs($owner);

    $customer = Customer::factory()->for($owner, 'owner')->create();
    $project = Project::factory()
        ->for($owner, 'owner')
        ->for($customer, 'customer')
        ->create([
            'currency' => 'EUR',
        ]);

    $activity = Activity::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'name' => 'Feature development',
        'default_hourly_rate' => 120,
        'is_billable' => true,
        'is_active' => true,
    ]);

    $backlogItem = BacklogItem::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'activity_id' => $activity->id,
        'title' => 'Implement onboarding flow',
        'description' => 'Deliver first usable onboarding journey.',
        'status' => BacklogItemStatus::Todo,
        'priority' => 2,
        'estimated_minutes' => 180,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $worklog = $backlogItem->convertToWorklog();

    expect($worklog)->toBeInstanceOf(ProjectActivity::class)
        ->and($worklog->owner_id)->toBe($owner->id)
        ->and($worklog->project_id)->toBe($project->id)
        ->and($worklog->activity_id)->toBe($activity->id)
        ->and($worklog->title)->toBe('Implement onboarding flow');

    $backlogItem->refresh();

    expect($backlogItem->status)->toBe(BacklogItemStatus::Done)
        ->and($backlogItem->converted_to_worklog_id)->toBe($worklog->id)
        ->and($backlogItem->converted_at)->not()->toBeNull();

    $worklogCountBeforeSecondCall = ProjectActivity::query()->count();
    $worklogFromSecondCall = $backlogItem->convertToWorklog();
    $worklogCountAfterSecondCall = ProjectActivity::query()->count();

    expect($worklogFromSecondCall->id)->toBe($worklog->id)
        ->and($worklogCountAfterSecondCall)->toBe($worklogCountBeforeSecondCall);
});
