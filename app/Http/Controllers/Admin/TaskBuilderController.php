<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkflowSectionRequest;
use App\Http\Requests\Admin\StoreWorkflowTaskRequest;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskBuilderController extends Controller
{
    public function index(): View
    {
        $visaTypes = VisaType::orderBy('name')->with(['workflowSections.tasks'])->get();

        $breadcrumbs = [
            ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
            ['label' => __('admin.nav_task_builder'), 'route' => null],
        ];

        return view('admin.task-builder.index', compact('visaTypes', 'breadcrumbs'));
    }

    public function storeSection(StoreWorkflowSectionRequest $request): RedirectResponse
    {
        $maxPosition = WorkflowSection::where('visa_type_id', $request->visa_type_id)->max('position') ?? 0;

        WorkflowSection::create([
            'visa_type_id' => $request->visa_type_id,
            'name' => $request->name,
            'position' => $maxPosition + 1,
        ]);

        return redirect()->route('admin.task-builder.index')->with('success', __('admin.section_created'));
    }

    public function destroySection(WorkflowSection $section): RedirectResponse
    {
        $section->delete();

        return redirect()->route('admin.task-builder.index')->with('success', __('admin.section_deleted'));
    }

    public function storeTask(StoreWorkflowTaskRequest $request, WorkflowSection $section): RedirectResponse
    {
        $maxPosition = $section->tasks()->max('position') ?? 0;

        WorkflowTask::create([
            'workflow_section_id' => $section->id,
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'approval_mode' => $request->input('approval_mode'), // nullable — null for non-question tasks
            'position' => $maxPosition + 1,
        ]);

        return redirect()->route('admin.task-builder.index')->with('success', __('admin.task_created'));
    }

    public function destroyTask(WorkflowTask $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('admin.task-builder.index')->with('success', __('admin.task_deleted'));
    }
}
