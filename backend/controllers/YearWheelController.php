<?php
require_once __DIR__ . '/../models/YearWheelCategory.php';
require_once __DIR__ . '/../models/YearWheelTask.php';

class YearWheelController
{
    // ── Categories ──

    public function indexCategories(Request $request): void
    {
        $filters = array_filter([
            'customer_id' => $request->query('customer_id'),
        ]);
        $categories = YearWheelCategory::all($filters);
        Response::success($categories);
    }

    public function showCategory(Request $request): void
    {
        $id = (int)$request->param('id');
        $category = YearWheelCategory::find($id);
        if (!$category) {
            Response::error('Category not found', 404);
            return;
        }
        Response::success($category);
    }

    public function storeCategory(Request $request): void
    {
        $data = $request->all();
        if (empty($data['name'])) {
            Response::error('Name is required', 400);
            return;
        }
        $id = YearWheelCategory::create($data);
        if (!$id) {
            Response::error('Unauthorized or creation failed', 403);
            return;
        }
        Logger::log('create', 'yw_categories', $id, 'Year Wheel category created: ' . $data['name']);
        Response::success(YearWheelCategory::find($id), 201);
    }

    public function updateCategory(Request $request): void
    {
        $id = (int)$request->param('id');
        $data = $request->all();
        if (!YearWheelCategory::update($id, $data)) {
            Response::error('Not found or unauthorized', 404);
            return;
        }
        Logger::log('update', 'yw_categories', $id, 'Year Wheel category updated');
        Response::success(YearWheelCategory::find($id));
    }

    public function destroyCategory(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!YearWheelCategory::delete($id)) {
            Response::error('Not found or unauthorized', 404);
            return;
        }
        Logger::log('delete', 'yw_categories', $id, 'Year Wheel category deleted');
        Response::success(null, 204);
    }

    // ── Tasks ──

    public function index(Request $request): void
    {
        $filters = array_filter([
            'year' => $request->query('year'),
            'customer_id' => $request->query('customer_id'),
            'category_id' => $request->query('category_id'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'assigned_to' => $request->query('assigned_to'),
        ], fn($v) => $v !== null && $v !== '');

        // Explicit opt-in/out rather than array_filter, which would strip the
        // string '0' and silently turn "only tasks off the wheel" into no filter.
        if ($request->query('wheel_only') !== null) {
            $filters['show_in_wheel'] = (int)(bool)$request->query('wheel_only');
        }

        $tasks = YearWheelTask::all($filters);
        Response::success($tasks);
    }

    public function show(Request $request): void
    {
        $id = (int)$request->param('id');
        $task = YearWheelTask::find($id);
        if (!$task) {
            Response::error('Task not found', 404);
            return;
        }
        Response::success($task);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        if (empty($data['title'])) {
            Response::error('Title is required', 400);
            return;
        }
        // start_date is optional: a general task may have no deadline. The wheel
        // needs one, so that requirement is enforced by the wheel UI.
        if (!empty($data['show_in_wheel']) && empty($data['start_date'])) {
            Response::error('A start date is required for tasks on the Year Wheel', 400);
            return;
        }
        $id = YearWheelTask::create($data);
        if (!$id) {
            Response::error('Unauthorized or creation failed', 403);
            return;
        }
        Logger::log('create', 'yw_tasks', $id, 'Year Wheel task created: ' . $data['title']);
        Response::success(YearWheelTask::find($id), 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $data = $request->all();

        $existing = YearWheelTask::find($id);
        if (!$existing) {
            Response::error('Not found or unauthorized', 404);
            return;
        }

        // The wheel renders from start_date, so a task that is (or is becoming)
        // a wheel task must keep one. Validated here because the UI is not the
        // only way to call this endpoint.
        $onWheel = array_key_exists('show_in_wheel', $data)
            ? (int)(bool)$data['show_in_wheel']
            : (int)(bool)$existing['show_in_wheel'];
        $startDate = array_key_exists('start_date', $data) ? $data['start_date'] : $existing['start_date'];
        if ($onWheel && empty($startDate)) {
            Response::error('A start date is required for tasks on the Year Wheel', 400);
            return;
        }

        if (!YearWheelTask::update($id, $data)) {
            Response::error('Not found or unauthorized', 404);
            return;
        }
        Logger::log('update', 'yw_tasks', $id, 'Year Wheel task updated');
        Response::success(YearWheelTask::find($id));
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!YearWheelTask::delete($id)) {
            Response::error('Not found or unauthorized', 404);
            return;
        }
        Logger::log('delete', 'yw_tasks', $id, 'Year Wheel task deleted');
        Response::success(null, 204);
    }

    public function rollover(Request $request): void
    {
        $year = (int)($request->input('year') ?: date('Y') + 1);
        $created = YearWheelTask::rollover($year);
        Logger::log('rollover', 'yw_tasks', 0, "Year Wheel rollover for {$year}: " . count($created) . ' tasks created');
        Response::success(['year' => $year, 'tasks_created' => count($created), 'task_ids' => $created]);
    }
}
