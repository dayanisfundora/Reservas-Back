<?php

namespace App\Http\Controllers;

use App\Http\Requests\OriginRequest;
use App\Http\Requests\StateRequest;
use App\Models\Origin;
use App\Models\State;
use Illuminate\Http\Request;
use App\Traits\HasBasicCrudOperations;

class StateController extends Controller
{
    use HasBasicCrudOperations;

    /**
     * Constructor to initialize properties for HasBasicCrudOperations trait
     */
    public function __construct()
    {
        $this->modelClass = State::class;
        $this->uniqueFields = ['code', 'description'];
        $this->allowedSorts = ['code', 'description', 'state', 'id', 'created_at', 'updated_at'];
        $this->searchFields = ['code', 'description'];
        $this->pdfViewName = 'pdf.states';
    }

    /**
     * Custom filter logic for State index
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function filterQuery($query, Request $request)
    {
        if ($request->filled('state')) {
            $stateValue = $request->input('state');
            if ($stateValue === '1' || $stateValue === 1 || $stateValue === true || $stateValue === 'true') {
                $query->where('state', true);
            } elseif ($stateValue === '0' || $stateValue === 0 || $stateValue === false || $stateValue === 'false') {
                $query->where('state', false);
            }
        }
        return $query;
    }

    /**
     * Store a newly created State with StateRequest validation
     * @param \App\Http\Requests\StateRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StateRequest $request)
    {
        return $this->handleStore($request->validated());
    }

    /**
     * Update an existing State with StateRequest validation
     * @param \App\Http\Requests\StateRequest $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StateRequest $request, string $id)
    {
        return $this->handleUpdate($id, $request->validated());
    }
}
